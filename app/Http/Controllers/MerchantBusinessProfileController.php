<?php

namespace App\Http\Controllers;

use App\Http\Requests\MerchantBusinessProfileUpdateRequest;
use App\Models\Merchant;
use App\Services\MerchantService;
use App\Support\MerchantAuthorization;
use App\Support\MerchantContext;
use Inertia\Inertia;
use Inertia\Response;

class MerchantBusinessProfileController extends Controller
{
    public function __construct(
        public MerchantContext $merchantContext,
        public MerchantAuthorization $merchantAuthorization,
        public MerchantService $merchantService,
    ) {}

    public function edit(): Response
    {
        abort_unless($this->merchantAuthorization->canViewBusinessProfile(), 403);

        /** @var Merchant $merchant */
        $merchant = $this->merchantContext->merchant();

        return Inertia::render('Merchants/MerchantBusinessProfilePage', [
            'merchant' => [
                'public_id' => $merchant->public_id,
                'name' => $merchant->name,
                'email' => $merchant->email,
                'phone' => $merchant->phone,
            ],
            'canUpdate' => $this->merchantAuthorization->canUpdateBusinessProfile(),
        ]);
    }

    public function update(MerchantBusinessProfileUpdateRequest $request)
    {
        /** @var Merchant $merchant */
        $merchant = $this->merchantContext->merchant();

        $this->merchantService->updateBusinessProfile($merchant, $request->validated());

        return redirect()
            ->route('merchant.business-profile.edit')
            ->with('success', 'تم تحديث بيانات المنشأة بنجاح');
    }
}
