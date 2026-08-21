<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectMerchantContextRequest;
use App\Services\MerchantContextService;
use App\Support\MerchantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MerchantContextController extends Controller
{
    public function __construct(
        public MerchantContextService $merchantContextService,
        public MerchantContext $merchantContext,
    ) {}

    public function select(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Merchants/MerchantSelectPage', [
            'availableMerchants' => $user
                ? $this->merchantContextService->availableMerchantsFor($user)
                : [],
        ]);
    }

    public function store(SelectMerchantContextRequest $request)
    {
        try {
            $this->merchantContextService->activateByPublicId(
                user: $request->user(),
                publicId: (string) $request->validated('public_id'),
                request: $request,
            );
        } catch (AccessDeniedHttpException) {
            abort(403);
        }

        return redirect()->route('merchant.home')->with('success', 'تم اختيار التاجر بنجاح');
    }

    public function home()
    {
        return Inertia::render('Merchants/MerchantHomePage', [
            'merchant' => $this->merchantContext->toArray(),
        ]);
    }
}
