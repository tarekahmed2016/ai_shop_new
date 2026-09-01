<?php

namespace App\Services\DuplicateDetection;

use App\Contracts\AiDuplicateDetectionProviderInterface;
use App\Exceptions\DuplicateDetectionFailedException;
use App\Support\DuplicateDetection\DuplicateDetectionInput;
use App\Support\DuplicateDetection\DuplicateDetectionResult;

class DeferredRemoteDuplicateDetectionProvider implements AiDuplicateDetectionProviderInterface
{
    public function detect(DuplicateDetectionInput $input): DuplicateDetectionResult
    {
        throw new DuplicateDetectionFailedException('Remote AI duplicate detection is not enabled.');
    }
}
