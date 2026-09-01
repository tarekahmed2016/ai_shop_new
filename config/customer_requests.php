<?php

return [
    'timezone' => 'Asia/Muscat',
    'default_daily_limit' => 3,
    'max_daily_limit' => 100,
    'ai_contact_confidence' => 0.6,
    'contact_reveal_limit' => (int) env('CUSTOMER_REQUEST_CONTACT_REVEAL_LIMIT', 3),
    'duplicate_confidence' => (float) env('CUSTOMER_REQUEST_DUPLICATE_CONFIDENCE', 0.90),
    'duplicate_previous_limit' => 6,
];
