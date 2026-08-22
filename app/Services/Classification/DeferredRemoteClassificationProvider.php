<?php

namespace App\Services\Classification;

use App\Contracts\AiClassificationProviderInterface;
use App\Exceptions\ClassificationFailedException;
use App\Support\Classification\ClassificationInput;
use App\Support\Classification\ClassificationResult;

class DeferredRemoteClassificationProvider implements AiClassificationProviderInterface
{
    public function classify(ClassificationInput $input): ClassificationResult
    {
        throw new ClassificationFailedException('Remote AI classification is not enabled in this phase.');
    }
}
