<?php

namespace Database\Factories;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantPermissionService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantUser>
 */
class MerchantUserFactory extends Factory
{
    protected $model = MerchantUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'user_id' => User::factory(),
            'role' => Role::Staff,
            'status' => Status::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MerchantUser $membership): void {
            app(MerchantPermissionService::class)->assignRoleDefaults($membership, onlyIfEmpty: true);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Status::Inactive]);
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => Role::Owner]);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => Role::Manager]);
    }
}
