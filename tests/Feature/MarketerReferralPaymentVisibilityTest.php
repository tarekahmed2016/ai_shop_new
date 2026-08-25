<?php

use App\Enums\CustomerExtraRequests\TransactionSource as ExtraSource;
use App\Enums\MerchantOfferCredits\TransactionSource as CreditSource;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\MarketerReferral;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\CustomerExtraRequestService;
use App\Services\MerchantOfferCreditService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->refAdmin = User::factory()->create();
    $this->refAdmin->assignRole('admin');
});

function referralMarketer(): Marketer
{
    return Marketer::factory()->create();
}

function referUser(Marketer $marketer, User $user): void
{
    MarketerReferral::query()->create([
        'marketer_id' => $marketer->id,
        'referred_user_id' => $user->id,
        'referral_code' => $marketer->referral_code,
        'registered_at' => now(),
    ]);
}

test('marketer sees only paid transactions from their own referred users including dual customer and merchant', function () {
    $marketerA = referralMarketer();
    $marketerB = referralMarketer();
    $userX = User::factory()->create(['name' => 'Referred X']);
    $userY = User::factory()->create(['name' => 'Referred Y']);
    referUser($marketerA, $userX);
    referUser($marketerB, $userY);

    $customerX = Customer::factory()->create(['user_id' => $userX->id]);
    $merchantX = Merchant::factory()->create();
    attachMerchantOwner($merchantX, $userX);
    $customerY = Customer::factory()->create(['user_id' => $userY->id]);

    app(CustomerExtraRequestService::class)->addCredits(
        $customerX,
        5,
        ExtraSource::BankTransfer,
        'SECRET-REF-X',
        'internal note x',
        $this->refAdmin,
        paidAmount: '2.000',
    );
    app(MerchantOfferCreditService::class)->addCredits(
        $merchantX,
        20,
        CreditSource::Cash,
        'SECRET-MC-X',
        'internal merchant note',
        creditAdmin(),
        paidAmount: '5.000',
    );
    app(CustomerExtraRequestService::class)->addCredits(
        $customerY,
        4,
        ExtraSource::Cash,
        'SECRET-REF-Y',
        'internal note y',
        $this->refAdmin,
        paidAmount: '3.000',
    );

    $this->actingAs($marketerA->user)
        ->get(route('marketer.payments'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MarketerPortal/PaymentsPage', false)
            ->has('payments.data', 2)
            ->where('summary.total_amount', '7.000')
            ->where('summary.paying_users', 1)
            ->where('payments.data.0.payer_name', 'Referred X')
            ->missing('payments.data.0.reference')
            ->missing('payments.data.0.notes')
            ->missing('payments.data.1.reference')
        );

    $this->actingAs($marketerB->user)
        ->get(route('marketer.payments'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('summary.total_amount', '3.000')
            ->where('payments.data.0.payer_name', 'Referred Y')
            ->where('payments.data.0.capability_name', 'Customer'));
});

test('marketer payment month total pagination and no n plus one', function () {
    $marketer = referralMarketer();
    $users = User::factory()->count(3)->create();
    foreach ($users as $user) {
        referUser($marketer, $user);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        app(CustomerExtraRequestService::class)->addCredits(
            $customer,
            1,
            ExtraSource::Cash,
            null,
            null,
            $this->refAdmin,
            paidAmount: '1.000',
        );
    }

    $this->travelTo(now()->subMonth()->startOfMonth()->addDays(2));
    $oldUser = User::factory()->create();
    referUser($marketer, $oldUser);
    $oldCustomer = Customer::factory()->create(['user_id' => $oldUser->id]);
    app(CustomerExtraRequestService::class)->addCredits(
        $oldCustomer,
        1,
        ExtraSource::Cash,
        null,
        null,
        $this->refAdmin,
        paidAmount: '9.000',
    );
    $this->travelBack();

    $this->actingAs($marketer->user)
        ->get(route('marketer.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('paymentSummary.total_amount', '12.000')
            ->where('paymentSummary.month_amount', '3.000')
            ->where('paymentSummary.paying_users', 4));

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($marketer->user)
        ->get(route('marketer.payments'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 4)
            ->where('payments.per_page', 25));

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $userByIdQueries = $queries->filter(function (array $query) {
        return preg_match('/from ["`]?users["`]? where ["`]?id["`]? = \?/i', $query['query']) === 1;
    });

    expect($userByIdQueries->count())->toBeLessThan(3)
        ->and(PaymentTransaction::query()->count())->toBe(4);
});
