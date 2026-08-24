<?php

use App\Http\Middleware\CaptureReferralCode;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsureMarketer;
use App\Http\Middleware\EnsureMerchantContext;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/dashboard.php'));
            Route::middleware('web')
                ->group(base_path('routes/customer.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CaptureReferralCode::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->redirectUsersTo(fn () => target());
        $middleware->appendToGroup('admin', [
            Authenticate::class,
            CheckAdmin::class,
        ]);
        $middleware->appendToGroup('merchant', [
            Authenticate::class,
            EnsureMerchantContext::class,
        ]);
        $middleware->appendToGroup('customer', [
            Authenticate::class,
            EnsureCustomer::class,
        ]);
        $middleware->appendToGroup('marketer', [
            Authenticate::class,
            EnsureMarketer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*'),
        );
    })->create();
