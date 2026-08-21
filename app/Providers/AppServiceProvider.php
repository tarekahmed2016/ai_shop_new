<?php

namespace App\Providers;

use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Services\MerchantPermissionService;
use App\Support\MerchantAuthorization;
use App\Support\MerchantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(MerchantContext::class);
        $this->app->scoped(MerchantAuthorization::class);
        $this->app->scoped(MerchantPermissionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('newsletter', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Vite::prefetch(concurrency: 3);

        Route::model('membership', MerchantUser::class);
        Route::model('merchantCategory', MerchantCategory::class);
    }
}
