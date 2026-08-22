<?php

return [
    'provider' => env('AI_CLASSIFICATION_PROVIDER', 'fake'),
    'model' => env('AI_CLASSIFICATION_MODEL', 'gpt-5.6-sol'),
    'reasoning_effort' => env('AI_CLASSIFICATION_REASONING_EFFORT', 'high'),
    'image_detail' => env('AI_CLASSIFICATION_IMAGE_DETAIL', 'original'),
    'high_confidence' => (float) env('AI_CLASSIFICATION_HIGH_CONFIDENCE', 0.85),
    'medium_confidence' => (float) env('AI_CLASSIFICATION_MEDIUM_CONFIDENCE', 0.60),
    'timeout' => (int) env('AI_CLASSIFICATION_TIMEOUT', 30),
];
