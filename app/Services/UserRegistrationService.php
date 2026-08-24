<?php

namespace App\Services;

use App\Enums\Users\Status as UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserRegistrationService
{
    public function __construct(
        public ReferralAttributionService $referralAttributionService,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = new User;
            $user->name = (string) $data['name'];
            $user->email = strtolower(trim((string) $data['email']));
            $user->phone = isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null;
            $user->password = (string) $data['password'];
            $user->status = UserStatus::Active;
            $user->save();

            $this->referralAttributionService->attributeNewUser($user);

            return $user;
        });
    }
}
