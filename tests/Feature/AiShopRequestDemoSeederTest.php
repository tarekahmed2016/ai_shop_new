<?php

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\RequestImage;
use App\Models\RequestMatch;
use App\Services\RequestMatchingService;
use Database\Seeders\AiShopRequestDemoSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->nonDemoCustomer = Customer::factory()->create([
        'name' => 'Real Customer Untouched',
        'email' => 'real.customer@example.test',
        'phone' => '91111111',
    ]);

    $this->nonDemoRequest = CustomerRequest::factory()->create([
        'customer_id' => $this->nonDemoCustomer->id,
        'request_text' => 'Non-demo request must stay untouched',
        'status' => RequestStatus::New,
        'category_id' => null,
    ]);

    $this->nonDemoCustomerSnapshot = $this->nonDemoCustomer->only(['id', 'public_id', 'name', 'email', 'phone']);
    $this->nonDemoRequestSnapshot = $this->nonDemoRequest->only(['id', 'public_id', 'customer_id', 'request_text', 'status', 'category_id']);
});

/**
 * @return list<string>
 */
function expectedMerchantEmailsForCategorySlug(string $slug): array
{
    return match ($slug) {
        'ai-shop-demo-mobile-phones' => [
            'demo.mobile-center@ai-shop-demo.test',
            'demo.tech-store@ai-shop-demo.test',
        ],
        'ai-shop-demo-mobile-accessories' => [
            'demo.mobile-center@ai-shop-demo.test',
        ],
        'ai-shop-demo-computers' => [
            'demo.tech-store@ai-shop-demo.test',
            'demo.printing-house@ai-shop-demo.test',
        ],
        'ai-shop-demo-electronics' => [
            'demo.tech-store@ai-shop-demo.test',
            'demo.home-appliances@ai-shop-demo.test',
        ],
        'ai-shop-demo-auto-parts' => [
            'demo.auto-parts@ai-shop-demo.test',
        ],
        'ai-shop-demo-tires-batteries' => [
            'demo.auto-parts@ai-shop-demo.test',
        ],
        'ai-shop-demo-building-materials' => [
            'demo.building-materials@ai-shop-demo.test',
        ],
        'ai-shop-demo-plumbing' => [
            'demo.building-materials@ai-shop-demo.test',
        ],
        'ai-shop-demo-electrical-supplies' => [
            'demo.building-materials@ai-shop-demo.test',
            'demo.ac-refrigeration@ai-shop-demo.test',
        ],
        'ai-shop-demo-home-appliances' => [
            'demo.home-appliances@ai-shop-demo.test',
            'demo.ac-refrigeration@ai-shop-demo.test',
            'demo.restaurant-supplies@ai-shop-demo.test',
        ],
        'ai-shop-demo-ac-refrigeration' => [
            'demo.ac-refrigeration@ai-shop-demo.test',
        ],
        'ai-shop-demo-furniture' => [
            'demo.restaurant-supplies@ai-shop-demo.test',
        ],
        'ai-shop-demo-printing' => [
            'demo.printing-house@ai-shop-demo.test',
        ],
        'ai-shop-demo-restaurants' => [
            'demo.restaurant-supplies@ai-shop-demo.test',
        ],
        default => [],
    };
}

test('request demo seeder creates customers requests and matches via matching service', function () {
    $this->seed(AiShopRequestDemoSeeder::class);

    $customers = Customer::query()
        ->where('email', 'like', 'demo.customer.%@ai-shop-demo.test')
        ->orderBy('email')
        ->get();

    expect($customers)->toHaveCount(8)
        ->and($customers->every(fn (Customer $c) => str_starts_with((string) $c->name, 'DEMO Customer ')))->toBeTrue()
        ->and($customers->every(fn (Customer $c) => Str::isUlid($c->public_id)))->toBeTrue();

    $requests = CustomerRequest::query()
        ->where('request_text', 'like', '%[DEMO-REQ-%')
        ->with(['category', 'matches.merchant'])
        ->get();

    expect($requests)->toHaveCount(20)
        ->and($requests->every(fn (CustomerRequest $r) => Str::isUlid($r->public_id)))->toBeTrue()
        ->and($requests->every(fn (CustomerRequest $r) => $r->source->value === 'admin'))->toBeTrue();

    $closed = $requests->first(fn (CustomerRequest $r) => str_contains($r->request_text, '[DEMO-REQ-019]'));
    $cancelled = $requests->first(fn (CustomerRequest $r) => str_contains($r->request_text, '[DEMO-REQ-020]'));

    expect($closed?->status)->toBe(RequestStatus::Closed)
        ->and($cancelled?->status)->toBe(RequestStatus::Cancelled)
        ->and(RequestMatch::query()->where('customer_request_id', $closed->id)->count())->toBe(0)
        ->and(RequestMatch::query()->where('customer_request_id', $cancelled->id)->count())->toBe(0);

    $matchingService = app(RequestMatchingService::class);

    foreach ($requests as $request) {
        $matchMerchantIds = RequestMatch::query()
            ->where('customer_request_id', $request->id)
            ->pluck('merchant_id')
            ->sort()
            ->values()
            ->all();

        $eligibleIds = $matchingService->eligibleMerchantIds($request)->sort()->values()->all();

        if ($request->status === RequestStatus::Closed || $request->status === RequestStatus::Cancelled) {
            expect($matchMerchantIds)->toBe([]);

            continue;
        }

        expect($matchMerchantIds)->toEqual($eligibleIds)
            ->and($matchMerchantIds)->not->toBeEmpty();

        $matchedEmails = Merchant::query()
            ->whereIn('id', $matchMerchantIds)
            ->pluck('email')
            ->sort()
            ->values()
            ->all();

        $expectedEmails = collect(expectedMerchantEmailsForCategorySlug($request->category->slug))
            ->sort()
            ->values()
            ->all();

        expect($matchedEmails)->toEqual($expectedEmails);
    }

    $phonesRequest = $requests->first(fn (CustomerRequest $r) => str_contains($r->request_text, '[DEMO-REQ-001]'));
    $phoneMatchEmails = $phonesRequest->matches->pluck('merchant.email')->sort()->values()->all();

    expect($phoneMatchEmails)->toEqual([
        'demo.mobile-center@ai-shop-demo.test',
        'demo.tech-store@ai-shop-demo.test',
    ])
        ->and($phoneMatchEmails)->not->toContain('demo.auto-parts@ai-shop-demo.test');

    $autoRequest = $requests->first(fn (CustomerRequest $r) => str_contains($r->request_text, '[DEMO-REQ-007]'));
    expect($autoRequest->matches->pluck('merchant.email')->all())->toEqual([
        'demo.auto-parts@ai-shop-demo.test',
    ]);

    $printRequest = $requests->first(fn (CustomerRequest $r) => str_contains($r->request_text, '[DEMO-REQ-015]'));
    expect($printRequest->matches->pluck('merchant.email')->all())->toEqual([
        'demo.printing-house@ai-shop-demo.test',
    ]);

    $buildingRequest = $requests->first(fn (CustomerRequest $r) => str_contains($r->request_text, '[DEMO-REQ-009]'));
    expect($buildingRequest->matches->pluck('merchant.email')->all())->toEqual([
        'demo.building-materials@ai-shop-demo.test',
    ]);

    $acRequest = $requests->first(fn (CustomerRequest $r) => str_contains($r->request_text, '[DEMO-REQ-013]'));
    expect($acRequest->matches->pluck('merchant.email')->all())->toEqual([
        'demo.ac-refrigeration@ai-shop-demo.test',
    ]);

    $allMatches = RequestMatch::query()->get(['customer_request_id', 'merchant_id']);
    expect($allMatches->count())->toBe(
        $allMatches->unique(fn (RequestMatch $m) => $m->customer_request_id.'-'.$m->merchant_id)->count()
    );

    expect($this->nonDemoCustomer->fresh()->only(['id', 'public_id', 'name', 'email', 'phone']))
        ->toEqual($this->nonDemoCustomerSnapshot)
        ->and($this->nonDemoRequest->fresh()->only(['id', 'public_id', 'customer_id', 'request_text', 'status', 'category_id']))
        ->toEqual($this->nonDemoRequestSnapshot)
        ->and(RequestMatch::query()->where('customer_request_id', $this->nonDemoRequest->id)->count())->toBe(0);
});

test('request demo seeder rerun does not duplicate customers requests or matches', function () {
    $this->seed(AiShopRequestDemoSeeder::class);

    $customerPublicIds = Customer::query()
        ->where('email', 'like', 'demo.customer.%@ai-shop-demo.test')
        ->pluck('public_id')
        ->sort()
        ->values()
        ->all();

    $requestPublicIds = CustomerRequest::query()
        ->where('request_text', 'like', '%[DEMO-REQ-%')
        ->pluck('public_id')
        ->sort()
        ->values()
        ->all();

    $matchCount = RequestMatch::query()->count();
    $matchPairs = RequestMatch::query()
        ->orderBy('customer_request_id')
        ->orderBy('merchant_id')
        ->get(['customer_request_id', 'merchant_id', 'status'])
        ->map(fn (RequestMatch $m) => $m->customer_request_id.'-'.$m->merchant_id.'-'.$m->status->value)
        ->all();

    $this->seed(AiShopRequestDemoSeeder::class);

    expect(Customer::query()->where('email', 'like', 'demo.customer.%@ai-shop-demo.test')->count())->toBe(8)
        ->and(CustomerRequest::query()->where('request_text', 'like', '%[DEMO-REQ-%')->count())->toBe(20)
        ->and(Customer::query()->where('email', 'like', 'demo.customer.%@ai-shop-demo.test')->pluck('public_id')->sort()->values()->all())
        ->toEqual($customerPublicIds)
        ->and(CustomerRequest::query()->where('request_text', 'like', '%[DEMO-REQ-%')->pluck('public_id')->sort()->values()->all())
        ->toEqual($requestPublicIds)
        ->and(RequestMatch::query()->count())->toBe($matchCount)
        ->and(
            RequestMatch::query()
                ->orderBy('customer_request_id')
                ->orderBy('merchant_id')
                ->get(['customer_request_id', 'merchant_id', 'status'])
                ->map(fn (RequestMatch $m) => $m->customer_request_id.'-'.$m->merchant_id.'-'.$m->status->value)
                ->all()
        )->toEqual($matchPairs)
        ->and($this->nonDemoCustomer->fresh()->only(['id', 'public_id', 'name', 'email', 'phone']))
        ->toEqual($this->nonDemoCustomerSnapshot)
        ->and($this->nonDemoRequest->fresh()->only(['id', 'public_id', 'customer_id', 'request_text', 'status', 'category_id']))
        ->toEqual($this->nonDemoRequestSnapshot);
});

test('request demo seeder never creates request image rows', function () {
    $this->seed(AiShopRequestDemoSeeder::class);

    $demoRequestIds = CustomerRequest::query()
        ->where('request_text', 'like', '%[DEMO-REQ-%')
        ->pluck('id');

    expect(RequestImage::query()->whereIn('customer_request_id', $demoRequestIds)->count())->toBe(0);
});
