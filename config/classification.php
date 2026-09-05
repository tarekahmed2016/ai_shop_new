<?php

return [
    'provider' => env('AI_CLASSIFICATION_PROVIDER', 'fake'),
    'model' => env('AI_CLASSIFICATION_MODEL', 'gpt-5.6-sol'),
    'reasoning_effort' => env('AI_CLASSIFICATION_REASONING_EFFORT', 'high'),
    'image_detail' => env('AI_CLASSIFICATION_IMAGE_DETAIL', 'original'),
    'high_confidence' => (float) env('AI_CLASSIFICATION_HIGH_CONFIDENCE', 0.85),
    'medium_confidence' => (float) env('AI_CLASSIFICATION_MEDIUM_CONFIDENCE', 0.60),
    'timeout' => (int) env('AI_CLASSIFICATION_TIMEOUT', 30),

    // Rollout flag for the queued AI pipeline. Default false keeps the
    // legacy synchronous classify/confirm/retry path as the live HTTP
    // behavior (rollback = leave this false / flip it back). Flip true
    // only after queue workers are confirmed healthy. See
    // config/customer_requests.php and README "Queue & scheduler".
    'async_enabled' => (bool) env('CLASSIFICATION_ASYNC_ENABLED', false),
];
