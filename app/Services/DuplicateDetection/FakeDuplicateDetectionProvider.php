<?php

namespace App\Services\DuplicateDetection;

use App\Contracts\AiDuplicateDetectionProviderInterface;
use App\Support\DuplicateDetection\DuplicateDetectionInput;
use App\Support\DuplicateDetection\DuplicateDetectionResult;

class FakeDuplicateDetectionProvider implements AiDuplicateDetectionProviderInterface
{
    public ?DuplicateDetectionInput $lastInput = null;

    public int $calls = 0;

    /**
     * @var list<DuplicateDetectionResult>
     */
    public array $queued = [];

    /**
     * @var callable(DuplicateDetectionInput): DuplicateDetectionResult|null
     */
    public $handler = null;

    public function detect(DuplicateDetectionInput $input): DuplicateDetectionResult
    {
        $this->lastInput = $input;
        $this->calls++;

        if (is_callable($this->handler)) {
            return ($this->handler)($input);
        }

        if ($this->queued !== []) {
            return array_shift($this->queued);
        }

        return new DuplicateDetectionResult(
            isDuplicate: false,
            matchedRequestId: null,
            confidence: 0.99,
            reasonCode: 'different_item',
        );
    }

    public function queue(DuplicateDetectionResult $result): void
    {
        $this->queued[] = $result;
    }

    public function reset(): void
    {
        $this->lastInput = null;
        $this->calls = 0;
        $this->queued = [];
        $this->handler = null;
    }
}
