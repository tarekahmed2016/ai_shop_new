<?php

namespace App\Http\Middleware;

use App\Services\ReferralAttributionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferralCode
{
    public function __construct(
        public ReferralAttributionService $referralAttributionService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->referralAttributionService->captureFromRequest($request);

        return $next($request);
    }
}
