<?php

use App\Support\ReferralCode;

test('referral codes are trimmed and uppercased', function () {
    expect(ReferralCode::normalize('  ab12cd34  '))->toBe('AB12CD34');
});

test('referral codes outside the allowed format are rejected', function () {
    expect(ReferralCode::normalize('ab'))->toBeNull();
    expect(ReferralCode::normalize('not-valid'))->toBeNull();
    expect(ReferralCode::normalize(''))->toBeNull();
    expect(ReferralCode::normalize(null))->toBeNull();
});
