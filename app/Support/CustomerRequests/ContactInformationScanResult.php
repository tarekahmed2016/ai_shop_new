<?php

namespace App\Support\CustomerRequests;

readonly class ContactInformationScanResult
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public bool $detected,
        public array $types,
        public string $layer,
    ) {}
}
