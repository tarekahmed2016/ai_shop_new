<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\UserRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        public UserRegistrationService $userRegistrationService,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/RegisterPage');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = $this->userRegistrationService->register($request->safe()->only(['name', 'email', 'phone', 'password']));

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('account.get-started');
    }
}
