<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyInfoRequest;
use App\Models\CompanyInfo;
use App\Services\CompanyInfoService;
use Inertia\Inertia;

class CompanyInfoController extends Controller
{
    public function __construct(public CompanyInfoService $companyInfoService) {}

    public function index()
    {
        $this->authorizeAdmin('settings.update');

        return Inertia::render('CompanyInfo/CompanyInfoPage', [
            'companyInfo' => $this->companyInfoService->getCompanyInfo(),
        ]);
    }

    public function update(CompanyInfoRequest $request)
    {
        $companyInfo = CompanyInfo::with('attachment')->first();
        $this->companyInfoService->update(companyInfo: $companyInfo, data: $request->safe()->except('logo'), logo: $request->file('logo'));

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
