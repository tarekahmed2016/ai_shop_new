<?php

use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use Database\Seeders\AiShopDemoSeeder;
use Illuminate\Support\Str;

test('demo seeder assigns unique ulid public ids and is idempotent', function () {
    $this->seed(AiShopDemoSeeder::class);

    $categories = Category::query()->where('slug', 'like', 'ai-shop-demo-%')->get();
    $merchants = Merchant::query()->where('email', 'like', '%@ai-shop-demo.test')->get();

    expect($categories)->toHaveCount(14)
        ->and($merchants)->toHaveCount(8)
        ->and(User::query()->where('email', 'like', '%@ai-shop-demo.test')->count())->toBe(8)
        ->and(MerchantUser::query()->count())->toBe(8);

    $categoryPublicIds = $categories->pluck('public_id');
    $merchantPublicIds = $merchants->pluck('public_id');

    expect($categoryPublicIds->every(fn ($id) => is_string($id) && Str::isUlid($id)))->toBeTrue()
        ->and($categoryPublicIds->unique()->count())->toBe(14)
        ->and($merchantPublicIds->every(fn ($id) => is_string($id) && Str::isUlid($id)))->toBeTrue()
        ->and($merchantPublicIds->unique()->count())->toBe(8);

    $snapshot = [
        'categories' => $categoryPublicIds->sort()->values()->all(),
        'merchants' => $merchantPublicIds->sort()->values()->all(),
    ];

    $this->seed(AiShopDemoSeeder::class);

    expect(Category::query()->where('slug', 'like', 'ai-shop-demo-%')->count())->toBe(14)
        ->and(Merchant::query()->where('email', 'like', '%@ai-shop-demo.test')->count())->toBe(8)
        ->and(Category::query()->where('slug', 'like', 'ai-shop-demo-%')->pluck('public_id')->sort()->values()->all())
        ->toEqual($snapshot['categories'])
        ->and(Merchant::query()->where('email', 'like', '%@ai-shop-demo.test')->pluck('public_id')->sort()->values()->all())
        ->toEqual($snapshot['merchants']);
});
