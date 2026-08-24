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
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('credit history shows add deduct promo offer submit source notes admin and balance after', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    $credits = app(MerchantOfferCreditService::class);

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 20,
            'source' => TransactionSource::BankTransfer->value,
            'reference' => 'TRX-12345',
            'notes' => 'Bank receipt',
        ])
        ->assertRedirect(route('merchants.credits.index', $merchant));

    $this->actingAs($admin)
        ->post(route('merchants.credits.store', $merchant), [
            'amount' => 5,
            'source' => TransactionSource::PromotionalBonus->value,
            'reference' => 'PROMO-1',
            'notes' => 'Launch bonus',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('merchants.credits.deduct', $merchant), [
            'amount' => 5,
            'source' => TransactionSource::ManualAdjustment->value,
            'notes' => 'Correction',
            'reference' => 'ADJ-1',
        ])
        ->assertRedirect();

    enableOfferCreditEnforcement();
    $request = CustomerRequest::factory()->create();
    $offer = MerchantOffer::factory()->create([
        'merchant_id' => $merchant->id,
        'customer_request_id' => $request->id,
    ]);
    $credits->lockMerchant((int) $merchant->id);
    $credits->consumeForOfferSubmit($merchant, $request, $offer, $admin);

    $rows = MerchantOfferCreditTransaction::query()
        ->where('merchant_id', $merchant->id)
        ->orderBy('id')
        ->get();

    expect($rows)->toHaveCount(4)
        ->and($rows[0]->amount)->toBe(20)
        ->and($rows[0]->balance_after)->toBe(20)
        ->and($rows[0]->type)->toBe(TransactionType::ManualAdd)
        ->and($rows[0]->source)->toBe(TransactionSource::BankTransfer)
        ->and($rows[0]->reference)->toBe('TRX-12345')
        ->and($rows[0]->notes)->toBe('Bank receipt')
        ->and($rows[0]->created_by_user_id)->toBe($admin->id)
        ->and($rows[1]->amount)->toBe(5)
        ->and($rows[1]->balance_after)->toBe(25)
        ->and($rows[1]->type)->toBe(TransactionType::PromotionalBonus)
        ->and($rows[1]->notes)->toBe('Launch bonus')
        ->and($rows[2]->amount)->toBe(-5)
        ->and($rows[2]->balance_after)->toBe(20)
        ->and($rows[2]->type)->toBe(TransactionType::ManualDeduct)
        ->and($rows[2]->reference)->toBe('ADJ-1')
        ->and($rows[3]->amount)->toBe(-1)
        ->and($rows[3]->balance_after)->toBe(19)
        ->and($rows[3]->type)->toBe(TransactionType::OfferSubmit);

    $this->actingAs($admin)
        ->get(route('merchants.credits.index', $merchant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Merchants/MerchantCreditsPage', false)
            ->where('balance', 19)
            ->has('transactions.data', 4)
            ->where('transactions.data.0.amount', -1)
            ->where('transactions.data.0.balance_after', 19)
            ->where('transactions.data.0.type', TransactionType::OfferSubmit->value)
            ->where('transactions.data.1.amount', -5)
            ->where('transactions.data.1.balance_after', 20)
            ->where('transactions.data.2.amount', 5)
            ->where('transactions.data.2.balance_after', 25)
            ->where('transactions.data.3.amount', 20)
            ->where('transactions.data.3.balance_after', 20)
            ->where('transactions.data.3.created_by.name', $admin->name)
            ->where('transactions.data.3.reference', 'TRX-12345')
            ->where('transactions.data.3.notes', 'Bank receipt')
        );

    $this->actingAs($admin)
        ->get(route('merchants.credits.index', $merchant).'?type='.TransactionType::ManualDeduct->value)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.amount', -5)
            ->where('transactions.data.0.balance_after', 20)
        );
});

test('credit history paginates newest first without loading the full ledger', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();

    foreach (range(1, 26) as $amount) {
        MerchantOfferCreditTransaction::factory()->create([
            'merchant_id' => $merchant->id,
            'amount' => $amount,
            'type' => TransactionType::ManualAdd,
            'source' => TransactionSource::ManualAdjustment,
            'created_by_user_id' => $admin->id,
        ]);
    }

    $this->actingAs($admin)
        ->get(route('merchants.credits.index', $merchant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 25)
            ->where('transactions.per_page', 25)
            ->where('transactions.total', 26)
            ->where('transactions.data.0.amount', 26)
            ->where('transactions.data.0.balance_after', 351)
            ->where('transactions.data.24.amount', 2)
        );

    $this->actingAs($admin)
        ->get(route('merchants.credits.index', $merchant).'?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.amount', 1)
            ->where('transactions.data.0.balance_after', 1)
        );
});

test('unauthorized users cannot view admin credit history', function () {
    $merchant = Merchant::factory()->create();
    MerchantOfferCreditTransaction::factory()->create([
        'merchant_id' => $merchant->id,
        'amount' => 10,
    ]);

    $plainAdmin = creditAdmin([]);
    $this->actingAs($plainAdmin)
        ->get(route('merchants.credits.index', $merchant))
        ->assertForbidden();

    $ownerMerchant = Merchant::factory()->create();
    $owner = User::factory()->create();
    MerchantUser::factory()->create([
        'user_id' => $owner->id,
        'merchant_id' => $ownerMerchant->id,
        'role' => Role::Owner,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($owner)
        ->withSession([MerchantContextService::SESSION_KEY => $ownerMerchant->id])
        ->get(route('merchants.credits.index', $ownerMerchant))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('merchants.credits.index', $merchant))
        ->assertRedirect(route('login'));
});

test('credit history does not N+1 created by users', function () {
    $admin = creditAdmin();
    $merchant = Merchant::factory()->create();
    $actors = User::factory()->count(8)->create();

    foreach ($actors as $actor) {
        MerchantOfferCreditTransaction::factory()->create([
            'merchant_id' => $merchant->id,
            'amount' => 1,
            'created_by_user_id' => $actor->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)
        ->get(route('merchants.credits.index', $merchant))
        ->assertOk();

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $userByIdQueries = $queries->filter(function (array $query) {
        return preg_match('/from ["`]?users["`]? where ["`]?id["`]? = \?/i', $query['query']) === 1;
    });

    expect($userByIdQueries->count())->toBeLessThan(3)
        ->and(Route::has('merchants.credits.destroy'))->toBeFalse();
});
