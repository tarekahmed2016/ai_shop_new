<?php

use App\Http\Controllers\CustomerPortal\CustomerPortalController;
use App\Http\Controllers\CustomerPortal\CustomerPushSubscriptionController;
use App\Http\Controllers\CustomerPortal\CustomerRegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/customer/register', [CustomerRegisterController::class, 'create'])->name('customer.register');
    Route::post('/customer/register', [CustomerRegisterController::class, 'store'])->name('customer.register.store');
});

Route::middleware(['customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/', [CustomerPortalController::class, 'home'])->name('home');
    Route::get('/requests', [CustomerPortalController::class, 'requestsIndex'])->name('requests.index');
    Route::get('/requests/create', [CustomerPortalController::class, 'requestsCreate'])->name('requests.create');
    Route::post('/requests', [CustomerPortalController::class, 'requestsStore'])->name('requests.store');
    Route::post('/requests/classify', [CustomerPortalController::class, 'requestsClassify'])
        ->middleware('throttle:request-classification')
        ->name('requests.classify');
    Route::post('/requests/classifications/{requestClassification}/confirm', [CustomerPortalController::class, 'requestsClassificationConfirm'])
        ->name('requests.classifications.confirm');
    Route::post('/requests/{customerRequest}/classify', [CustomerPortalController::class, 'requestsRetryClassification'])
        ->middleware('throttle:request-classification')
        ->name('requests.classify.resume');
    Route::post('/requests/{customerRequest}/category', [CustomerPortalController::class, 'requestsFinalizeCategory'])
        ->name('requests.category');
    Route::get('/requests/{customerRequest}', [CustomerPortalController::class, 'requestsShow'])->name('requests.show');
    Route::get('/requests/{customerRequest}/image', [CustomerPortalController::class, 'requestsImage'])->name('requests.image');
    Route::get('/push-subscriptions/config', [CustomerPushSubscriptionController::class, 'config'])
        ->name('push-subscriptions.config');
    Route::post('/push-subscriptions', [CustomerPushSubscriptionController::class, 'store'])
        ->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [CustomerPushSubscriptionController::class, 'destroy'])
        ->name('push-subscriptions.destroy');
    Route::get('/offers/{merchantOffer}/images/{offerImage}', [CustomerPortalController::class, 'offerImage'])->name('offers.images.show');
    Route::post('/offers/{merchantOffer}/contact-reveal', [CustomerPortalController::class, 'offerContactReveal'])->name('offers.contact-reveal');
    Route::get('/profile', [CustomerPortalController::class, 'profileEdit'])->name('profile.edit');
    Route::patch('/profile', [CustomerPortalController::class, 'profileUpdate'])->name('profile.update');
});
