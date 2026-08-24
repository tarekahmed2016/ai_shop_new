<?php

namespace App\Http\Controllers\MarketerPortal;

use App\Http\Controllers\Controller;
use App\Services\MarketerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketerPortalController extends Controller
{
    public function __construct(
        public MarketerService $marketerService,
    ) {}

    public function home(Request $request): Response
    {
        $marketer = $request->user()?->marketer;
        abort_unless($marketer !== null && $marketer->isActive(), 403);

        return Inertia::render('MarketerPortal/HomePage', [
            'metrics' => $this->marketerService->dashboardMetrics($marketer),
        ]);
    }

    public function referrals(Request $request): Response
    {
        $marketer = $request->user()?->marketer;
        abort_unless($marketer !== null && $marketer->isActive(), 403);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'registered_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('MarketerPortal/ReferralsPage', [
            'referrals' => $this->marketerService->getOwnPaginatedReferrals(
                marketer: $marketer,
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
            ),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }
}
