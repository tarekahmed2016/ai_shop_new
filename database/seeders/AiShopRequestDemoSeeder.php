<?php

namespace Database\Seeders;

use App\Enums\CustomerRequests\Source;
use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\Customers\Status as CustomerStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerRequest;
use App\Services\RequestMatchingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT-ONLY demo data for Phase 3 (customers/requests) and Phase 4 (matching).
 *
 * Depends on AiShopDemoSeeder categories and merchants (exact category matching).
 *
 * Identifiers:
 * - customer emails: demo.customer.{key}@ai-shop-demo.test
 * - request texts end with a stable marker: [DEMO-REQ-XXX]
 * - does not create request_matches manually; uses RequestMatchingService
 *
 * Run (after AiShopDemoSeeder):
 *   php artisan db:seed --class=AiShopRequestDemoSeeder
 *
 * Do not call this from DatabaseSeeder.
 */
class AiShopRequestDemoSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'ai-shop-demo.test';

    /**
     * @var list<array{key: string, name: string, phone: string}>
     */
    private const CUSTOMERS = [
        ['key' => 'ahmed', 'name' => 'DEMO Customer Ahmed', 'phone' => '90000001'],
        ['key' => 'salim', 'name' => 'DEMO Customer Salim', 'phone' => '90000002'],
        ['key' => 'mohammed', 'name' => 'DEMO Customer Mohammed', 'phone' => '90000003'],
        ['key' => 'ali', 'name' => 'DEMO Customer Ali', 'phone' => '90000004'],
        ['key' => 'khalid', 'name' => 'DEMO Customer Khalid', 'phone' => '90000005'],
        ['key' => 'hassan', 'name' => 'DEMO Customer Hassan', 'phone' => '90000006'],
        ['key' => 'nasser', 'name' => 'DEMO Customer Nasser', 'phone' => '90000007'],
        ['key' => 'saeed', 'name' => 'DEMO Customer Saeed', 'phone' => '90000008'],
    ];

    /**
     * @var list<array{code: string, customer: string, category_slug: string, text: string, status: RequestStatus}>
     */
    private const REQUESTS = [
        [
            'code' => '001',
            'customer' => 'ahmed',
            'category_slug' => 'ai-shop-demo-mobile-phones',
            'text' => 'محتاج شاشة أصلية لهاتف Honor X9',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '002',
            'customer' => 'salim',
            'category_slug' => 'ai-shop-demo-mobile-phones',
            'text' => 'محتاج بطارية أصلية لهاتف Samsung A54',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '003',
            'customer' => 'mohammed',
            'category_slug' => 'ai-shop-demo-mobile-accessories',
            'text' => 'محتاج شاحن سريع Type-C أصلي',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '004',
            'customer' => 'ali',
            'category_slug' => 'ai-shop-demo-mobile-accessories',
            'text' => 'محتاج سماعة بلوتوث بسعر مناسب',
            'status' => RequestStatus::New,
        ],
        [
            'code' => '005',
            'customer' => 'khalid',
            'category_slug' => 'ai-shop-demo-computers',
            'text' => 'محتاج لابتوب للاستخدام المكتبي بسعر متوسط',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '006',
            'customer' => 'hassan',
            'category_slug' => 'ai-shop-demo-electronics',
            'text' => 'محتاج تلفزيون سمارت 55 بوصة',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '007',
            'customer' => 'nasser',
            'category_slug' => 'ai-shop-demo-auto-parts',
            'text' => 'محتاج طرمبة بنزين شيفروليه جروف',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '008',
            'customer' => 'saeed',
            'category_slug' => 'ai-shop-demo-tires-batteries',
            'text' => 'محتاج بطارية سيارة 70 أمبير',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '009',
            'customer' => 'ahmed',
            'category_slug' => 'ai-shop-demo-building-materials',
            'text' => 'محتاج 20 كيس أسمنت ومواد بناء',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '010',
            'customer' => 'salim',
            'category_slug' => 'ai-shop-demo-plumbing',
            'text' => 'محتاج خلاط حوض وقطع سباكة',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '011',
            'customer' => 'mohammed',
            'category_slug' => 'ai-shop-demo-electrical-supplies',
            'text' => 'محتاج قاطع كهرباء وكابل 6 مم',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '012',
            'customer' => 'ali',
            'category_slug' => 'ai-shop-demo-home-appliances',
            'text' => 'محتاج غسالة أوتوماتيك 9 كيلو',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '013',
            'customer' => 'khalid',
            'category_slug' => 'ai-shop-demo-ac-refrigeration',
            'text' => 'محتاج مكيف سبليت 2 طن',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '014',
            'customer' => 'hassan',
            'category_slug' => 'ai-shop-demo-furniture',
            'text' => 'محتاج مكتب وكرسي مكتب',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '015',
            'customer' => 'nasser',
            'category_slug' => 'ai-shop-demo-printing',
            'text' => 'محتاج طباعة 500 كارت بزنس',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '016',
            'customer' => 'saeed',
            'category_slug' => 'ai-shop-demo-restaurants',
            'text' => 'محتاج تجهيز بوفيه لمناسبة 30 شخص',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '017',
            'customer' => 'ahmed',
            'category_slug' => 'ai-shop-demo-computers',
            'text' => 'محتاج ماوس وكيبورد لاسلكي للمكتب',
            'status' => RequestStatus::New,
        ],
        [
            'code' => '018',
            'customer' => 'salim',
            'category_slug' => 'ai-shop-demo-auto-parts',
            'text' => 'محتاج فلتر زيت لتويوتا كورولا',
            'status' => RequestStatus::Ready,
        ],
        [
            'code' => '019',
            'customer' => 'mohammed',
            'category_slug' => 'ai-shop-demo-mobile-phones',
            'text' => 'محتاج جوال متوسط بمواصفات جيدة',
            'status' => RequestStatus::Closed,
        ],
        [
            'code' => '020',
            'customer' => 'ali',
            'category_slug' => 'ai-shop-demo-printing',
            'text' => 'محتاج طباعة بروشورات للمحل',
            'status' => RequestStatus::Cancelled,
        ],
    ];

    public function run(): void
    {
        $this->call(AiShopDemoSeeder::class);

        $customers = [];

        foreach (self::CUSTOMERS as $row) {
            $customers[$row['key']] = $this->upsertDemoCustomer($row);
        }

        $matchingService = app(RequestMatchingService::class);

        foreach (self::REQUESTS as $row) {
            $category = Category::query()->where('slug', $row['category_slug'])->first();

            if ($category === null) {
                continue;
            }

            $customer = $customers[$row['customer']];
            $request = $this->upsertDemoRequest($row, $customer, $category);

            $matchingService->sync($request);
        }
    }

    public static function requestMarker(string $code): string
    {
        return '[DEMO-REQ-'.$code.']';
    }

    public static function customerEmail(string $key): string
    {
        return 'demo.customer.'.$key.'@'.self::EMAIL_DOMAIN;
    }

    /**
     * @param  array{key: string, name: string, phone: string}  $row
     */
    private function upsertDemoCustomer(array $row): Customer
    {
        $email = self::customerEmail($row['key']);
        $customer = Customer::query()->where('email', $email)->first();

        if ($customer === null) {
            $customer = new Customer;
            $customer->public_id = (string) Str::ulid();
            $customer->email = $email;
        } elseif (! str_starts_with((string) $customer->name, 'DEMO Customer ')) {
            return $customer;
        }

        $customer->fill([
            'name' => $row['name'],
            'phone' => $row['phone'],
            'whatsapp_id' => 'demo-wa-'.$row['key'],
            'status' => CustomerStatus::Active,
        ]);
        $customer->save();

        return $customer;
    }

    /**
     * @param  array{code: string, customer: string, category_slug: string, text: string, status: RequestStatus}  $row
     */
    private function upsertDemoRequest(array $row, Customer $customer, Category $category): CustomerRequest
    {
        $marker = self::requestMarker($row['code']);
        $requestText = $row['text'].' '.$marker;

        $request = CustomerRequest::query()
            ->where('request_text', 'like', '%'.$marker.'%')
            ->first();

        if ($request === null) {
            $request = new CustomerRequest;
            $request->public_id = (string) Str::ulid();
            $request->source = Source::Admin;
        }

        $request->customer_id = $customer->id;
        $request->request_text = $requestText;
        $request->status = $row['status'];
        $request->category_id = $category->id;
        $request->source = Source::Admin;
        $request->save();

        return $request->fresh(['category']);
    }
}
