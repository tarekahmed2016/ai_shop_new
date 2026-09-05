<?php

return [
    'timezone' => 'Asia/Muscat',
    'default_daily_limit' => 3,
    'max_daily_limit' => 100,
    'ai_contact_confidence' => 0.6,
    'contact_reveal_limit' => (int) env('CUSTOMER_REQUEST_CONTACT_REVEAL_LIMIT', 3),
    'duplicate_confidence' => (float) env('CUSTOMER_REQUEST_DUPLICATE_CONFIDENCE', 0.90),
    'duplicate_previous_limit' => 6,

    // --- Async AI pipeline (classification / duplicate-check / finalization) ---

    // Dedicated queue name for AI-processing jobs, kept separate from the
    // notification fan-out queue so a burst of one workload can never
    // starve the other. Must be included in whatever `queue:work` command
    // runs in each environment (see README "Queue & scheduler").
    'ai_queue' => env('CUSTOMER_REQUEST_AI_QUEUE', 'ai-processing'),

    // Maximum number of non-Ready (PendingClassification) rows a single
    // customer may have open at once — an anti-abuse ceiling, independent
    // of the daily accepted-request quota, since quota is no longer tied
    // to row existence.
    'max_open_ai_attempts' => (int) env('CUSTOMER_REQUEST_MAX_OPEN_AI_ATTEMPTS', 5),

    // Idle ready_for_review/failed rows older than this are auto-cancelled
    // by the recovery sweep, freeing the open-attempt ceiling.
    'open_attempt_ttl_hours' => (int) env('CUSTOMER_REQUEST_OPEN_ATTEMPT_TTL_HOURS', 48),

    // How long an in-flight ai_stage may go without progress before the
    // recovery sweep treats it as stuck and reissues a fresh job/token.
    // Must stay comfortably above every AI job's own $timeout (30-35s).
    'stuck_ai_threshold_minutes' => (int) env('CUSTOMER_REQUEST_STUCK_AI_THRESHOLD_MINUTES', 3),

    // After this many stuck-recovery re-dispatch attempts for the same
    // pipeline stage group, stop retrying and surface a terminal outcome.
    'stuck_ai_max_recovery_attempts' => (int) env('CUSTOMER_REQUEST_STUCK_AI_MAX_RECOVERY_ATTEMPTS', 3),

    // How often Laravel's scheduler should invoke
    // `customer-requests:recover-stuck-ai`. The system cron that calls
    // `php artisan schedule:run` must still run every minute; this value
    // only controls how often the recovery command is actually fired.
    // Recovery and expiry do not run at all if that cron/`schedule:work`
    // process is missing — they are not triggered by queue workers.
    'stuck_ai_recovery_every_minutes' => (int) env('CUSTOMER_REQUEST_STUCK_AI_RECOVERY_EVERY_MINUTES', 5),

    // How long a Ready row may sit with matching_completed_at null after
    // matching_last_attempt_at before the pending-matching recovery sweep
    // re-dispatches MatchCustomerRequestJob. Must stay above the matching
    // job timeout (120s) plus its retry backoff. A null last-attempt
    // (lost dispatch after Ready commit) is recovered immediately.
    'matching_recovery_stale_minutes' => (int) env('CUSTOMER_REQUEST_MATCHING_RECOVERY_STALE_MINUTES', 10),

    // How often Laravel's scheduler should invoke
    // `customer-requests:recover-pending-matching`. The same minute-cron
    // that fires stuck-AI recovery is required; this value only controls
    // cadence of the matching sweep.
    'matching_recovery_every_minutes' => (int) env('CUSTOMER_REQUEST_MATCHING_RECOVERY_EVERY_MINUTES', 5),

    // Frontend polling cadence/timeout for the classification-status
    // endpoint. Kept server-side/config-driven so they can be tuned without
    // a frontend deploy (the values are exposed to the page via Inertia).
    'status_poll_interval_ms' => (int) env('CUSTOMER_REQUEST_STATUS_POLL_INTERVAL_MS', 1800),
    'status_poll_timeout_ms' => (int) env('CUSTOMER_REQUEST_STATUS_POLL_TIMEOUT_MS', 90000),
    'status_poll_retry_ms' => (int) env('CUSTOMER_REQUEST_STATUS_POLL_RETRY_MS', 3000),

    // How long a duplicate-block notice stays in cache after the pending
    // row is cancelled/removed, so a poll that races the row's absence
    // still gets a graceful customer-facing outcome instead of a 404.
    'duplicate_notice_ttl_seconds' => (int) env('CUSTOMER_REQUEST_DUPLICATE_NOTICE_TTL_SECONDS', 86400),
];
