<?php

use App\Enums\MerchantOfferCredits\AdminPermission;
use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\MerchantOfferCredits\TransactionType;
use App\Models\Merchant;
use App\Models\MerchantOfferCreditTransaction;
use App\Models\User;
use App\Services\MerchantOfferCreditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

test('admin can add and deduct credits with ledger history', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 20,
            'source' => TransactionSource::BankTransfer->value,
            'reference' => 'TRX-12345',
            'notes' => 'Bank receipt',
        ])
        ->assertRedirect(route('merchants.credits.index', $merchant));

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(20);

    $add = MerchantOfferCreditTransaction::query()->first();
    expect($add->amount)->toBe(20)
        ->and($add->type)->toBe(TransactionType::ManualAdd)
        ->and($add->source)->toBe(TransactionSource::BankTransfer)
        ->and($add->reference)->toBe('TRX-12345')
        ->and($add->notes)->toBe('Bank receipt')
        ->and($add->created_by_user_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('merchants.credits.deduct', $merchant), [
            'amount' => 5,
            'source' => TransactionSource::ManualAdjustment->value,
            'notes' => 'Correction',
            'reference' => 'ADJ-1',
        ])
        ->assertRedirect(route('merchants.credits.index', $merchant));

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(15);

    $this->actingAs($admin)
        ->post(route('merchants.credits.deduct', $merchant), [
            'amount' => 20,
            'source' => TransactionSource::ManualAdjustment->value,
            'notes' => 'Too much',
        ])
        ->assertSessionHasErrors('amount');

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(15);

    $this->actingAs($admin)
        ->get(route('merchants.credits.index', $merchant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantCreditsPage', false)
            ->where('balance', 15)
            ->has('transactions.data', 2));
});

test('unauthorized users cannot modify offer credits', function () {
    $merchant = Merchant::factory()->create();
    $plainAdmin = creditAdmin([]);
    $user = User::factory()->create();

    $this->actingAs($plainAdmin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 10,
            'source' => TransactionSource::Cash->value,
        ])
        ->assertForbidden();

    ['merchant' => $ownerMerchant, 'user' => $owner] = matchedOfferSetup();

    $this->actingAs($owner)
        ->withSession(offerSession($ownerMerchant))
        ->post(route('merchants.credits.store', $ownerMerchant), [
            'amount' => 10,
            'source' => TransactionSource::Cash->value,
        ])
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->post(route('merchants.credits.bulk'), [
            'merchant_public_ids' => [$merchant->public_id],
            'amount' => 10,
            'source' => TransactionSource::Cash->value,
        ])
        ->assertRedirect(route('login'));

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(0)
        ->and(app(MerchantOfferCreditService::class)->balance($ownerMerchant->id))->toBe(0);
});

test('admin can enable enforcement from merchants index', function () {
    $admin = creditAdmin();

    $this->actingAs($admin)
        ->put(route('merchants.credits.enforcement'), ['enabled' => true])
        ->assertRedirect();

    expect(app(MerchantOfferCreditService::class)->isEnforcementEnabled())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('merchants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offerCreditEnforcement', true)
            ->where('creditPermissions.add', true));
});

test('admin bulk add credits each selected merchant separately and is atomic', function () {
    $admin = creditAdmin();
    $merchants = Merchant::factory()->count(3)->create();
    $ids = $merchants->pluck('public_id')->all();

    $this->actingAs($admin)
        ->post(route('merchants.credits.bulk'), [
            'merchant_public_ids' => array_merge($ids, [$ids[0]]),
            'amount' => 20,
            'source' => TransactionSource::PromotionalBonus->value,
            'reference' => 'PROMO-20',
            'notes' => 'Launch bonus',
        ])
        ->assertRedirect();

    foreach ($merchants as $merchant) {
        expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(20)
            ->and(MerchantOfferCreditTransaction::query()->where('merchant_id', $merchant->id)->count())->toBe(1);

        $row = MerchantOfferCreditTransaction::query()->where('merchant_id', $merchant->id)->first();
        expect($row->amount)->toBe(20)
            ->and($row->type)->toBe(TransactionType::PromotionalBonus)
            ->and($row->source)->toBe(TransactionSource::PromotionalBonus)
            ->and($row->reference)->toBe('PROMO-20')
            ->and($row->notes)->toBe('Launch bonus')
            ->and($row->created_by_user_id)->toBe($admin->id);
    }

    expect(MerchantOfferCreditTransaction::query()->count())->toBe(3);
});

test('bulk add rejects empty zero negative and invalid merchants without crediting others', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    $missing = (string) Str::ulid();

    $this->actingAs($admin)
        ->post(route('merchants.credits.bulk'), [
            'merchant_public_ids' => [],
            'amount' => 20,
            'source' => TransactionSource::Cash->value,
        ])
        ->assertSessionHasErrors('merchant_public_ids');

    $this->actingAs($admin)
        ->post(route('merchants.credits.bulk'), [
            'merchant_public_ids' => [$merchant->public_id],
            'amount' => 0,
            'source' => TransactionSource::Cash->value,
        ])
        ->assertSessionHasErrors('amount');

    $this->actingAs($admin)
        ->post(route('merchants.credits.bulk'), [
            'merchant_public_ids' => [$merchant->public_id],
            'amount' => -5,
            'source' => TransactionSource::Cash->value,
        ])
        ->assertSessionHasErrors('amount');

    $this->actingAs($admin)
        ->post(route('merchants.credits.bulk'), [
            'merchant_public_ids' => [$merchant->public_id, $missing],
            'amount' => 20,
            'source' => TransactionSource::Cash->value,
        ])
        ->assertSessionHasErrors('merchant_public_ids.1');

    expect(app(MerchantOfferCreditService::class)->balance($merchant->id))->toBe(0);
});

test('bulk add rolls back all merchants when one selected merchant is missing after lock', function () {
    $admin = creditAdmin();
    $kept = Merchant::factory()->create();
    $missingId = 999999;

    expect(fn () => app(MerchantOfferCreditService::class)->bulkAdd(
        [$kept->id, $missingId],
        20,
        TransactionSource::Cash,
        null,
        null,
        $admin,
    ))->toThrow(ValidationException::class);

    expect(app(MerchantOfferCreditService::class)->balance($kept->id))->toBe(0)
        ->and(MerchantOfferCreditTransaction::query()->count())->toBe(0);
});

test('admin merchants index uses a credit balance aggregate without loading ledger rows', function () {
    $admin = creditAdmin();
    Merchant::factory()->count(3)->create()->each(function (Merchant $merchant) use ($admin) {
        app(MerchantOfferCreditService::class)->addCredits($merchant, 7, TransactionSource::Cash, null, null, $admin);
    });

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)
        ->get(route('merchants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('merchants.data.0.offer_credit_balance', 7));

    $log = DB::getQueryLog();
    DB::disableQueryLog();

    $ledgerRowLoads = collect($log)->filter(function (array $query) {
        $sql = strtolower($query['query']);

        return str_contains($sql, 'merchant_offer_credit_transactions')
            && ! str_contains($sql, 'sum(')
            && ! str_contains($sql, 'count(');
    })->count();

    expect($ledgerRowLoads)->toBe(0);
});

test('admin without add permission cannot bulk credit', function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => AdminPermission::View->value, 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo(AdminPermission::View->value);
    $merchant = Merchant::factory()->create();

    $this->actingAs($admin)
        ->post(route('merchants.credits.bulk'), [
            'merchant_public_ids' => [$merchant->public_id],
            'amount' => 10,
            'source' => TransactionSource::Cash->value,
        ])
        ->assertForbidden();
});
