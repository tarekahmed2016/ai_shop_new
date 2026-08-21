<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRegisterRequest;
use App\Services\CustomerRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CustomerRegisterController extends Controller
{
    public function __construct(
        public CustomerRegistrationService $customerRegistrationService,
    ) {}

    public function create(): Response
    {
        return Inertia::render('CustomerPortal/RegisterPage');
    }

    public function store(CustomerRegisterRequest $request): RedirectResponse
    {
        $result = $this->customerRegistrationService->register($request->validated());

        event(new Registered($result['user']));

        Auth::login($result['user']);

        $request->session()->regenerate();

        return redirect()->route('customer.home')->with('success', 'تم إنشاء الحساب بنجاح');
    }
}
