<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsletterSubscriptionRequest;
use App\Models\NewsletterSubscriber;
use App\Services\NewsletterSubscriberService;
use Illuminate\Http\RedirectResponse;

class PublicNewsletterController extends Controller
{
    public function __construct(public NewsletterSubscriberService $newsletterSubscriberService) {}

    public function store(StoreNewsletterSubscriptionRequest $request): RedirectResponse
    {
        $existing = NewsletterSubscriber::query()
            ->where('email', $request->validated('email'))
            ->first();

        $this->newsletterSubscriberService->subscribe(
            data: $request->safe()->only(['email']),
        );

        if ($existing && $existing->is_active) {
            return redirect()->back()->with('info', 'newsletter_already_subscribed');
        }

        return redirect()->back()->with('success', 'newsletter_subscribed');
    }
}
