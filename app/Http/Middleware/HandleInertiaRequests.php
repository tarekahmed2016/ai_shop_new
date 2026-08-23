<?php

namespace App\Http\Middleware;

use App\Services\CompanyInfoService;
use App\Services\MerchantContextService;
use App\Services\PageService;
use App\Services\PublicHomeService;
use App\Services\PublicNavService;
use App\Support\CustomerContext;
use App\Support\MerchantContext;
use App\Support\ThemeColor;
use App\Support\UserCapabilities;
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
        if ($request->user()) {
            app(MerchantContextService::class)->establishFromSession($request->user(), $request);
            app(CustomerContext::class)->resolveFromUser($request->user());
        }

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
                'isCustomer' => (bool) $request->user()?->customer,
                'capabilities' => $request->user() ? UserCapabilities::for($request->user()) : null,
            ],
            'merchantContext' => fn () => app(MerchantContext::class)->toArray(),
            'customerContext' => fn () => app(CustomerContext::class)->toArray(),
            'availableMerchants' => fn () => $request->user()
                ? app(MerchantContextService::class)->availableMerchantsFor($request->user())
                : [],
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
            'webPush' => fn () => $this->webPushShare($request),
        ];
    }

    /**
     * @return array{vapid_public_key: string, enabled: bool}|null
     */
    private function webPushShare(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $merchantActive = app(MerchantContext::class)->isActive();
        $customerActive = app(CustomerContext::class)->isActive();

        if (! $merchantActive && ! $customerActive) {
            return null;
        }

        $publicKey = (string) config('webpush.vapid.public_key', '');

        return [
            'vapid_public_key' => $publicKey,
            'enabled' => $publicKey !== '',
        ];
    }
}
