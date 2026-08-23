<?php

namespace App\Http\Middleware;

use App\Support\CustomerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomer
{
    public function __construct(
        public CustomerContext $customerContext,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $customer = $this->customerContext->resolveFromUser($user);

        if ($customer === null) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->guest(route('account.customer.enable'));
        }

        if (! $customer->isActive()) {
            abort(403);
        }

        return $next($request);
    }
}
