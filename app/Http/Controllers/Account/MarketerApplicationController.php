<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\InvalidMarketerTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ApplyMarketerRequest;
use App\Services\MarketerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketerApplicationController extends Controller
{
    public function __construct(
        public MarketerService $marketerService,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = admin_dashboard_redirect()) {
            return $redirect;
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        $marketer = $user->marketer;
        if ($marketer?->isActive()) {
            return redirect()->route('marketer.home');
        }

        return Inertia::render('Account/ApplyMarketerPage', [
            'marketer' => $this->payload($marketer),
        ]);
    }

    public function store(ApplyMarketerRequest $request): RedirectResponse
    {
        if ($redirect = admin_dashboard_redirect()) {
            return $redirect;
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        $existing = $user->marketer;
        if ($existing?->isRejected()) {
            return $this->reapply($request);
        }

        if ($existing?->isInactive()) {
            return redirect()->route('marketer.application.status');
        }

        if ($existing?->isActive()) {
            return redirect()->route('marketer.home');
        }

        $this->marketerService->apply($user);

        return redirect()->route('marketer.application.status')
            ->with('success', 'تم إرسال طلب الانضمام');
    }

    public function status(Request $request): Response|RedirectResponse
    {
        if ($redirect = admin_dashboard_redirect()) {
            return $redirect;
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        $marketer = $user->marketer;
        if ($marketer === null) {
            return redirect()->route('marketer.application.create');
        }

        if ($marketer->isActive()) {
            return redirect()->route('marketer.home');
        }

        return Inertia::render('Account/MarketerStatusPage', [
            'marketer' => $this->payload($marketer),
        ]);
    }

    public function reapply(ApplyMarketerRequest $request): RedirectResponse
    {
        if ($redirect = admin_dashboard_redirect()) {
            return $redirect;
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $this->marketerService->reapply($user);
        } catch (InvalidMarketerTransitionException) {
            return redirect()->route('marketer.application.status');
        }

        return redirect()->route('marketer.application.status')
            ->with('success', 'تم إعادة إرسال الطلب');
    }

    /**
     * @return array{public_id: string, status: string, referral_code: string}|null
     */
    private function payload($marketer): ?array
    {
        if ($marketer === null) {
            return null;
        }

        return [
            'public_id' => $marketer->public_id,
            'status' => $marketer->status->name,
            'referral_code' => $marketer->referral_code,
        ];
    }
}
