<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Matched-request notification fan-out
    |--------------------------------------------------------------------------
    |
    | Recipients are discovered and queued in chunks so a large match set
    | does not load every membership into memory. Override only if needed.
    |
    */

    'matched_request_chunk_size' => max(1, min(500, (int) env('MATCHED_REQUEST_NOTIFICATION_CHUNK_SIZE', 200))),

];
