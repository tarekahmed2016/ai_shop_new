<?php

namespace App\Services;

use App\Enums\Customers\Status as CustomerStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerRegistrationService
{
    public function __construct(
        public ActivityLogService $activityLogService,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string}  $data
     * @return array{user: User, customer: Customer}
     */
    public function register(array $data): array
    {
        $email = strtolower(trim((string) $data['email']));

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.',
            ]);
        }

        return DB::transaction(function () use ($data, $email) {
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
            $customer->status = CustomerStatus::Active;
            $customer->save();

            $this->activityLogService->recordCreated(
                subject: $customer,
                allowedFields: ['name', 'email', 'phone', 'status', 'user_id'],
                subjectLabel: $customer->display_name,
                metadata: [
                    'action' => 'customer.account_created',
                    'user_id' => $user->id,
                ],
                actor: $user,
            );

            return [
                'user' => $user,
                'customer' => $customer,
            ];
        });
    }
}
