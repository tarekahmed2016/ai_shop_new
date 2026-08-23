<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebPushSubscriptionRequest;
use App\Http\Requests\WebPushUnsubscribeRequest;
use App\Models\User;
use App\Services\PushSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPushSubscriptionController extends Controller
{
    public function __construct(
        public PushSubscriptionService $pushSubscriptionService,
    ) {}

    public function config(Request $request): JsonResponse
    {
        return response()->json($this->pushSubscriptionService->config($this->requireUser($request)));
    }

    public function store(WebPushSubscriptionRequest $request): JsonResponse
    {
        $this->pushSubscriptionService->store($this->requireUser($request), $request->validated());

        return response()->json([
            'subscribed' => true,
        ]);
    }

    public function destroy(WebPushUnsubscribeRequest $request): JsonResponse
    {
        $this->pushSubscriptionService->destroy($this->requireUser($request), (string) $request->validated('endpoint'));

        return response()->json(['ok' => true]);
    }

    private function requireUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
