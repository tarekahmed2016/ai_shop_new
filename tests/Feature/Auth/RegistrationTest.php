<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('registration screen can be rendered', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/RegisterPage', false));
});

test('new users can register', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ])->assertRedirect(route('account.get-started'));

    $this->assertAuthenticated();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeTrue();
});
