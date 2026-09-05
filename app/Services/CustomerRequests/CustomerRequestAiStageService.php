<?php

namespace App\Services\CustomerRequests;

use App\Enums\CustomerRequests\AiStage;
use App\Models\CustomerRequest;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Implements the one, universal race-safe stage-transition pattern used by
 * every HTTP intake method and every AI pipeline job (plan section F.0):
 *
 *   DB transaction:
 *       lock the row
 *       validate current stage is in the accepted set
 *       validate ai_job_token matches (when a token is expected)
 *       run the caller's mutation (writes belonging to this transition)
 *   COMMIT
 *   dispatch downstream job(s) ->afterCommit()
 *
 * Because the guard is re-checked with a fresh, locked read on every call,
 * a stale token or a stage that already moved on is always a safe no-op —
 * this is what makes worker retries with the same token safe, makes
 * failed() handlers from a superseded token harmless, and makes the stuck
 * job recovery sweep unable to clobber a row a live worker just advanced.
 */
class CustomerRequestAiStageService
{
    public function newToken(): string
    {
        return (string) Str::ulid();
    }

    /**
     * @param  AiStage|list<AiStage>|null  $acceptedStages  null accepts any stage
     * @param  Closure(CustomerRequest):mixed  $mutate  runs inside the lock; must persist any changes itself
     * @return mixed whatever $mutate returns, or null if the guard rejected the transition
     */
    public function guardedTransition(
        int $customerRequestId,
        AiStage|array|null $acceptedStages,
        ?string $expectedToken,
        Closure $mutate
    ): mixed {
        return DB::transaction(function () use ($customerRequestId, $acceptedStages, $expectedToken, $mutate) {
            $request = CustomerRequest::query()->whereKey($customerRequestId)->lockForUpdate()->first();

            if ($request === null) {
                return null;
            }

            if ($acceptedStages !== null) {
                $accepted = is_array($acceptedStages) ? $acceptedStages : [$acceptedStages];
                if (! in_array($request->ai_stage, $accepted, true)) {
                    return null;
                }
            }

            if ($expectedToken !== null && $request->ai_job_token !== $expectedToken) {
                return null;
            }

            return $mutate($request);
        });
    }

    /**
     * Persist a stage/token advance on an already-locked row (call only
     * from inside a guardedTransition() mutator).
     */
    public function advance(CustomerRequest $request, AiStage $stage, ?string $token, bool $resetAttempts = true): void
    {
        $request->ai_stage = $stage;
        $request->ai_job_token = $token;
        $request->ai_stage_updated_at = now();

        if ($resetAttempts) {
            $request->ai_attempts = 0;
        }

        $request->save();
    }
}
