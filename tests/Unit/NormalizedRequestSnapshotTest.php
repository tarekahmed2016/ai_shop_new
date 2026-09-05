<?php

use App\Support\CustomerRequests\NormalizedRequestSnapshot;

test('a snapshot is comparable when item or summary is a non-empty string', function () {
    expect(NormalizedRequestSnapshot::isComparable(['item' => 'ABS Sensor']))->toBeTrue()
        ->and(NormalizedRequestSnapshot::isComparable(['summary' => 'auto spare parts']))->toBeTrue()
        ->and(NormalizedRequestSnapshot::isComparable(['item' => '  ', 'summary' => '']))->toBeFalse()
        ->and(NormalizedRequestSnapshot::isComparable(['item' => null, 'summary' => null]))->toBeFalse()
        ->and(NormalizedRequestSnapshot::isComparable([]))->toBeFalse();
});
