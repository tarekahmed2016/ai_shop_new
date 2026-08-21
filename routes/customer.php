<?php

use App\Http\Controllers\CustomerPortal\CustomerPortalController;
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
    Route::get('/requests/{customerRequest}', [CustomerPortalController::class, 'requestsShow'])->name('requests.show');
    Route::get('/requests/{customerRequest}/image', [CustomerPortalController::class, 'requestsImage'])->name('requests.image');
    Route::get('/profile', [CustomerPortalController::class, 'profileEdit'])->name('profile.edit');
    Route::patch('/profile', [CustomerPortalController::class, 'profileUpdate'])->name('profile.update');
});
