<?php

namespace App\Http\Controllers;

use App\Enums\Marketers\Status;
use App\Exceptions\InvalidMarketerTransitionException;
use App\Http\Requests\MarketerStoreRequest;
use App\Models\Marketer;
use App\Services\MarketerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketerController extends Controller
{
    public function __construct(
        public MarketerService $marketerService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Marketer::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $statusValue = $request->input('status');
        $status = is_numeric($statusValue) ? Status::tryFrom((int) $statusValue) : null;

        return Inertia::render('Marketers/MarketersPage', [
            'marketers' => $this->marketerService->getPaginatedMarketers(
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
                status: $status,
            ),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
                'status' => $status?->value,
            ],
            'statuses' => Status::toArray(),
            'pendingCount' => $this->marketerService->pendingCount(),
        ]);
    }

    public function store(MarketerStoreRequest $request): RedirectResponse
    {
        $this->marketerService->createByAdmin($request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function approve(Marketer $marketer): RedirectResponse
    {
        $this->authorize('approve', $marketer);

        return $this->runTransition(fn () => $this->marketerService->approve($marketer), 'تم قبول الطلب');
    }

    public function reject(Marketer $marketer): RedirectResponse
    {
        $this->authorize('reject', $marketer);

        return $this->runTransition(fn () => $this->marketerService->reject($marketer), 'تم رفض الطلب');
    }

    public function deactivate(Marketer $marketer): RedirectResponse
    {
        $this->authorize('deactivate', $marketer);

        return $this->runTransition(fn () => $this->marketerService->deactivate($marketer), 'تم إيقاف المسوق');
    }

    public function reactivate(Marketer $marketer): RedirectResponse
    {
        $this->authorize('reactivate', $marketer);

        return $this->runTransition(fn () => $this->marketerService->reactivate($marketer), 'تم إعادة تفعيل المسوق');
    }

    private function runTransition(callable $callback, string $success): RedirectResponse
    {
        try {
            $callback();
        } catch (InvalidMarketerTransitionException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', $success);
    }
}
