<?php

namespace App\Http\Middleware;

use App\Services\CompanyInfoService;
use App\Services\PageService;
use App\Services\PublicHomeService;
use App\Services\PublicNavService;
use App\Support\ThemeColor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => Inertia::always([
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ]),
            'auth' => [
                'user' => $request->user(),
                'role' => $request->user()?->getRoleNames()->first(),
                'isAdmin' => (bool) $request->user()?->hasRole('admin'),
            ],
            'companyInfo' => fn () => tap(
                app(CompanyInfoService::class)->getCompanyInfo(),
                function ($companyInfo) {
                    foreach (ThemeColor::resolvedFor($companyInfo) as $field => $value) {
                        $companyInfo->{$field} = $value;
                    }

                    $companyInfo->logo = $companyInfo->attachment?->asset_path;
                },
            ),
            'businessCta' => fn () => app(PublicHomeService::class)->getActiveBusinessCta(),
            'menuPages' => fn () => app(PageService::class)->getPublicMenuPages(),
            'publicNavContext' => fn () => app(PublicNavService::class)->getContext(),
        ];
    }
}
