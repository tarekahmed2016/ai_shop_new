<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('dashboard and merchants remain accessible after sidebar refactor', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard/IndexPage', false));

    $this->actingAs($this->admin)
        ->get(route('merchants.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('categories.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('customers.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('customer-requests.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('matching.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('company-info.index'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('roles.index'))
        ->assertOk();
});
