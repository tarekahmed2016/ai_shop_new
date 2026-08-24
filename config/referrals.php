<?php

return [
    'query_parameter' => 'ref',
    'cookie_name' => env('REFERRAL_COOKIE_NAME', 'ref_code'),
    'cookie_days' => (int) env('REFERRAL_COOKIE_DAYS', 30),
];
