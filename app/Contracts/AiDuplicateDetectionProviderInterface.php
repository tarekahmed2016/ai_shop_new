<?php

namespace App\Contracts;

use App\Support\DuplicateDetection\DuplicateDetectionInput;
use App\Support\DuplicateDetection\DuplicateDetectionResult;

interface AiDuplicateDetectionProviderInterface
{
    public function detect(DuplicateDetectionInput $input): DuplicateDetectionResult;
}
