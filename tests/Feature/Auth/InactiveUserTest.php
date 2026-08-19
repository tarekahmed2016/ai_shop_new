<?php

use App\Enums\Users\Status;
use App\Models\User;

test('inactive users cannot authenticate', function () {
    $user = User::factory()->create([
        'email' => 'inactive@example.com',
        'status' => Status::Inactive,
    ]);

    $this->post('/login', [
        'email' => 'inactive@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('active users can authenticate', function () {
    $user = User::factory()->create([
        'email' => 'active@example.com',
        'status' => Status::Active,
    ]);

    $this->post('/login', [
        'email' => 'active@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});
