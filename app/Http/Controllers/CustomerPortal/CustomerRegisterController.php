<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Legacy /customer/register compatibility.
 *
 * GET redirects to canonical /register (no competing Customer signup page).
 * POST uses UserRegistrationService via RegisteredUserController: User only,
 * then account.get-started. Customer capability is never created here.
 */
class CustomerRegisterController extends Controller
{
    public function __construct(
        public RegisteredUserController $registeredUserController,
    ) {}

    public function create(): RedirectResponse
    {
        return redirect()->route('register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        return $this->registeredUserController->store($request);
    }
}
