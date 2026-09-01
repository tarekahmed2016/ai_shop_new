<?php

namespace App\Exceptions;

use App\Models\CustomerRequest;
use RuntimeException;

class DuplicateCustomerRequestException extends RuntimeException
{
    public function __construct(
        public CustomerRequest $matchedRequest,
        public ?float $confidence = null,
        public ?string $reasonCode = null,
    ) {
        parent::__construct('Duplicate customer request.');
    }
}
