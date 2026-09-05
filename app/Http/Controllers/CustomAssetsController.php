<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomAssetsRequest;
use App\Models\CompanyInfo;
use App\Services\CompanyInfoService;
use Inertia\Inertia;

class CustomAssetsController extends Controller
{
    public function __construct(public CompanyInfoService $companyInfoService) {}

    public function index()
    {
        $this->authorizeAdmin('settings.update');

        return Inertia::render('CustomAssets/CustomAssetsPage', [
            'customAssets' => $this->companyInfoService->getCustomAssets(),
        ]);
    }

    public function update(CustomAssetsRequest $request)
    {
        $companyInfo = CompanyInfo::first();
        $this->companyInfoService->updateCustomAssets(
            companyInfo: $companyInfo,
            data: $request->validated(),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
