<?php

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\MerchantOfferCredits\TransactionType;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCreditTransaction;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantContextService;
use App\Services\MerchantOfferCreditService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function moneyOf(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return bcadd((string) $value, '0', 3);
}

test('manual add stores paid amount and allows null promotional bonus', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    attachMerchantOwner($merchant);

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 20,
            'source' => TransactionSource::BankTransfer->value,
            'paid_amount' => '5.000',
            'reference' => 'BANK-123456',
            'notes' => 'August package',
        ])
        ->assertRedirect(route('merchants.credits.index', $merchant));

    $paid = MerchantOfferCreditTransaction::query()->first();
    expect($paid->amount)->toBe(20)
        ->and(moneyOf($paid->paid_amount))->toBe('5.000')
        ->and($paid->balance_after)->toBe(20)
        ->and($paid->reference)->toBe('BANK-123456')
        ->and($paid->notes)->toBe('August package');

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 20,
            'source' => TransactionSource::PromotionalBonus->value,
        ])
        ->assertRedirect();

    $promo = MerchantOfferCreditTransaction::query()->orderByDesc('id')->first();
    expect($promo->type)->toBe(TransactionType::PromotionalBonus)
        ->and($promo->paid_amount)->toBeNull()
        ->and($promo->balance_after)->toBe(40);

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 10,
            'source' => TransactionSource::Cash->value,
            'paid_amount' => '-1',
        ])
        ->assertSessionHasErrors('paid_amount');

    expect(MerchantOfferCreditTransaction::query()->count())->toBe(2);
});

test('offer submit and manual deduct keep paid amount null and old rows stay null', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    $legacy = MerchantOfferCreditTransaction::factory()->create([
        'merchant_id' => $merchant->id,
        'amount' => 8,
        'paid_amount' => null,
    ]);

    app(MerchantOfferCreditService::class)->addCredits(
        $merchant,
        5,
        TransactionSource::Cash,
        null,
        null,
        $admin,
    );

    $this->actingAs($admin)
        ->post(route('merchants.credits.deduct', $merchant), [
            'amount' => 2,
            'source' => TransactionSource::ManualAdjustment->value,
            'notes' => 'Correction',
            'paid_amount' => '9.000',
        ])
        ->assertSessionHasErrors('paid_amount');

    $this->actingAs($admin)
        ->post(route('merchants.credits.deduct', $merchant), [
            'amount' => 2,
            'source' => TransactionSource::ManualAdjustment->value,
            'notes' => 'Correction',
        ])
        ->assertRedirect();

    enableOfferCreditEnforcement();
    $request = CustomerRequest::factory()->create();
    $offer = MerchantOffer::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $request->id,
    ]);
    app(MerchantOfferCreditService::class)->lockMerchant((int) $merchant->id);
    app(MerchantOfferCreditService::class)->consumeForOfferSubmit($merchant, $request, $offer, $admin);

    expect(moneyOf($legacy->fresh()->paid_amount))->toBeNull()
        ->and(MerchantOfferCreditTransaction::query()->where('type', TransactionType::ManualDeduct)->value('paid_amount'))->toBeNull()
        ->and(MerchantOfferCreditTransaction::query()->where('type', TransactionType::OfferSubmit)->value('paid_amount'))->toBeNull();
});

test('bulk add records paid amount per merchant not as a batch total', function () {
    $admin = creditAdmin();
    $merchants = Merchant::factory()->count(3)->create();
    $merchants->each(fn ($merchant) => attachMerchantOwner($merchant));

    $this->actingAs($admin)
        ->post(route('merchants.credits.bulk'), [
            'merchant_public_ids' => $merchants->pluck('public_id')->all(),
            'amount' => 20,
            'source' => TransactionSource::BankTransfer->value,
            'paid_amount' => '5.000',
            'reference' => 'PKG-20',
        ])
        ->assertRedirect();

    $rows = MerchantOfferCreditTransaction::query()->orderBy('merchant_id')->get();
    expect($rows)->toHaveCount(3)
        ->and($rows->every(fn ($row) => $row->amount === 20 && moneyOf($row->paid_amount) === '5.000'))->toBeTrue()
        ->and(moneyOf($rows->sum('paid_amount')))->toBe('15.000');
});

test('global credit history lists multiple merchants with filters pagination summaries and paid amount', function () {
    $admin = creditAdmin();
    $first = Merchant::factory()->create(['name' => 'Alpha Trading']);
    $second = Merchant::factory()->create(['name' => 'Beta Parts']);
    attachMerchantOwner($first);
    attachMerchantOwner($second);

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $first), [
            'amount' => 20,
            'source' => TransactionSource::BankTransfer->value,
            'paid_amount' => '5.000',
            'reference' => 'BANK-1',
            'notes' => 'August package',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $second), [
            'amount' => 10,
            'source' => TransactionSource::Cash->value,
            'paid_amount' => '10.000',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $second), [
            'amount' => 7,
            'source' => TransactionSource::PromotionalBonus->value,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('merchants.credits.deduct', $first), [
            'amount' => 3,
            'source' => TransactionSource::ManualAdjustment->value,
            'notes' => 'Correction',
        ])
        ->assertRedirect();

    enableOfferCreditEnforcement();
    $request = CustomerRequest::factory()->create();
    $offer = MerchantOffer::factory()->create([
        'merchant_id' => $second->id,
        'customer_request_id' => $request->id,
    ]);
    app(MerchantOfferCreditService::class)->lockMerchant((int) $second->id);
    app(MerchantOfferCreditService::class)->consumeForOfferSubmit($second, $request, $offer, $admin);

    $this->actingAs($admin)
        ->get(route('merchants.credits.transactions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantCreditTransactionsPage', false)
            ->has('transactions.data', 5)
            ->where('summary.total_paid_amount', '15.000')
            ->where('summary.credits_added', 37)
            ->where('transactions.data.0.type', TransactionType::OfferSubmit->value)
            ->where('transactions.data.0.paid_amount', null)
            ->where('transactions.data.0.merchant.public_id', $second->public_id)
            ->where('transactions.data.4.paid_amount', '5.000')
            ->where('transactions.data.4.created_by.name', $admin->name)
            ->where('transactions.data.4.reference', 'BANK-1')
        );

    $this->actingAs($admin)
        ->get(route('merchants.credits.transactions', ['merchant' => $first->public_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 2)
            ->where('transactions.data.0.merchant.public_id', $first->public_id)
            ->where('summary.credits_added', 20)
            ->where('summary.total_paid_amount', '5.000')
        );

    $this->actingAs($admin)
        ->get(route('merchants.credits.transactions', ['type' => TransactionType::PromotionalBonus->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.paid_amount', null)
            ->where('summary.credits_added', 7)
            ->where('summary.total_paid_amount', '0.000')
        );

    $this->actingAs($admin)
        ->get(route('merchants.credits.transactions', ['source' => TransactionSource::BankTransfer->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.reference', 'BANK-1')
        );

    $this->actingAs($admin)
        ->get(route('merchants.credits.transactions', ['paid_only' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 2)
            ->where('summary.total_paid_amount', '15.000')
        );

    $today = now()->toDateString();
    $this->actingAs($admin)
        ->get(route('merchants.credits.transactions', ['date_from' => $today, 'date_to' => $today]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 5));

    $this->actingAs($admin)
        ->get(route('merchants.credits.index', $first))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantCreditsPage', false)
            ->where('transactions.data.1.paid_amount', '5.000')
            ->where('transactions.data.0.paid_amount', null)
        );
});

test('global credit history paginates newest first without N+1 and blocks unauthorized users', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    $actors = User::factory()->count(8)->create();

    foreach (range(1, 26) as $index) {
        MerchantOfferCreditTransaction::factory()->create([
            'merchant_id' => $merchant->id,
            'amount' => $index,
            'paid_amount' => $index === 26 ? '1.000' : null,
            'created_by_user_id' => $actors[($index - 1) % $actors->count()]->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)
        ->get(route('merchants.credits.transactions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 25)
            ->where('transactions.per_page', 25)
            ->where('transactions.total', 26)
            ->where('transactions.data.0.amount', 26)
            ->where('transactions.data.0.paid_amount', '1.000')
        );

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $userByIdQueries = $queries->filter(function (array $query) {
        return preg_match('/from ["`]?users["`]? where ["`]?id["`]? = \?/i', $query['query']) === 1;
    });
    $merchantByIdQueries = $queries->filter(function (array $query) {
        return preg_match('/from ["`]?merchants["`]? where ["`]?id["`]? = \?/i', $query['query']) === 1;
    });

    expect($userByIdQueries->count())->toBeLessThan(3)
        ->and($merchantByIdQueries->count())->toBeLessThan(3);

    $this->actingAs($admin)
        ->get(route('merchants.credits.transactions').'?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.amount', 1)
            ->where('transactions.data.0.paid_amount', null)
        );

    $this->actingAs(creditAdmin([]))
        ->get(route('merchants.credits.transactions'))
        ->assertForbidden();

    $owner = User::factory()->create();
    $ownerMerchant = Merchant::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $owner->id,
        'merchant_id' => $ownerMerchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($owner)
        ->withSession([MerchantContextService::SESSION_KEY => $ownerMerchant->id])
        ->get(route('merchants.credits.transactions'))
        ->assertRedirect(route('login'));
});
