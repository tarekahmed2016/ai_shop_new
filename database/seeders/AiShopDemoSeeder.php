<?php

namespace Database\Seeders;

use App\Enums\Categories\Status as CategoryStatus;
use App\Enums\MerchantMemberships\Role as MerchantRole;
use App\Enums\MerchantMemberships\Status as MembershipStatus;
use App\Enums\Merchants\Status as MerchantStatus;
use App\Enums\Users\Status as UserStatus;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT-ONLY demo data for Phase 1 (merchants) and Phase 2 (categories).
 *
 * Identifiers:
 * - category slugs start with "ai-shop-demo-"
 * - merchant names start with "DEMO "
 * - merchant/owner emails use the @ai-shop-demo.test domain
 *
 * Demo owner password (local development only): Demo12345!
 *
 * Run: php artisan db:seed --class=AiShopDemoSeeder
 *
 * Do not call this from DatabaseSeeder.
 */
class AiShopDemoSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'ai-shop-demo.test';

    private const OWNER_PASSWORD = 'Demo12345!';

    /**
     * @var list<array{slug: string, name_en: string, name_ar: string, sort_order: int, parent_slug: ?string}>
     */
    private const CATEGORIES = [
        ['slug' => 'ai-shop-demo-electronics', 'name_en' => 'Electronics', 'name_ar' => 'الإلكترونيات', 'sort_order' => 10, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-mobile-phones', 'name_en' => 'Mobile Phones', 'name_ar' => 'الهواتف المحمولة', 'sort_order' => 11, 'parent_slug' => 'ai-shop-demo-electronics'],
        ['slug' => 'ai-shop-demo-mobile-accessories', 'name_en' => 'Mobile Accessories', 'name_ar' => 'إكسسوارات الهواتف', 'sort_order' => 12, 'parent_slug' => 'ai-shop-demo-electronics'],
        ['slug' => 'ai-shop-demo-computers', 'name_en' => 'Computers & Laptops', 'name_ar' => 'الكمبيوتر واللابتوب', 'sort_order' => 13, 'parent_slug' => 'ai-shop-demo-electronics'],
        ['slug' => 'ai-shop-demo-auto-parts', 'name_en' => 'Auto Parts', 'name_ar' => 'قطع غيار السيارات', 'sort_order' => 20, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-tires-batteries', 'name_en' => 'Tires & Batteries', 'name_ar' => 'الإطارات والبطاريات', 'sort_order' => 21, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-building-materials', 'name_en' => 'Building Materials', 'name_ar' => 'مواد البناء', 'sort_order' => 30, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-plumbing', 'name_en' => 'Plumbing', 'name_ar' => 'السباكة', 'sort_order' => 31, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-electrical-supplies', 'name_en' => 'Electrical Supplies', 'name_ar' => 'الأدوات الكهربائية', 'sort_order' => 32, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-home-appliances', 'name_en' => 'Home Appliances', 'name_ar' => 'الأجهزة المنزلية', 'sort_order' => 40, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-ac-refrigeration', 'name_en' => 'Air Conditioning & Refrigeration', 'name_ar' => 'التكييف والتبريد', 'sort_order' => 41, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-furniture', 'name_en' => 'Furniture', 'name_ar' => 'الأثاث', 'sort_order' => 50, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-printing', 'name_en' => 'Printing & Advertising', 'name_ar' => 'الطباعة والدعاية', 'sort_order' => 60, 'parent_slug' => null],
        ['slug' => 'ai-shop-demo-restaurants', 'name_en' => 'Restaurants & Catering', 'name_ar' => 'المطاعم وتجهيز الطعام', 'sort_order' => 70, 'parent_slug' => null],
    ];

    /**
     * @var list<array{key: string, name: string, phone: string, category_slugs: list<string>}>
     */
    private const MERCHANTS = [
        [
            'key' => 'mobile-center',
            'name' => 'DEMO Mobile Center',
            'phone' => '01000000001',
            'category_slugs' => [
                'ai-shop-demo-mobile-phones',
                'ai-shop-demo-mobile-accessories',
            ],
        ],
        [
            'key' => 'tech-store',
            'name' => 'DEMO Tech Store',
            'phone' => '01000000002',
            'category_slugs' => [
                'ai-shop-demo-electronics',
                'ai-shop-demo-computers',
                'ai-shop-demo-mobile-phones',
            ],
        ],
        [
            'key' => 'auto-parts',
            'name' => 'DEMO Auto Parts',
            'phone' => '01000000003',
            'category_slugs' => [
                'ai-shop-demo-auto-parts',
                'ai-shop-demo-tires-batteries',
            ],
        ],
        [
            'key' => 'building-materials',
            'name' => 'DEMO Building Materials',
            'phone' => '01000000004',
            'category_slugs' => [
                'ai-shop-demo-building-materials',
                'ai-shop-demo-plumbing',
                'ai-shop-demo-electrical-supplies',
            ],
        ],
        [
            'key' => 'home-appliances',
            'name' => 'DEMO Home Appliances',
            'phone' => '01000000005',
            'category_slugs' => [
                'ai-shop-demo-home-appliances',
                'ai-shop-demo-electronics',
            ],
        ],
        [
            'key' => 'ac-refrigeration',
            'name' => 'DEMO AC & Refrigeration',
            'phone' => '01000000006',
            'category_slugs' => [
                'ai-shop-demo-ac-refrigeration',
                'ai-shop-demo-home-appliances',
                'ai-shop-demo-electrical-supplies',
            ],
        ],
        [
            'key' => 'printing-house',
            'name' => 'DEMO Printing House',
            'phone' => '01000000007',
            'category_slugs' => [
                'ai-shop-demo-printing',
                'ai-shop-demo-computers',
            ],
        ],
        [
            'key' => 'restaurant-supplies',
            'name' => 'DEMO Restaurant Supplies',
            'phone' => '01000000008',
            'category_slugs' => [
                'ai-shop-demo-restaurants',
                'ai-shop-demo-home-appliances',
                'ai-shop-demo-furniture',
            ],
        ],
    ];

    public function run(): void
    {
        $categories = [];

        foreach (self::CATEGORIES as $row) {
            $categories[$row['slug']] = $this->upsertDemoCategory($row);
        }

        foreach (self::CATEGORIES as $row) {
            $category = $categories[$row['slug']];
            $parentId = $row['parent_slug'] === null ? null : $categories[$row['parent_slug']]->id;

            if ($category->parent_id !== $parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }
        }

        foreach (self::MERCHANTS as $row) {
            $merchantEmail = 'demo.'.$row['key'].'@'.self::EMAIL_DOMAIN;
            $ownerEmail = 'demo.owner.'.$row['key'].'@'.self::EMAIL_DOMAIN;

            $merchant = $this->upsertDemoMerchant($row, $merchantEmail);

            if ($merchant === null) {
                continue;
            }

            $owner = User::query()->firstOrCreate(
                ['email' => $ownerEmail],
                [
                    'name' => $row['name'].' Owner',
                    'phone' => $row['phone'],
                    'password' => self::OWNER_PASSWORD,
                    'status' => UserStatus::Active,
                ],
            );

            if ($owner->wasRecentlyCreated === false && $owner->name === $row['name'].' Owner') {
                $owner->fill([
                    'phone' => $row['phone'],
                    'status' => UserStatus::Active,
                ]);
                $owner->save();
            }

            MerchantUser::query()->firstOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'user_id' => $owner->id,
                ],
                [
                    'role' => MerchantRole::Owner,
                    'status' => MembershipStatus::Active,
                ],
            );

            foreach ($row['category_slugs'] as $categorySlug) {
                MerchantCategory::query()->firstOrCreate([
                    'merchant_id' => $merchant->id,
                    'category_id' => $categories[$categorySlug]->id,
                ]);
            }
        }
    }

    /**
     * @param  array{slug: string, name_en: string, name_ar: string, sort_order: int, parent_slug: ?string}  $row
     */
    private function upsertDemoCategory(array $row): Category
    {
        $category = Category::query()->where('slug', $row['slug'])->first();

        if ($category === null) {
            $category = new Category;
            $category->public_id = (string) Str::ulid();
            $category->slug = $row['slug'];
        }

        $category->fill([
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'],
            'status' => CategoryStatus::Active,
            'sort_order' => $row['sort_order'],
        ]);
        $category->save();

        return $category;
    }

    /**
     * @param  array{key: string, name: string, phone: string, category_slugs: list<string>}  $row
     */
    private function upsertDemoMerchant(array $row, string $merchantEmail): ?Merchant
    {
        $merchant = Merchant::query()->where('email', $merchantEmail)->first();

        if ($merchant === null) {
            $merchant = new Merchant;
            $merchant->public_id = (string) Str::ulid();
            $merchant->email = $merchantEmail;
        } elseif (! str_starts_with($merchant->name, 'DEMO ')) {
            return null;
        }

        $merchant->fill([
            'name' => $row['name'],
            'phone' => $row['phone'],
            'status' => MerchantStatus::Active,
        ]);
        $merchant->save();

        return $merchant;
    }
}
