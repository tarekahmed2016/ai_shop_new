<?php

namespace App\Support\DuplicateDetection;

readonly class DuplicateDetectionInput
{
    /**
     * @param  array<string, mixed>  $newRequest
     * @param  list<array<string, mixed>>  $previousRequests
     */
    public function __construct(
        public array $newRequest,
        public array $previousRequests,
    ) {}

    /**
     * Compact payload sent to the duplicate-detection AI. Never includes raw text or images.
     *
     * @return array{new_request: array<string, mixed>, previous_requests: list<array<string, mixed>>}
     */
    public function toAiPayload(): array
    {
        return [
            'new_request' => $this->newRequest,
            'previous_requests' => $this->previousRequests,
        ];
    }
}
