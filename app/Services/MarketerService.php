<?php

namespace App\Services;

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\Marketers\Status;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\Users\Status as UserStatus;
use App\Exceptions\InvalidMarketerTransitionException;
use App\Models\Marketer;
use App\Models\MarketerReferral;
use App\Models\User;
use App\Support\ReferralCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketerService
{
    public function apply(User $user): Marketer
    {
        return DB::transaction(function () use ($user) {
            $existing = Marketer::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->isPending() || $existing->isActive()) {
                    return $existing;
                }

                throw new InvalidMarketerTransitionException('A marketer capability already exists for this user.');
            }

            try {
                return Marketer::query()->create([
                    'user_id' => $user->id,
                    'referral_code' => $this->generateUniqueReferralCode(),
                    'status' => Status::Pending,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                $marketer = Marketer::query()->where('user_id', $user->id)->first();
                if ($marketer === null) {
                    throw $exception;
                }

                return $marketer;
            }
        });
    }

    public function reapply(User $user): Marketer
    {
        return DB::transaction(function () use ($user) {
            $marketer = Marketer::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($marketer === null) {
                return $this->apply($user);
            }

            if ($marketer->isPending() || $marketer->isActive()) {
                return $marketer;
            }

            if (! $marketer->isRejected()) {
                throw new InvalidMarketerTransitionException('Only a rejected application can be submitted again.');
            }

            $marketer->status = Status::Pending;
            $marketer->save();

            return $marketer;
        });
    }

    public function approve(Marketer $marketer): Marketer
    {
        return $this->transition($marketer, Status::Active, [Status::Pending, Status::Rejected, Status::Inactive]);
    }

    public function reject(Marketer $marketer): Marketer
    {
        return $this->transition($marketer, Status::Rejected, [Status::Pending]);
    }

    public function deactivate(Marketer $marketer): Marketer
    {
        return $this->transition($marketer, Status::Inactive, [Status::Active]);
    }

    public function reactivate(Marketer $marketer): Marketer
    {
        return $this->transition($marketer, Status::Active, [Status::Inactive]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createByAdmin(array $data): Marketer
    {
        $status = $data['status'] instanceof Status ? $data['status'] : Status::from((int) $data['status']);

        if (! in_array($status, [Status::Pending, Status::Active], true)) {
            throw ValidationException::withMessages([
                'status' => 'Admin-created marketers must start as Pending or Active.',
            ]);
        }

        return DB::transaction(function () use ($data, $status) {
            $mode = (string) $data['mode'];

            if ($mode === 'attach') {
                $user = User::query()->where('email', strtolower(trim((string) $data['user_email'])))->first();
                if ($user === null) {
                    throw ValidationException::withMessages([
                        'user_email' => 'No user exists with this email.',
                    ]);
                }

                if (Marketer::query()->where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages([
                        'user_email' => 'This user already has a marketer capability.',
                    ]);
                }

                return Marketer::query()->create([
                    'user_id' => $user->id,
                    'referral_code' => $this->generateUniqueReferralCode(),
                    'status' => $status,
                ]);
            }

            $email = strtolower(trim((string) $data['email']));
            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'A user with this email already exists. Attach the existing account instead of creating another user.',
                ]);
            }

            $user = new User;
            $user->name = (string) $data['name'];
            $user->email = $email;
            $user->phone = isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null;
            $user->password = (string) $data['password'];
            $user->status = UserStatus::Active;
            $user->save();

            return Marketer::query()->create([
                'user_id' => $user->id,
                'referral_code' => $this->generateUniqueReferralCode(),
                'status' => $status,
            ]);
        });
    }

    public function getPaginatedMarketers(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        ?Status $status = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'created_at', 'status', 'referral_code'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return Marketer::query()
            ->with(['user:id,name,email,phone'])
            ->withReferralCapabilityCounts()
            ->when($status instanceof Status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('referral_code', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function pendingCount(): int
    {
        return Marketer::query()->where('status', Status::Pending)->count();
    }

    /**
     * @return array{
     *     total_referred_users: int,
     *     customers: int,
     *     merchants: int,
     *     dual: int,
     *     registrations_this_month: int,
     *     referral_code: string,
     *     referral_url: string,
     *     recent_referrals: list<array<string, mixed>>
     * }
     */
    public function dashboardMetrics(Marketer $marketer): array
    {
        $counts = $this->capabilityCountsForMarketer($marketer);
        $monthStart = Carbon::now()->startOfMonth();

        $recent = $marketer->referrals()
            ->with(['referredUser.customer', 'referredUser.merchantMemberships.merchant'])
            ->orderByDesc('registered_at')
            ->limit(8)
            ->get()
            ->map(fn (MarketerReferral $referral) => $this->serializeReferral($referral))
            ->all();

        return [
            'total_referred_users' => $counts['total'],
            'customers' => $counts['customers'],
            'merchants' => $counts['merchants'],
            'dual' => $counts['dual'],
            'registrations_this_month' => $marketer->referrals()
                ->where('registered_at', '>=', $monthStart)
                ->count(),
            'referral_code' => $marketer->referral_code,
            'referral_url' => $this->referralUrl($marketer),
            'recent_referrals' => $recent,
        ];
    }

    public function getOwnPaginatedReferrals(
        Marketer $marketer,
        string $search = '',
        string $sortBy = 'registered_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['registered_at', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'registered_at';

        $paginator = $marketer->referrals()
            ->with(['referredUser.customer', 'referredUser.merchantMemberships.merchant'])
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('referredUser', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(
            fn (MarketerReferral $referral) => $this->serializeReferral($referral)
        );

        return $paginator;
    }

    public function referralUrl(Marketer $marketer): string
    {
        $parameter = (string) config('referrals.query_parameter', 'ref');

        return rtrim((string) config('app.url'), '/').'/?'.http_build_query([
            $parameter => $marketer->referral_code,
        ]);
    }

    /**
     * @param  list<Status>  $from
     */
    private function transition(Marketer $marketer, Status $to, array $from): Marketer
    {
        if (! in_array($marketer->status, $from, true)) {
            throw new InvalidMarketerTransitionException('This status change is not allowed.');
        }

        $marketer->status = $to;
        $marketer->save();

        return $marketer;
    }

    /**
     * @return array{total: int, customers: int, merchants: int, dual: int}
     */
    private function capabilityCountsForMarketer(Marketer $marketer): array
    {
        $referrals = $marketer->relationLoaded('referrals')
            ? $marketer->referrals
            : $marketer->referrals()->with(['referredUser.customer', 'referredUser.merchantMemberships.merchant'])->get();

        $customers = 0;
        $merchants = 0;
        $dual = 0;

        foreach ($referrals as $referral) {
            $flags = $this->referredCapabilityFlags($referral);
            if ($flags['customer']) {
                $customers++;
            }
            if ($flags['merchant']) {
                $merchants++;
            }
            if ($flags['customer'] && $flags['merchant']) {
                $dual++;
            }
        }

        return [
            'total' => $referrals->count(),
            'customers' => $customers,
            'merchants' => $merchants,
            'dual' => $dual,
        ];
    }

    /**
     * @return array{customer: bool, merchant: bool}
     */
    private function referredCapabilityFlags(MarketerReferral $referral): array
    {
        $user = $referral->referredUser;
        $isCustomer = $user?->customer?->status === CustomerStatus::Active;
        $isMerchant = $user?->merchantMemberships?->contains(
            fn ($membership) => $membership->status === MembershipStatus::Active
                && $membership->merchant?->status === MerchantStatus::Active
        ) === true;

        return [
            'customer' => $isCustomer,
            'merchant' => $isMerchant,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReferral(MarketerReferral $referral): array
    {
        $flags = $this->referredCapabilityFlags($referral);
        $user = $referral->referredUser;

        return [
            'id' => $referral->id,
            'name' => $user?->name,
            'email' => $user?->email,
            'registered_at' => $referral->registered_at?->toIso8601String(),
            'is_customer' => $flags['customer'],
            'is_merchant' => $flags['merchant'],
            'is_dual' => $flags['customer'] && $flags['merchant'],
        ];
    }

    private function generateUniqueReferralCode(): string
    {
        for ($attempt = 0; $attempt < 16; $attempt++) {
            $code = ReferralCode::generate();
            if (! Marketer::query()->where('referral_code', $code)->exists()) {
                return $code;
            }
        }

        throw new InvalidMarketerTransitionException('Unable to generate a unique referral code.');
    }
}
