<?php

namespace App\Http\Controllers;

use App\Http\Requests\ThemeColorsRequest;
use App\Models\CompanyInfo;
use App\Services\CompanyInfoService;
use Inertia\Inertia;

class ThemeColorsController extends Controller
{
    public function __construct(public CompanyInfoService $companyInfoService) {}

    public function index()
    {
        $this->authorizeAdmin('settings.update');

        return Inertia::render('ThemeColors/ThemeColorsPage', [
            'themeColors' => $this->companyInfoService->getThemeColors(),
        ]);
    }

    public function update(ThemeColorsRequest $request)
    {
        $companyInfo = CompanyInfo::first();
        $this->companyInfoService->updateThemeColors(
            companyInfo: $companyInfo,
            data: $request->validated(),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
