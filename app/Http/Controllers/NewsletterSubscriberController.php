<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Services\NewsletterSubscriberService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterSubscriberController extends Controller
{
    public function __construct(public NewsletterSubscriberService $newsletterSubscriberService) {}

    public function index(Request $request): Response
    {
        $search = (string) $request->input('search', '');
        $sortBy = in_array($request->input('sort_column'), ['id', 'email', 'created_at']) ? $request->input('sort_column') : 'created_at';
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $newsletterSubscribers = $this->newsletterSubscriberService->getPaginatedSubscribers(
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );

        return Inertia::render('NewsletterSubscribers/NewsletterSubscribersPage', [
            'newsletterSubscribers' => $newsletterSubscribers,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function destroy(NewsletterSubscriber $newsletterSubscriber)
    {
        $this->newsletterSubscriberService->delete(subscriber: $newsletterSubscriber);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }

    public function unsubscribe(NewsletterSubscriber $newsletterSubscriber)
    {
        $this->newsletterSubscriberService->unsubscribe(subscriber: $newsletterSubscriber);

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
