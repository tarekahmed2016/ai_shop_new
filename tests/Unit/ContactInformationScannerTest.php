<?php

use App\Services\ContactInformationScanner;
use Tests\TestCase;

uses(TestCase::class);

test('deterministic scanner blocks obvious contact channels', function (string $text, array $types) {
    $result = (new ContactInformationScanner)->scanText($text);

    expect($result->detected)->toBeTrue()
        ->and($result->types)->toEqualCanonicalizing($types);
})->with([
    ['Call me on 9xxxxxxx', ['phone']],
    ['WhatsApp 9xxxxxxx', ['whatsapp', 'phone']],
    ['name@example.com', ['email']],
    ['https://example.com', ['url']],
    ['contact me on Instagram @partsoman', ['social']],
]);

test('deterministic scanner allows product numbers prices and quantities', function (string $text) {
    $result = (new ContactInformationScanner)->scanText($text);

    expect($result->detected)->toBeFalse()->and($result->types)->toBe([]);
})->with([
    ['10 pieces'],
    ['Toyota 2019'],
    ['Part No. 123456'],
    ['50 OMR budget'],
    ['size 120x80'],
    ['Need model X200'],
]);
