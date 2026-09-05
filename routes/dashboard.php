<?php

use App\Http\Controllers\Account\EnableCustomerController;
use App\Http\Controllers\Account\GetStartedController;
use App\Http\Controllers\Account\MarketerApplicationController;
use App\Http\Controllers\Account\StartMerchantController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CertificateAwardController;
use App\Http\Controllers\ClientPartnerController;
use App\Http\Controllers\CompanyInfoController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\CustomAssetsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerExtraRequestController;
use App\Http\Controllers\CustomerRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HeroSlideController;
use App\Http\Controllers\HomepagePromoBlockController;
use App\Http\Controllers\MarketerCommissionController;
use App\Http\Controllers\MarketerController;
use App\Http\Controllers\MarketerPortal\MarketerPortalController;
use App\Http\Controllers\MerchantBusinessActivityController;
use App\Http\Controllers\MerchantBusinessProfileController;
use App\Http\Controllers\MerchantCategoryController;
use App\Http\Controllers\MerchantContextController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\MerchantMatchedRequestOpenController;
use App\Http\Controllers\MerchantMembershipController;
use App\Http\Controllers\MerchantOfferCreditController;
use App\Http\Controllers\MerchantPushSubscriptionController;
use App\Http\Controllers\MerchantRequestController;
use App\Http\Controllers\MerchantTeamController;
use App\Http\Controllers\NewsletterSubscriberController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RequestMatchController;
use App\Http\Controllers\RichTextImageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\ThemeColorsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/account/get-started', GetStartedController::class)->name('account.get-started');
    Route::get('/account/customer/enable', [EnableCustomerController::class, 'create'])->name('account.customer.enable');
    Route::post('/account/customer/enable', [EnableCustomerController::class, 'store'])->name('account.customer.enable.store');
    Route::get('/account/merchant/start', [StartMerchantController::class, 'create'])->name('account.merchant.start');
    Route::post('/account/merchant/start', [StartMerchantController::class, 'store'])->name('account.merchant.start.store');
    Route::get('/account/marketer/apply', [MarketerApplicationController::class, 'create'])->name('marketer.application.create');
    Route::post('/account/marketer/apply', [MarketerApplicationController::class, 'store'])->name('marketer.application.store');
    Route::get('/account/marketer/status', [MarketerApplicationController::class, 'status'])->name('marketer.application.status');
    Route::post('/account/marketer/reapply', [MarketerApplicationController::class, 'reapply'])->name('marketer.application.reapply');
    Route::get('/merchant/select', [MerchantContextController::class, 'select'])->name('merchant.select');
    Route::post('/merchant/context', [MerchantContextController::class, 'store'])->name('merchant.context.store');
    Route::get('/merchant/open-request/{merchant}/{customerRequest}', MerchantMatchedRequestOpenController::class)
        ->name('merchant.requests.open');
});

Route::middleware(['merchant'])->group(function () {
    Route::get('/merchant', [MerchantContextController::class, 'home'])->name('merchant.home');
    Route::get('/merchant/push-subscriptions/config', [MerchantPushSubscriptionController::class, 'config'])
        ->name('merchant.push-subscriptions.config');
    Route::post('/merchant/push-subscriptions', [MerchantPushSubscriptionController::class, 'store'])
        ->name('merchant.push-subscriptions.store');
    Route::delete('/merchant/push-subscriptions', [MerchantPushSubscriptionController::class, 'destroy'])
        ->name('merchant.push-subscriptions.destroy');
    Route::get('/merchant/requests', [MerchantRequestController::class, 'index'])->name('merchant.requests.index');
    Route::get('/merchant/requests/{customerRequest}', [MerchantRequestController::class, 'show'])->name('merchant.requests.show');
    Route::get('/merchant/requests/{customerRequest}/image', [MerchantRequestController::class, 'image'])->name('merchant.requests.image');
    Route::post('/merchant/requests/{customerRequest}/dismiss', [MerchantRequestController::class, 'dismiss'])->name('merchant.requests.dismiss');
    Route::post('/merchant/requests/{customerRequest}/offers', [MerchantRequestController::class, 'storeOffer'])->name('merchant.requests.offers.store');
    Route::post('/merchant/requests/{customerRequest}/offers/update', [MerchantRequestController::class, 'updateOffer'])->name('merchant.requests.offers.update');
    Route::post('/merchant/requests/{customerRequest}/offers/withdraw', [MerchantRequestController::class, 'withdrawOffer'])->name('merchant.requests.offers.withdraw');
    Route::get('/merchant/offers/{merchantOffer}/images/{offerImage}', [MerchantRequestController::class, 'offerImage'])->name('merchant.offers.images.show');

    Route::get('/merchant/activities', [MerchantBusinessActivityController::class, 'index'])->name('merchant.activities.index');
    Route::post('/merchant/activities', [MerchantBusinessActivityController::class, 'store'])->name('merchant.activities.store');
    Route::patch('/merchant/activities/{merchantCategory}', [MerchantBusinessActivityController::class, 'update'])->name('merchant.activities.update');
    Route::delete('/merchant/activities/{merchantCategory}', [MerchantBusinessActivityController::class, 'destroy'])->name('merchant.activities.destroy');

    Route::get('/merchant/team', [MerchantTeamController::class, 'index'])->name('merchant.team.index');
    Route::post('/merchant/team', [MerchantTeamController::class, 'store'])
        ->middleware('throttle:merchant-team-write')
        ->name('merchant.team.store');
    Route::patch('/merchant/team/{membership}', [MerchantTeamController::class, 'update'])->name('merchant.team.update');
    Route::delete('/merchant/team/{membership}', [MerchantTeamController::class, 'destroy'])->name('merchant.team.destroy');
    Route::get('/merchant/business-profile', [MerchantBusinessProfileController::class, 'edit'])->name('merchant.business-profile.edit');
    Route::patch('/merchant/business-profile', [MerchantBusinessProfileController::class, 'update'])->name('merchant.business-profile.update');
});

Route::middleware(['marketer'])->group(function () {
    Route::get('/marketer', [MarketerPortalController::class, 'home'])->name('marketer.home');
    Route::get('/marketer/referrals', [MarketerPortalController::class, 'referrals'])->name('marketer.referrals');
    Route::get('/marketer/payments', [MarketerPortalController::class, 'payments'])->name('marketer.payments');
    Route::get('/marketer/commissions', [MarketerPortalController::class, 'commissions'])->name('marketer.commissions');
    Route::get('/marketer/payouts', [MarketerPortalController::class, 'payouts'])->name('marketer.payouts');
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
    Route::resource('/merchants', MerchantController::class)->except(['show', 'create', 'edit', 'destroy']);
    Route::post('/merchants/credits/bulk', [MerchantOfferCreditController::class, 'bulkStore'])->name('merchants.credits.bulk');
    Route::put('/merchants/credits/enforcement', [MerchantOfferCreditController::class, 'updateEnforcement'])->name('merchants.credits.enforcement');
    Route::get('/merchants/credits/transactions', [MerchantOfferCreditController::class, 'transactions'])->name('merchants.credits.transactions');
    Route::get('/merchants/{merchant}/credits', [MerchantOfferCreditController::class, 'index'])->name('merchants.credits.index');
    Route::post('/merchants/{merchant}/credits', [MerchantOfferCreditController::class, 'store'])->name('merchants.credits.store');
    Route::post('/merchants/{merchant}/credits/deduct', [MerchantOfferCreditController::class, 'deduct'])->name('merchants.credits.deduct');
    Route::get('/merchants/{merchant}/categories', [MerchantCategoryController::class, 'index'])->name('merchants.categories.index');
    Route::post('/merchants/{merchant}/categories', [MerchantCategoryController::class, 'store'])->name('merchants.categories.store');
    Route::patch('/merchants/{merchant}/categories/{merchantCategory}', [MerchantCategoryController::class, 'update'])->name('merchants.categories.update');
    Route::delete('/merchants/{merchant}/categories/{merchantCategory}', [MerchantCategoryController::class, 'destroy'])->name('merchants.categories.destroy');
    Route::resource('/categories', CategoryController::class)->except(['show', 'create', 'edit', 'destroy']);
    Route::get('/payments', [PaymentTransactionController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [PaymentTransactionController::class, 'show'])->name('payments.show');
    Route::get('/marketer-commissions', [MarketerCommissionController::class, 'index'])->name('marketer-commissions.index');
    Route::put('/marketer-commissions/settings', [MarketerCommissionController::class, 'updateSettings'])->name('marketer-commissions.settings');
    Route::put('/customers/settings/daily-request-limit', [CustomerController::class, 'updateDailyLimit'])->name('customers.settings.daily-request-limit');
    Route::get('/customers/{customer}/daily-limit-history', [CustomerController::class, 'dailyLimitHistory'])->name('customers.daily-limit-history');
    Route::get('/customers/{customer}/extra-requests', [CustomerExtraRequestController::class, 'index'])->name('customers.extra-requests.index');
    Route::post('/customers/{customer}/extra-requests', [CustomerExtraRequestController::class, 'store'])->name('customers.extra-requests.store');
    Route::post('/customers/{customer}/extra-requests/deduct', [CustomerExtraRequestController::class, 'deduct'])->name('customers.extra-requests.deduct');
    Route::post('/customers/extra-requests/bulk', [CustomerExtraRequestController::class, 'bulkStore'])->name('customers.extra-requests.bulk');
    Route::resource('/customers', CustomerController::class)->except(['show', 'create', 'edit', 'destroy']);
    Route::get('/marketers', [MarketerController::class, 'index'])->name('marketers.index');
    Route::post('/marketers', [MarketerController::class, 'store'])->name('marketers.store');
    Route::get('/marketers/{marketer}', [MarketerController::class, 'show'])->name('marketers.show');
    Route::get('/marketers/{marketer}/commissions', [MarketerController::class, 'commissions'])->name('marketers.commissions');
    Route::get('/marketers/{marketer}/payouts', [MarketerController::class, 'payouts'])->name('marketers.payouts');
    Route::post('/marketers/{marketer}/payouts', [MarketerController::class, 'storePayout'])->name('marketers.payouts.store');
    Route::put('/marketers/{marketer}/commission-rates', [MarketerController::class, 'updateRates'])->name('marketers.commission-rates.update');
    Route::post('/marketers/{marketer}/approve', [MarketerController::class, 'approve'])->name('marketers.approve');
    Route::post('/marketers/{marketer}/reject', [MarketerController::class, 'reject'])->name('marketers.reject');
    Route::post('/marketers/{marketer}/deactivate', [MarketerController::class, 'deactivate'])->name('marketers.deactivate');
    Route::post('/marketers/{marketer}/reactivate', [MarketerController::class, 'reactivate'])->name('marketers.reactivate');
    Route::post('/customers/{customer}/portal-access', [CustomerController::class, 'enablePortal'])->name('customers.portal-access');
    Route::post('/customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])->name('customers.reactivate');
    Route::get('/customer-requests/{customerRequest}/image', [CustomerRequestController::class, 'image'])->name('customer-requests.image');
    Route::get('/customer-requests/{customerRequest}/offers/{merchantOffer}/images/{offerImage}', [CustomerRequestController::class, 'offerImage'])->name('customer-requests.offers.images.show');
    Route::post('/customer-requests/{customerRequest}/match', [RequestMatchController::class, 'sync'])->name('customer-requests.match');
    Route::resource('/customer-requests', CustomerRequestController::class)
        ->except(['show', 'create', 'edit', 'destroy'])
        ->parameters(['customer-requests' => 'customerRequest']);
    Route::get('/matching', [RequestMatchController::class, 'index'])->name('matching.index');
    Route::get('/merchants/{merchant}/memberships', [MerchantMembershipController::class, 'index'])->name('merchants.memberships.index');
    Route::post('/merchants/{merchant}/memberships', [MerchantMembershipController::class, 'store'])->name('merchants.memberships.store');
    Route::put('/merchants/{merchant}/memberships/{membership}', [MerchantMembershipController::class, 'update'])->name('merchants.memberships.update');
    Route::delete('/merchants/{merchant}/memberships/{membership}', [MerchantMembershipController::class, 'destroy'])->name('merchants.memberships.destroy');
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
