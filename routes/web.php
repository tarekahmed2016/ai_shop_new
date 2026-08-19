<?php

use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicNewsletterController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)->name('home');
Route::get('/page/{slug}', [PublicPageController::class, 'show'])->name('public.page.show');
Route::post('/contact', [PublicContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');
Route::post('/newsletter', [PublicNewsletterController::class, 'store'])
    ->middleware('throttle:newsletter')
    ->name('newsletter.store');

require __DIR__.'/auth.php';
