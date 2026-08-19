<?php

use App\Http\Controllers\CertificateAwardController;
use App\Http\Controllers\ClientPartnerController;
use App\Http\Controllers\CompanyInfoController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\CustomAssetsController;
use App\Http\Controllers\HeroSlideController;
use App\Http\Controllers\HomepagePromoBlockController;
use App\Http\Controllers\NewsletterSubscriberController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RichTextImageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\ThemeColorsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::inertia('/dashboard', 'Dashboard/IndexPage')->name('dashboard');
});

Route::middleware(['admin'])->group(function () {
    Route::get('/company-info', [CompanyInfoController::class, 'index'])->name('company-info.index');
    Route::put('/company-info', [CompanyInfoController::class, 'update'])->name('company-info.update');
    Route::get('/theme-colors', [ThemeColorsController::class, 'index'])->name('theme-colors.index');
    Route::put('/theme-colors', [ThemeColorsController::class, 'update'])->name('theme-colors.update');
    Route::get('/custom-assets', [CustomAssetsController::class, 'index'])->name('custom-assets.index');
    Route::put('/custom-assets', [CustomAssetsController::class, 'update'])->name('custom-assets.update');
    Route::resource('/users', UserController::class)->except(['show', 'create', 'edit']);
    Route::resource('/roles', RoleController::class)->except(['show', 'create', 'edit']);
    Route::resource('/services', ServiceController::class)->except(['show', 'create', 'edit']);
    Route::get('/services-next-ordering', [ServiceController::class, 'getNextOrdering'])->name('services.next-ordering');
    Route::resource('/pages', PageController::class)->except(['show', 'create', 'edit']);
    Route::post('/rich-text/images', [RichTextImageController::class, 'store'])->name('rich-text-images.store');
    Route::resource('/projects', ProjectController::class)->except(['show', 'create', 'edit']);
    Route::get('/projects-next-ordering', [ProjectController::class, 'getNextOrdering'])->name('projects.next-ordering');
    Route::resource('/team-members', TeamMemberController::class)->except(['show', 'create', 'edit']);
    Route::get('/team-members-next-ordering', [TeamMemberController::class, 'getNextOrdering'])->name('team-members.next-ordering');
    Route::resource('/clients-partners', ClientPartnerController::class)->except(['show', 'create', 'edit']);
    Route::get('/clients-partners-next-ordering', [ClientPartnerController::class, 'getNextOrdering'])->name('clients-partners.next-ordering');
    Route::resource('/certificates-awards', CertificateAwardController::class)->except(['show', 'create', 'edit']);
    Route::get('/certificates-awards-next-ordering', [CertificateAwardController::class, 'getNextOrdering'])->name('certificates-awards.next-ordering');
    Route::get('/contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::put('/contact-messages/{contactMessage}/read', [ContactMessageController::class, 'markAsRead'])->name('contact-messages.read');
    Route::put('/contact-messages/{contactMessage}/unread', [ContactMessageController::class, 'markAsUnread'])->name('contact-messages.unread');
    Route::delete('/contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
    Route::resource('/hero-slides', HeroSlideController::class)->except(['show', 'create', 'edit']);
    Route::get('/hero-slides-next-ordering', [HeroSlideController::class, 'getNextOrdering'])->name('hero-slides.next-ordering');
    Route::resource('/homepage-promos', HomepagePromoBlockController::class)->except(['show', 'create', 'edit']);
    Route::get('/homepage-promos-next-ordering', [HomepagePromoBlockController::class, 'getNextOrdering'])->name('homepage-promos.next-ordering');
    Route::get('/newsletter-subscribers', [NewsletterSubscriberController::class, 'index'])->name('newsletter-subscribers.index');
    Route::delete('/newsletter-subscribers/{newsletterSubscriber}', [NewsletterSubscriberController::class, 'destroy'])->name('newsletter-subscribers.destroy');
    Route::put('/newsletter-subscribers/{newsletterSubscriber}/unsubscribe', [NewsletterSubscriberController::class, 'unsubscribe'])->name('newsletter-subscribers.unsubscribe');
});
