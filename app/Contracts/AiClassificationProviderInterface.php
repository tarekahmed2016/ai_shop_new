<?php

namespace App\Contracts;

use App\Support\Classification\ClassificationInput;
use App\Support\Classification\ClassificationResult;

interface AiClassificationProviderInterface
{
    public function classify(ClassificationInput $input): ClassificationResult;
}
