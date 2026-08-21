<?php

namespace App\Http\Controllers;

use App\Enums\RequestMatches\Status as MatchStatus;
use App\Http\Requests\RecalculateMatchesRequest;
use App\Models\CustomerRequest;
use App\Models\RequestMatch;
use App\Services\RequestMatchingService;
use App\Services\RequestMatchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequestMatchController extends Controller
{
    public function __construct(
        public RequestMatchService $requestMatchService,
        public RequestMatchingService $requestMatchingService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', RequestMatch::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $status = $this->requestMatchService->parseStatusFilter($request->input('status'));
        $merchantPublicId = $request->filled('merchant') ? (string) $request->input('merchant') : null;
        $requestPublicId = $request->filled('request') ? (string) $request->input('request') : null;

        return Inertia::render('Matching/MatchingPage', [
            'matches' => $this->requestMatchService->getPaginatedMatches(
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
                status: $status,
                merchantPublicId: $merchantPublicId,
                requestPublicId: $requestPublicId,
            ),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
                'status' => $status,
                'merchant' => $merchantPublicId,
                'request' => $requestPublicId,
            ],
            'statuses' => MatchStatus::toArray(),
        ]);
    }

    public function sync(RecalculateMatchesRequest $request, CustomerRequest $customerRequest)
    {
        $this->requestMatchingService->sync($customerRequest, strict: true);

        return redirect()->back()->with('success', 'تم تحديث المطابقات بنجاح');
    }
}
