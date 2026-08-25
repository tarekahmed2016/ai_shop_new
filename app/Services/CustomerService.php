<?php

namespace App\Services;

use App\Enums\CustomerRequests\Source;
use App\Enums\Customers\Status;
use App\Enums\Users\Status as UserStatus;
use App\Models\Customer;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'user_id',
        'name',
        'phone',
        'whatsapp_id',
        'email',
        'daily_request_limit_override',
        'status',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
        public CustomerRequestLimitService $customerRequestLimitService,
        public CustomerContactAbuseService $customerContactAbuseService,
        public PlatformSettingService $platformSettingService,
        public CustomerDailyRequestLimitAuditService $customerDailyRequestLimitAuditService,
    ) {}

    public function getPaginatedCustomers(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'name', 'phone', 'email', 'status', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        [$start, $end] = $this->customerRequestLimitService->todayUtcRange();
        $globalLimit = $this->customerRequestLimitService->globalLimit();

        $paginator = Customer::query()
            ->with('user:id,name,email,phone')
            ->withSum('extraRequestTransactions as extra_request_balance', 'amount')
            ->withCount([
                'requests',
                'requests as requests_today_count' => function ($query) use ($start, $end) {
                    $query->whereIn('source', [Source::Web, Source::WhatsApp])
                        ->whereBetween('created_at', [$start, $end]);
                },
            ])
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('whatsapp_id', 'like', "%{$search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(function (Customer $customer) use ($globalLimit) {
            $effective = $customer->daily_request_limit_override ?? $globalLimit;
            $used = (int) $customer->requests_today_count;
            $customer->setAttribute('has_portal_access', $customer->user_id !== null);
            $customer->setAttribute('requests_today', $used);
            $customer->setAttribute('daily_limit', (int) $effective);
            $customer->setAttribute('daily_limit_global', (int) $globalLimit);
            $customer->setAttribute(
                'daily_limit_override',
                $customer->daily_request_limit_override !== null ? (int) $customer->daily_request_limit_override : null,
            );
            $customer->setAttribute('remaining_today', max(0, (int) $effective - $used));
            $customer->setAttribute('extra_request_balance', (int) ($customer->extra_request_balance ?? 0));

            return $customer;
        });

        return $paginator;
    }

    /**
     * @return Collection<int, Customer>
     */
    public function optionsForRequests(?bool $activeOnly = true): Collection
    {
        return Customer::query()
            ->when($activeOnly, fn ($q) => $q->where('status', Status::Active))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'public_id', 'name', 'phone', 'email', 'status']);
    }

    /**
     * Link a Customer to an existing User without creating a User or merging historical rows.
     * Does not reactivate an inactive Customer.
     */
    public function ensureForUser(User $user): Customer
    {
        $existing = Customer::query()->where('user_id', $user->id)->first();
        if ($existing !== null) {
            $user->setRelation('customer', $existing);

            return $existing;
        }

        try {
            return DB::transaction(function () use ($user): Customer {
                $existing = Customer::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $user->setRelation('customer', $existing);

                    return $existing;
                }

                $customer = new Customer;
                $customer->public_id = (string) Str::ulid();
                $customer->user_id = $user->id;
                $customer->name = $user->name;
                $customer->email = $user->email;
                $customer->phone = $user->phone;
                $customer->status = Status::Active;
                $customer->save();

                $this->activityLogService->recordCreated(
                    subject: $customer,
                    allowedFields: self::ACTIVITY_FIELDS,
                    subjectLabel: $customer->display_name,
                    metadata: [
                        'action' => 'customer.capability_ensured',
                        'user_id' => $user->id,
                    ],
                    actor: $user,
                );

                $user->setRelation('customer', $customer);

                return $customer;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $customer = Customer::query()->where('user_id', $user->id)->first();
            if ($customer === null) {
                throw $exception;
            }

            $user->setRelation('customer', $customer);

            return $customer;
        }
    }

    /**
     * Admin create: always User + linked Customer in one transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Customer
    {
        unset($data['user_id']);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $this->assertEmailAvailableForNewUser($email);

        return DB::transaction(function () use ($data, $email) {
            $notes = $this->pullLimitNotes($data);
            $override = array_key_exists('daily_request_limit_override', $data)
                ? $data['daily_request_limit_override']
                : null;

            $user = new User;
            $user->name = (string) $data['name'];
            $user->email = $email;
            $user->phone = $data['phone'] ?? null;
            $user->password = (string) $data['password'];
            $user->status = UserStatus::Active;
            $user->save();

            $customer = new Customer;
            $customer->public_id = (string) Str::ulid();
            $customer->user_id = $user->id;
            $customer->name = $user->name;
            $customer->email = $user->email;
            $customer->phone = $user->phone;
            $customer->whatsapp_id = $data['whatsapp_id'] ?? null;
            $customer->status = $data['status'] instanceof Status
                ? $data['status']
                : Status::from((int) $data['status']);
            $customer->daily_request_limit_override = $override;
            $customer->save();

            $this->activityLogService->recordCreated(
                subject: $customer,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $customer->display_name,
                metadata: [
                    'action' => 'customer.admin_created_with_user',
                    'user_id' => $user->id,
                ],
                actor: auth()->user() instanceof User ? auth()->user() : null,
            );

            $this->customerDailyRequestLimitAuditService->recordOverrideChange(
                customer: $customer,
                oldOverride: null,
                newOverride: $customer->daily_request_limit_override,
                actor: auth()->user() instanceof User ? auth()->user() : null,
                notes: $notes,
            );

            return $customer->load('user');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        unset($data['user_id'], $data['password'], $data['password_confirmation']);

        $this->assertIdentifiable($data);

        $originalValues = $customer->only(self::ACTIVITY_FIELDS);
        $oldOverride = $customer->daily_request_limit_override;
        $notes = $this->pullLimitNotes($data);
        $actor = auth()->user() instanceof User ? auth()->user() : null;

        return DB::transaction(function () use ($customer, $data, $originalValues, $oldOverride, $notes, $actor) {
            $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : $customer->email;

            if ($customer->user_id) {
                $this->assertEmailAvailableForNewUser($email, ignoreUserId: (int) $customer->user_id);
            }

            $customer->fill([
                'name' => $data['name'] ?? $customer->name,
                'phone' => $data['phone'] ?? $customer->phone,
                'email' => $email,
                'whatsapp_id' => array_key_exists('whatsapp_id', $data) ? $data['whatsapp_id'] : $customer->whatsapp_id,
                'status' => $data['status'] ?? $customer->status,
            ]);

            if (array_key_exists('daily_request_limit_override', $data)) {
                $customer->daily_request_limit_override = $data['daily_request_limit_override'];
            }

            $wasSuspended = $originalValues['status'] === Status::Suspended || $originalValues['status'] === Status::Suspended->value;
            if ($wasSuspended && $customer->status === Status::Active) {
                $customer->suspended_at = null;
            }

            $customer->save();

            if ($customer->user_id) {
                $user = $customer->user;
                if ($user) {
                    $user->name = $customer->name;
                    $user->email = $customer->email;
                    $user->phone = $customer->phone;
                    if ($user->isDirty('email')) {
                        $user->email_verified_at = null;
                    }
                    $user->save();
                }
            }

            $this->activityLogService->recordChanges(
                subject: $customer,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $customer->display_name,
            );

            if (array_key_exists('daily_request_limit_override', $data)) {
                $this->customerDailyRequestLimitAuditService->recordOverrideChange(
                    customer: $customer,
                    oldOverride: $oldOverride,
                    newOverride: $customer->daily_request_limit_override,
                    actor: $actor,
                    notes: $notes,
                );
            }

            return $customer->fresh('user');
        });
    }

    public function updateDailyLimit(int $limit, ?string $notes = null): void
    {
        $old = $this->platformSettingService->dailyCustomerRequestLimit();
        $max = max(1, (int) config('customer_requests.max_daily_limit', 100));
        $new = max(1, min($max, $limit));
        $actor = auth()->user() instanceof User ? auth()->user() : null;

        try {
            DB::transaction(function () use ($new, $old, $actor, $notes) {
                $this->platformSettingService->setDailyCustomerRequestLimit($new);
                $this->customerDailyRequestLimitAuditService->recordGlobalLimitChange(
                    oldLimit: $old,
                    newLimit: $new,
                    actor: $actor,
                    notes: $notes,
                );
            });
        } catch (\Throwable $exception) {
            $this->platformSettingService->forget(PlatformSetting::KEY_DAILY_CUSTOMER_REQUEST_LIMIT);

            throw $exception;
        }
    }

    public function reactivate(Customer $customer): Customer
    {
        return $this->customerContactAbuseService->reactivate($customer);
    }

    /**
     * Explicit portal enable for historical unlinked customers.
     * Never auto-links an existing User by email.
     *
     * @param  array<string, mixed>  $data
     */
    public function createLoginAccount(Customer $customer, array $data): Customer
    {
        if ($customer->user_id !== null) {
            throw ValidationException::withMessages([
                'email' => 'This customer already has portal access.',
            ]);
        }

        unset($data['user_id']);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $this->assertEmailAvailableForNewUser($email);

        return DB::transaction(function () use ($customer, $data, $email) {
            $user = new User;
            $user->name = (string) ($data['name'] ?? $customer->name ?? $email);
            $user->email = $email;
            $user->phone = $data['phone'] ?? $customer->phone;
            $user->password = (string) $data['password'];
            $user->status = UserStatus::Active;
            $user->save();

            $customer->user_id = $user->id;
            $customer->name = $user->name;
            $customer->email = $user->email;
            $customer->phone = $user->phone;
            $customer->save();

            $this->activityLogService->recordChanges(
                subject: $customer,
                originalValues: ['user_id' => null],
                allowedFields: ['user_id', 'name', 'email', 'phone'],
                subjectLabel: $customer->display_name,
                metadata: [
                    'action' => 'customer.portal_access_enabled',
                    'user_id' => $user->id,
                ],
            );

            return $customer->fresh('user');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pullLimitNotes(array &$data): ?string
    {
        $notes = $data['daily_request_limit_notes'] ?? null;
        unset($data['daily_request_limit_notes']);

        if (! is_string($notes)) {
            return null;
        }

        $trimmed = trim($notes);

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertEmailAvailableForNewUser(string $email, ?int $ignoreUserId = null): void
    {
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'The email field is required.',
            ]);
        }

        $query = User::query()->whereRaw('LOWER(email) = ?', [$email]);
        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered and cannot be used for a new customer login.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertIdentifiable(array $data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($name === '' && $phone === '' && $email === '') {
            throw ValidationException::withMessages([
                'name' => 'Provide a name, phone, or email.',
            ]);
        }
    }
}
