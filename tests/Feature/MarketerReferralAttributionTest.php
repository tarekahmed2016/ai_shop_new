<?php

use App\Enums\Marketers\Status as MarketerStatus;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\MarketerReferral;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\ReferralAttributionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Cookie;

function activeMarketer(string $code): Marketer
{
    return Marketer::factory()->create([
        'referral_code' => $code,
        'status' => MarketerStatus::Active,
    ]);
}

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Referred User',
        'email' => 'referred@example.com',
        'password' => 'password12',
        'password_confirmation' => 'password12',
    ], $overrides);
}

function queuedReferralCookie($response): ?Cookie
{
    $name = (string) config('referrals.cookie_name');

    return collect($response->headers->getCookies())->first(
        fn (Cookie $cookie) => $cookie->getName() === $name
    );
}

test('valid active referral is captured from home', function () {
    activeMarketer('HOMECODE1');

    $this->get('/?ref=HOMECODE1')->assertOk();

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBe('HOMECODE1');
});

test('valid active referral is captured from register', function () {
    activeMarketer('REGCODE12');

    $this->get('/register?ref=REGCODE12')->assertOk();

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBe('REGCODE12');
    expect(session(ReferralAttributionService::SESSION_LANDING_KEY))->toBe('/register');
});

test('referral codes are normalized on capture', function () {
    activeMarketer('NORMCODE1');

    $this->call('GET', '/', ['ref' => '  normcode1  '])->assertOk();

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBe('NORMCODE1');
});

test('invalid referral codes are ignored', function () {
    $this->get('/?ref=no')->assertOk();

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBeNull();
    expect(queuedReferralCookie($this->get('/')))->toBeNull();
});

test('inactive marketer codes are ignored', function () {
    Marketer::factory()->inactive()->create(['referral_code' => 'DEADCODE1']);

    $response = $this->get('/?ref=DEADCODE1')->assertOk();

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBeNull();
    expect(queuedReferralCookie($response))->toBeNull();
});

test('a valid referral sets an HttpOnly first-party cookie', function () {
    activeMarketer('COOKIEOK1');

    $response = $this->get('/?ref=COOKIEOK1')->assertOk();
    $cookie = $response->getCookie((string) config('referrals.cookie_name'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe('COOKIEOK1')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getPath())->toBe('/')
        ->and(strtolower((string) $cookie->getSameSite()))->toBe('lax');
});

test('the first captured referral wins and cannot be overwritten', function () {
    activeMarketer('FIRSTWIN1');
    activeMarketer('SECONDWIN');

    $this->get('/?ref=FIRSTWIN1')->assertOk();
    $this->get('/?ref=SECONDWIN')->assertOk();

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBe('FIRSTWIN1');
});

test('captured referral survives later navigation', function () {
    activeMarketer('NAVCODE12');

    $this->get('/?ref=NAVCODE12')->assertOk();
    $this->get('/login')->assertOk();

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBe('NAVCODE12');
});

test('registration attributes the captured referral to the new user', function () {
    $marketer = activeMarketer('ATTRCODE1');

    $this->get('/?ref=ATTRCODE1')->assertOk();

    $this->post('/register', registrationPayload())->assertRedirect(route('account.get-started'));

    $user = User::query()->where('email', 'referred@example.com')->first();
    $referral = MarketerReferral::query()->sole();

    expect($user)->not->toBeNull()
        ->and(MarketerReferral::query()->count())->toBe(1)
        ->and($referral->referred_user_id)->toBe($user->id)
        ->and($referral->marketer_id)->toBe($marketer->id)
        ->and($referral->referral_code)->toBe('ATTRCODE1')
        ->and($referral->landing_path)->toBe('/');
});

test('original landing path is stored from the first capture', function () {
    activeMarketer('LANDCODE1');

    $this->get('/register?ref=LANDCODE1')->assertOk();
    $this->get('/login')->assertOk();

    $this->post('/register', registrationPayload())->assertRedirect(route('account.get-started'));

    expect(MarketerReferral::query()->sole()->landing_path)->toBe('/register');
});

test('normal registration creates no marketer referral', function () {
    $this->post('/register', registrationPayload())->assertRedirect(route('account.get-started'));

    expect(MarketerReferral::query()->count())->toBe(0);
});

test('duplicate email registration creates no referral', function () {
    User::factory()->create(['email' => 'referred@example.com']);
    activeMarketer('DUPCODE12');

    $this->get('/?ref=DUPCODE12')->assertOk();

    $this->from('/register')->post('/register', registrationPayload())->assertSessionHasErrors('email');

    expect(MarketerReferral::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'referred@example.com')->count())->toBe(1);
});

test('authenticated users are not captured or newly attributed', function () {
    $existing = User::factory()->create();
    activeMarketer('AUTHCODE1');

    $response = $this->actingAs($existing)->get('/?ref=AUTHCODE1')->assertOk();

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBeNull()
        ->and(queuedReferralCookie($response))->toBeNull()
        ->and($existing->marketerReferral)->toBeNull()
        ->and(MarketerReferral::query()->count())->toBe(0);
});

test('self referral does not create a marketer referral row', function () {
    $marketer = activeMarketer('SELFCODE1');

    $this->get('/');
    session([
        ReferralAttributionService::SESSION_CODE_KEY => 'SELFCODE1',
    ]);

    expect(app(ReferralAttributionService::class)->attributeNewUser($marketer->user))->toBeFalse()
        ->and(MarketerReferral::query()->count())->toBe(0);
});

test('inactive marketer at registration does not receive a referral', function () {
    $marketer = activeMarketer('LATEDEAD1');

    $this->get('/?ref=LATEDEAD1')->assertOk();

    $marketer->update(['status' => MarketerStatus::Inactive]);

    $this->post('/register', registrationPayload())->assertRedirect(route('account.get-started'));

    expect(MarketerReferral::query()->count())->toBe(0)
        ->and(session(ReferralAttributionService::SESSION_CODE_KEY))->toBe('LATEDEAD1');
});

test('successful attribution clears session and expires the referral cookie', function () {
    activeMarketer('CLEAROK12');

    $this->get('/?ref=CLEAROK12')->assertOk();

    $response = $this->post('/register', registrationPayload())->assertRedirect(route('account.get-started'));

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBeNull()
        ->and(session(ReferralAttributionService::SESSION_LANDING_KEY))->toBeNull();

    $cookie = $response->getCookie((string) config('referrals.cookie_name'));
    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeLessThan(time());
});

test('failed validation preserves captured attribution for retry', function () {
    $marketer = activeMarketer('RETRYCODE');

    $this->get('/?ref=RETRYCODE')->assertOk();

    $this->from('/register')->post('/register', registrationPayload([
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))->assertSessionHasErrors('password');

    expect(session(ReferralAttributionService::SESSION_CODE_KEY))->toBe('RETRYCODE')
        ->and(MarketerReferral::query()->count())->toBe(0);

    $this->post('/register', registrationPayload())->assertRedirect(route('account.get-started'));

    expect(MarketerReferral::query()->sole()->marketer_id)->toBe($marketer->id);
});

test('a failed attribution transaction leaves no orphan user or referral', function () {
    activeMarketer('ROLLCODE1');

    $this->partialMock(ReferralAttributionService::class, function ($mock) {
        $mock->shouldReceive('attributeNewUser')->once()->andThrow(new RuntimeException('forced failure'));
    });

    $this->get('/?ref=ROLLCODE1')->assertOk();

    $this->post('/register', registrationPayload())->assertInternalServerError();

    expect(User::query()->where('email', 'referred@example.com')->exists())->toBeFalse()
        ->and(MarketerReferral::query()->count())->toBe(0);
});

test('unique referred_user_id prevents double attribution', function () {
    $user = User::factory()->create();
    $first = activeMarketer('UNIQCODE1');
    $second = activeMarketer('UNIQCODE2');

    MarketerReferral::query()->create([
        'marketer_id' => $first->id,
        'referred_user_id' => $user->id,
        'referral_code' => 'UNIQCODE1',
        'landing_path' => '/',
        'registered_at' => now(),
    ]);

    expect(fn () => MarketerReferral::query()->create([
        'marketer_id' => $second->id,
        'referred_user_id' => $user->id,
        'referral_code' => 'UNIQCODE2',
        'landing_path' => '/',
        'registered_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);

    $this->get('/');
    session([ReferralAttributionService::SESSION_CODE_KEY => 'UNIQCODE2']);
    expect(app(ReferralAttributionService::class)->attributeNewUser($user))->toBeFalse()
        ->and(MarketerReferral::query()->where('referred_user_id', $user->id)->count())->toBe(1)
        ->and($user->fresh()->marketerReferral->marketer_id)->toBe($first->id);
});

test('legacy customer register uses the same attribution flow', function () {
    $marketer = activeMarketer('CUSTCODE1');

    $this->get('/?ref=CUSTCODE1')->assertOk();

    $customersBefore = Customer::query()->count();
    $merchantsBefore = Merchant::query()->count();
    $membershipsBefore = MerchantUser::query()->count();

    $this->post('/customer/register', registrationPayload([
        'email' => 'legacy@example.com',
    ]))->assertRedirect(route('account.get-started'));

    $user = User::query()->where('email', 'legacy@example.com')->first();

    expect(MarketerReferral::query()->sole()->marketer_id)->toBe($marketer->id)
        ->and(MarketerReferral::query()->sole()->referred_user_id)->toBe($user->id)
        ->and(Customer::query()->count())->toBe($customersBefore)
        ->and(Merchant::query()->count())->toBe($merchantsBefore)
        ->and(MerchantUser::query()->count())->toBe($membershipsBefore)
        ->and(User::query()->where('email', 'legacy@example.com')->count())->toBe(1);
});

test('unified registration still creates no customer or merchant', function () {
    $customersBefore = Customer::query()->count();
    $merchantsBefore = Merchant::query()->count();

    $this->post('/register', registrationPayload())->assertRedirect(route('account.get-started'));

    expect(Customer::query()->count())->toBe($customersBefore)
        ->and(Merchant::query()->count())->toBe($merchantsBefore);
});

test('registration rejects trusted identity fields and they cannot control attribution', function () {
    $captured = activeMarketer('REALCODE1');
    $spoofed = activeMarketer('FAKECODE1');

    $this->get('/?ref=REALCODE1')->assertOk();

    foreach (['marketer_id', 'referrer_id', 'referred_by', 'user_id', 'customer_id', 'merchant_id'] as $field) {
        $this->from('/register')
            ->post('/register', registrationPayload([
                'email' => $field.'@example.com',
                $field => $spoofed->id,
            ]))
            ->assertSessionHasErrors($field);
    }

    expect(MarketerReferral::query()->count())->toBe(0);

    $this->post('/register', registrationPayload())->assertRedirect(route('account.get-started'));

    expect(MarketerReferral::query()->sole()->marketer_id)->toBe($captured->id);
});

test('referral cookie can still attribute after the session is gone', function () {
    $marketer = activeMarketer('COOKIEUSE');

    $this->get('/?ref=COOKIEUSE')->assertOk();

    Session::flush();

    $this->withCookie((string) config('referrals.cookie_name'), 'COOKIEUSE')
        ->post('/register', registrationPayload())
        ->assertRedirect(route('account.get-started'));

    expect(MarketerReferral::query()->sole()->marketer_id)->toBe($marketer->id);
});
