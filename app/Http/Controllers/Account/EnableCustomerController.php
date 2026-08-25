<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\CustomerService;
use App\Support\CustomerContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnableCustomerController extends Controller
{
    public function __construct(
        public CustomerService $customerService,
        public CustomerContext $customerContext,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = admin_dashboard_redirect()) {
            return $redirect;
        }

        $user = $request->user();
        $customer = $user?->customer;

        if ($customer?->isActive()) {
            return redirect()->intended(route('customer.requests.create'));
        }

        if ($customer !== null && ! $customer->isActive()) {
            abort(403);
        }

        return Inertia::render('Account/EnableCustomerPage');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = admin_dashboard_redirect()) {
            return $redirect;
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        $customer = $this->customerService->ensureForUser($user);

        if (! $customer->isActive()) {
            abort(403);
        }

        $this->customerContext->set($customer);

        return redirect()->intended(route('customer.requests.create'));
    }
}
