<?php

return [
    'provider' => env('AI_DUPLICATE_DETECTION_PROVIDER', env('AI_CLASSIFICATION_PROVIDER', 'fake')),
    'model' => env('AI_DUPLICATE_DETECTION_MODEL', env('AI_CLASSIFICATION_MODEL', 'gpt-5.6-sol')),
    'reasoning_effort' => env('AI_DUPLICATE_DETECTION_REASONING_EFFORT', 'medium'),
    'timeout' => (int) env('AI_DUPLICATE_DETECTION_TIMEOUT', 20),
];
