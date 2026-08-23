<?php

namespace App\Http\Controllers;

use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\RequestMatch;
use App\Services\MerchantContextService;
use App\Services\MerchantPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MerchantMatchedRequestOpenController extends Controller
{
    public function __construct(
        public MerchantContextService $merchantContextService,
        public MerchantPermissionService $merchantPermissionService,
    ) {}

    public function __invoke(Request $request, Merchant $merchant, CustomerRequest $customerRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        try {
            $this->merchantContextService->assertCanActivate($user, $merchant);
        } catch (AccessDeniedHttpException) {
            abort(404);
        }

        $membership = $this->merchantContextService->activeMembership($user, $merchant);
        abort_unless($membership !== null, 404);

        if (! $this->merchantPermissionService->membershipCan($membership, PermissionKey::RequestsView->value)) {
            abort(404);
        }

        $match = RequestMatch::query()
            ->where('merchant_id', $merchant->id)
            ->where('customer_request_id', $customerRequest->id)
            ->first();

        abort_unless($match instanceof RequestMatch, 404);

        $this->merchantContextService->activateByPublicId($user, (string) $merchant->public_id, $request);

        return redirect()->route('merchant.requests.show', $customerRequest);
    }
}
