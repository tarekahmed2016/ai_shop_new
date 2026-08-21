<?php

namespace App\Http\Middleware;

use App\Services\MerchantContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantContext
{
    public function __construct(
        public MerchantContextService $merchantContextService,
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

        if (! $this->merchantContextService->establishFromSession($user, $request)) {
            return redirect()->route('merchant.select');
        }

        return $next($request);
    }
}
