<?php

namespace App\Services;

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\User;
use App\Support\CustomerContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPortalService
{
    public function __construct(
        public CustomerContext $customerContext,
        public CustomerRequestService $customerRequestService,
        public CategoryService $categoryService,
        public ActivityLogService $activityLogService,
    ) {}

    public function requireCustomer(): Customer
    {
        $customer = $this->customerContext->customer();

        if ($customer === null || ! $customer->isActive()) {
            abort(403);
        }

        return $customer;
    }

    /**
     * @return array{total: int, ready: int, new_or_open: int, closed: int}
     */
    public function dashboardStats(Customer $customer): array
    {
        $base = CustomerRequest::query()->where('customer_id', $customer->id);

        return [
            'total' => (clone $base)->count(),
            'ready' => (clone $base)->where('status', RequestStatus::Ready)->count(),
            'new_or_open' => (clone $base)->whereIn('status', [
                RequestStatus::New,
                RequestStatus::PendingClassification,
                RequestStatus::Ready,
            ])->count(),
            'closed' => (clone $base)->whereIn('status', [
                RequestStatus::Closed,
                RequestStatus::Cancelled,
            ])->count(),
        ];
    }

    /**
     * @return list<CustomerRequest>
     */
    public function recentRequests(Customer $customer, int $limit = 5): array
    {
        return CustomerRequest::query()
            ->where('customer_id', $customer->id)
            ->with(['category:id,public_id,name_ar,name_en', 'image'])
            ->latest()
            ->limit($limit)
            ->get()
            ->all();
    }

    public function getPaginatedOwnRequests(
        Customer $customer,
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['created_at', 'status', 'id'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return CustomerRequest::query()
            ->where('customer_id', $customer->id)
            ->with(['category:id,public_id,name_ar,name_en', 'image'])
            ->withCount('submittedOffers')
            ->when($search, fn ($q) => $q->where('request_text', 'like', "%{$search}%"))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOwnRequestOrFail(Customer $customer, CustomerRequest $customerRequest): CustomerRequest
    {
        if ((int) $customerRequest->customer_id !== (int) $customer->id) {
            abort(404);
        }

        return $customerRequest->load(['category:id,public_id,name_ar,name_en', 'image']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRequest(Customer $customer, array $data, ?UploadedFile $image = null): CustomerRequest
    {
        return $this->customerRequestService->storeForCustomer($customer, $data, $image);
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $data
     */
    public function updateProfile(Customer $customer, array $data): Customer
    {
        $user = $customer->user;

        if ($user === null) {
            abort(403);
        }

        return DB::transaction(function () use ($customer, $user, $data) {
            $email = strtolower(trim((string) $data['email']));

            $emailTaken = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($emailTaken) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already registered.',
                ]);
            }

            $user->name = (string) $data['name'];
            $user->email = $email;
            $user->phone = $data['phone'] ?? null;

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            $customer->name = $user->name;
            $customer->email = $user->email;
            $customer->phone = $user->phone;
            $customer->save();

            return $customer->fresh('user');
        });
    }
}
