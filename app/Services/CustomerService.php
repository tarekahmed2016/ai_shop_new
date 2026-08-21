<?php

namespace App\Services;

use App\Enums\Customers\Status;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'name',
        'phone',
        'whatsapp_id',
        'email',
        'status',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
    ) {}

    public function getPaginatedCustomers(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'name', 'phone', 'email', 'status', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return Customer::query()
            ->withCount('requests')
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('whatsapp_id', 'like', "%{$search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
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
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Customer
    {
        $this->assertIdentifiable($data);

        $customer = new Customer;
        $customer->public_id = (string) Str::ulid();
        $customer->fill($data);
        $customer->save();

        $this->activityLogService->recordCreated(
            subject: $customer,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $customer->display_name,
        );

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $this->assertIdentifiable($data);

        $originalValues = $customer->only(self::ACTIVITY_FIELDS);

        $customer->update($data);

        $this->activityLogService->recordChanges(
            subject: $customer,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $customer->display_name,
        );

        return $customer;
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
