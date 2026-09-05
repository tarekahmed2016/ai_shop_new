<?php

use App\Support\CustomerRequests\QuotaConsumedAtBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, forward-only migration supporting the async AI classification /
 * duplicate-detection / finalization pipeline.
 *
 * No existing column is modified or dropped. No existing row's visible
 * behavior changes except that `quota_consumed_at` is backfilled so the
 * corrected `CustomerRequestLimitService::todayCount()` query produces the
 * exact same historical counts as the previous raw-row-count query (see the
 * backfill comment below for the correctness argument).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_requests', function (Blueprint $table) {
            // Pipeline state machine (App\Enums\CustomerRequests\AiStage).
            // Null while status is not PendingClassification, and always
            // null for admin-created requests.
            $table->string('ai_stage', 32)->nullable()->after('normalized_request_json');

            // Drives the stuck-job recovery sweep's staleness detection.
            $table->timestamp('ai_stage_updated_at')->nullable()->after('ai_stage');

            // Single token reused across every job type that ever "owns"
            // this row. Sufficient because the state machine guarantees a
            // row is owned by at most one job type at any moment.
            $table->string('ai_job_token', 40)->nullable()->after('ai_stage_updated_at');

            // Consecutive stuck-recovery re-dispatch counter for the current
            // token lineage. Reset to 0 by genuine customer actions.
            $table->unsignedTinyInteger('ai_attempts')->default(0)->after('ai_job_token');

            // Client-supplied idempotency key for the *current* intake
            // attempt (classify or confirm). Overwritten per attempt cycle,
            // not append-only.
            $table->string('submission_token', 40)->nullable()->after('ai_attempts');

            // What the customer actually confirmed, persisted synchronously
            // at confirm-intake time so the finalize job acts on exactly
            // what was shown to (and chosen by) the customer, independent of
            // whatever "latest classification" might mean later.
            // Intentionally plain nullable integers (no DB-level FK) — these
            // are internal pipeline bookkeeping fields, not relational data
            // that other parts of the app join against.
            $table->unsignedBigInteger('confirmed_category_id')->nullable()->after('submission_token');
            $table->unsignedBigInteger('confirmed_classification_id')->nullable()->after('confirmed_category_id');

            // Set exactly once, inside the finalize job's atomic
            // consume+finalize transaction, immediately before status
            // becomes Ready. This — not row existence — is now the
            // authoritative marker of "this row consumed a daily quota
            // slot / extra-request credit unit".
            $table->timestamp('quota_consumed_at')->nullable()->after('confirmed_classification_id');

            // Machine-readable reason for the current ai_stage (e.g.
            // quota_exhausted_at_finalization). Null for ordinary stages.
            $table->string('ai_stage_reason', 64)->nullable()->after('quota_consumed_at');

            // Set when a duplicate check (early or final) blocks this row;
            // points at the pre-existing request it duplicates. The row is
            // kept (status flips to Cancelled) rather than deleted, so the
            // customer always gets a graceful, permanently-available answer
            // instead of racing an expiring notice. A short-lived cache
            // copy of the same notice covers the (rare) case the row is
            // later removed before the customer polls.
            $table->unsignedBigInteger('duplicate_of_customer_request_id')->nullable()->after('ai_stage_reason');

            $table->index(['ai_stage', 'ai_stage_updated_at'], 'customer_requests_ai_stage_stale_idx');
            $table->index(['customer_id', 'quota_consumed_at'], 'customer_requests_customer_quota_idx');
            $table->unique(['customer_id', 'submission_token'], 'customer_requests_customer_submission_token_unique');
        });

        // Backfill: under the pre-existing synchronous code, a Web/WhatsApp
        // CustomerRequest row was never persisted until quota/credit
        // consumption had already happened (including on total AI failure —
        // see the architecture review). So "row exists" has always been
        // equivalent to "quota was consumed" for every historical row.
        // Setting quota_consumed_at = created_at for all of them makes the
        // new quota_consumed_at-based todayCount() query produce identical
        // historical counts to the old raw-row-count query. Admin-created
        // rows are unaffected either way because todayCount() already
        // filters to source in (web, whatsapp).
        QuotaConsumedAtBackfill::run();
    }

    public function down(): void
    {
        Schema::table('customer_requests', function (Blueprint $table) {
            $table->dropUnique('customer_requests_customer_submission_token_unique');
            $table->dropIndex('customer_requests_customer_quota_idx');
            $table->dropIndex('customer_requests_ai_stage_stale_idx');

            $table->dropColumn([
                'ai_stage',
                'ai_stage_updated_at',
                'ai_job_token',
                'ai_attempts',
                'submission_token',
                'confirmed_category_id',
                'confirmed_classification_id',
                'quota_consumed_at',
                'ai_stage_reason',
                'duplicate_of_customer_request_id',
            ]);
        });
    }
};
