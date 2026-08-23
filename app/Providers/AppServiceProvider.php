<?php

namespace App\Providers;

use App\Contracts\AiClassificationProviderInterface;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\RequestClassification;
use App\Services\Classification\DeferredRemoteClassificationProvider;
use App\Services\Classification\FakeClassificationProvider;
use App\Services\Classification\OpenAIClassificationProvider;
use App\Services\MerchantPermissionService;
use App\Services\WebPush\SafeWebPushReportHandler;
use App\Support\CustomerContext;
use App\Support\MerchantAuthorization;
use App\Support\MerchantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\ReportHandlerInterface;

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
        $this->app->scoped(CustomerContext::class);
        $this->app->bind(ReportHandlerInterface::class, SafeWebPushReportHandler::class);
        $this->app->singleton(AiClassificationProviderInterface::class, function () {
            return match ((string) config('classification.provider')) {
                'fake' => new FakeClassificationProvider,
                'openai' => new OpenAIClassificationProvider,
                default => new DeferredRemoteClassificationProvider,
            };
        });
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

        RateLimiter::for('request-classification', function (Request $request) {
            return Limit::perMinute(8)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        Vite::prefetch(concurrency: 3);

        $this->app->bind(ReportHandlerInterface::class, SafeWebPushReportHandler::class);

        Route::model('membership', MerchantUser::class);
        Route::model('merchantCategory', MerchantCategory::class);
        Route::model('requestClassification', RequestClassification::class);
    }
}
