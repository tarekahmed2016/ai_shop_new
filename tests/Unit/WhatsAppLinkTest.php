<?php

use App\Support\WhatsAppLink;
use Tests\TestCase;

uses(TestCase::class);

test('oman 8-digit local numbers receive the configured country code', function () {
    expect(WhatsAppLink::digits('91234567'))->toBe('96891234567');
});

test('international oman numbers stay normalized without double prefixing', function () {
    expect(WhatsAppLink::digits('96891234567'))->toBe('96891234567')
        ->and(WhatsAppLink::digits('+96891234567'))->toBe('96891234567')
        ->and(WhatsAppLink::digits('0096891234567'))->toBe('96891234567');
});

test('whatsapp digits strip spaces hyphens and parentheses', function () {
    expect(WhatsAppLink::digits('+968 9111-2222'))->toBe('96891112222')
        ->and(WhatsAppLink::digits('00 968 (9111) 2222'))->toBe('96891112222')
        ->and(WhatsAppLink::digits(' 91 234 567 '))->toBe('96891234567');
});

test('invalid short or trunk-prefixed numbers remain unavailable', function () {
    expect(WhatsAppLink::digits('1234567'))->toBeNull()
        ->and(WhatsAppLink::digits('01012345678'))->toBeNull()
        ->and(WhatsAppLink::digits(''))->toBeNull()
        ->and(WhatsAppLink::digits(null))->toBeNull();
});

test('whatsapp url encodes the prefilled message and uses digits only', function () {
    $message = 'Hello, request ABC priced at 32.500 OMR.';
    $encoded = rawurlencode($message);
    $mobile = WhatsAppLink::mobileUrl('91234567', $message);
    $web = WhatsAppLink::webUrl('91234567', $message);
    $pair = WhatsAppLink::pair('91234567', $message);

    expect($mobile)->toBe('https://wa.me/96891234567?text='.$encoded)
        ->and($web)->toBe('https://web.whatsapp.com/send?phone=96891234567&text='.$encoded)
        ->and($pair)->toBe([
            'mobile' => $mobile,
            'web' => $web,
        ])
        ->and(WhatsAppLink::url('91234567', $message))->toBe($mobile)
        ->and(WhatsAppLink::url('12345', 'hi'))->toBeNull()
        ->and(WhatsAppLink::webUrl('12345', 'hi'))->toBeNull()
        ->and(WhatsAppLink::pair('12345', 'hi'))->toBeNull()
        ->and(WhatsAppLink::isValid('77416103'))->toBeTrue()
        ->and(WhatsAppLink::isValid('01012345678'))->toBeFalse();
});
