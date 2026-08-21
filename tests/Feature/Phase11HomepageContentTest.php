<?php

use App\Enums\ActivityLogs\Event;
use App\Enums\HomepagePromoLayout;
use App\Enums\HomepagePromoType;
use App\Models\ActivityLog;
use App\Models\HeroSlide;
use App\Models\HomepagePromoBlock;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function validHeroSlidePayload(array $overrides = []): array
{
    return array_merge([
        'title_ar' => 'شريحة البطل',
        'title_en' => 'Hero Slide',
        'description_ar' => 'وصف الشريحة',
        'description_en' => 'Slide description',
        'cta_text_ar' => 'اطلب الآن',
        'cta_text_en' => 'Order Now',
        'cta_url' => 'https://example.com/order',
        'ordering' => 0,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('hero.jpg'),
    ], $overrides);
}

function validHomepagePromoPayload(array $overrides = []): array
{
    return array_merge([
        'type' => HomepagePromoType::FeatureBand->value,
        'title_ar' => 'عرض مميز',
        'title_en' => 'Feature Promo',
        'description_ar' => 'وصف العرض',
        'description_en' => 'Promo description',
        'cta_text_ar' => 'اعرف المزيد',
        'cta_text_en' => 'Learn More',
        'cta_url' => 'https://example.com',
        'layout_variant' => HomepagePromoLayout::ContentLeft->value,
        'ordering' => 0,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('promo.jpg'),
    ], $overrides);
}

test('guest cannot open hero slides index', function () {
    $this->get(route('hero-slides.index'))
        ->assertRedirect(route('login'));
});

test('admin can create hero slide with bilingual content and image', function () {
    $this->actingAs($this->admin)
        ->post(route('hero-slides.store'), validHeroSlidePayload())
        ->assertRedirect();

    $slide = HeroSlide::first();

    expect($slide)->not->toBeNull()
        ->and($slide->title_ar)->toBe('شريحة البطل')
        ->and($slide->title_en)->toBe('Hero Slide')
        ->and($slide->is_active)->toBeTrue()
        ->and($slide->attachment)->not->toBeNull();

    Storage::disk('public')->assertExists($slide->attachment->path);

    expect(ActivityLog::where('event', Event::Created)->where('subject_type', HeroSlide::class)->exists())->toBeTrue();
});

test('creating hero slide requires image on store', function () {
    $payload = validHeroSlidePayload();
    unset($payload['image']);

    $this->actingAs($this->admin)
        ->post(route('hero-slides.store'), $payload)
        ->assertSessionHasErrors('image');

    expect(HeroSlide::count())->toBe(0);
});

test('hero slide rejects non raster image upload', function () {
    $this->actingAs($this->admin)
        ->post(route('hero-slides.store'), validHeroSlidePayload([
            'image' => UploadedFile::fake()->create('slide.svg', 100, 'image/svg+xml'),
        ]))
        ->assertSessionHasErrors('image');
});

test('admin can update hero slide with bilingual content and optional image', function () {
    $slide = HeroSlide::factory()->create([
        'title_en' => 'Original Title',
        'ordering' => 1,
    ]);
    $slide->attachment()->create([
        'name' => 'hero.jpg',
        'path' => 'hero-slides/original.jpg',
    ]);

    Storage::disk('public')->put('hero-slides/original.jpg', 'original');

    $this->actingAs($this->admin)
        ->post(route('hero-slides.update', $slide), [
            '_method' => 'put',
            'title_ar' => 'عنوان محدث',
            'title_en' => 'Updated Title',
            'description_ar' => 'وصف محدث',
            'description_en' => 'Updated description',
            'cta_text_ar' => null,
            'cta_text_en' => null,
            'cta_url' => null,
            'ordering' => 1,
            'is_active' => true,
        ])
        ->assertRedirect();

    $slide->refresh();

    expect($slide->title_en)->toBe('Updated Title')
        ->and($slide->title_ar)->toBe('عنوان محدث')
        ->and($slide->description_en)->toBe('Updated description');
});

test('updating hero slide requires record specific route not collection route', function () {
    $slide = HeroSlide::factory()->create(['title_en' => 'Route Test']);
    $slide->attachment()->create(['name' => 'hero.jpg', 'path' => 'hero-slides/test.jpg']);
    Storage::disk('public')->put('hero-slides/test.jpg', 'test');

    $this->actingAs($this->admin)
        ->post('/hero-slides', [
            '_method' => 'put',
            'title_ar' => 'x',
            'title_en' => 'Should Fail',
            'ordering' => 0,
            'is_active' => true,
        ])
        ->assertStatus(405);
});

test('public homepage only shows active hero slides', function () {
    HeroSlide::factory()->create(['title_en' => 'Inactive Slide', 'is_active' => false, 'ordering' => 0]);
    $active = HeroSlide::factory()->create(['title_en' => 'Active Slide', 'is_active' => true, 'ordering' => 1]);
    $active->attachment()->create(['name' => 'hero.jpg', 'path' => 'hero-slides/hero.jpg']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/HomePage', false)
            ->has('heroSlides', 1)
            ->where('heroSlides.0.title_en', 'Active Slide'));
});

test('admin can manage homepage promo blocks with bilingual content', function () {
    $this->actingAs($this->admin)
        ->post(route('homepage-promos.store'), validHomepagePromoPayload([
            'type' => HomepagePromoType::BusinessCta->value,
            'image' => null,
        ]))
        ->assertRedirect();

    $block = HomepagePromoBlock::first();

    expect($block)->not->toBeNull()
        ->and($block->type)->toBe(HomepagePromoType::BusinessCta)
        ->and($block->title_en)->toBe('Feature Promo');
});

test('feature band and promo strip require image on create', function () {
    $payload = validHomepagePromoPayload(['type' => HomepagePromoType::PromoStrip->value]);
    unset($payload['image']);

    $this->actingAs($this->admin)
        ->post(route('homepage-promos.store'), $payload)
        ->assertSessionHasErrors('image');
});

test('admin can delete homepage promo block', function () {
    $block = HomepagePromoBlock::factory()->businessCta()->create([
        'title_en' => 'Delete Me',
        'title_ar' => '1111',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('homepage-promos.destroy', $block))
        ->assertRedirect();

    expect(HomepagePromoBlock::count())->toBe(0)
        ->and(ActivityLog::where('event', Event::Deleted)->where('subject_id', $block->id)->exists())->toBeTrue();
});

test('public homepage receives active promo blocks by type', function () {
    HomepagePromoBlock::factory()->businessCta()->create([
        'title_en' => 'Franchise CTA',
        'cta_text_en' => 'Apply Now',
        'cta_url' => '#contact',
        'is_active' => true,
    ]);

    HomepagePromoBlock::factory()->featureBand()->create([
        'title_en' => 'Fry Society',
        'is_active' => true,
    ])->attachment()->create([
        'name' => 'feature.jpg',
        'path' => 'homepage-promos/feature.jpg',
        'collection' => 'default',
    ]);

    HomepagePromoBlock::factory()->promoStrip()->inactive()->create(['title_en' => 'Hidden Strip']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/HomePage', false)
            ->where('businessCta.title_en', 'Franchise CTA')
            ->where('featureBand.title_en', 'Fry Society')
            ->has('promoStrips', 0));
});

test('guest can subscribe to newsletter with valid email', function () {
    $this->post(route('newsletter.store'), ['email' => 'subscriber@example.com'])
        ->assertRedirect()
        ->assertSessionHas('success', 'newsletter_subscribed');

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'subscriber@example.com',
        'is_active' => true,
    ]);
});

test('newsletter rejects invalid email', function () {
    $this->post(route('newsletter.store'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

test('duplicate newsletter email returns friendly flash without error', function () {
    $this->post(route('newsletter.store'), ['email' => 'dup@example.com'])
        ->assertSessionHas('success', 'newsletter_subscribed');

    $this->post(route('newsletter.store'), ['email' => 'dup@example.com'])
        ->assertSessionHas('info', 'newsletter_already_subscribed');

    expect(NewsletterSubscriber::where('email', 'dup@example.com')->count())->toBe(1);
});

test('newsletter subscription is rate limited', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('newsletter.store'), ['email' => "user{$i}@example.com"]);
    }

    $this->post(route('newsletter.store'), ['email' => 'blocked@example.com'])
        ->assertStatus(429);
});

test('newsletter ignores mass assignment of extra fields', function () {
    $this->post(route('newsletter.store'), [
        'email' => 'safe@example.com',
        'is_admin' => true,
        'is_active' => false,
    ])->assertRedirect();

    $subscriber = NewsletterSubscriber::where('email', 'safe@example.com')->first();

    expect($subscriber)->not->toBeNull()
        ->and($subscriber->is_active)->toBeTrue();
});

test('guest cannot open newsletter subscribers admin index', function () {
    $this->get(route('newsletter-subscribers.index'))
        ->assertRedirect(route('login'));
});

test('admin can list and delete newsletter subscribers', function () {
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'admin-list@example.com']);

    $this->actingAs($this->admin)
        ->get(route('newsletter-subscribers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('NewsletterSubscribers/NewsletterSubscribersPage', false)
            ->has('newsletterSubscribers.data', 1));

    $this->actingAs($this->admin)
        ->delete(route('newsletter-subscribers.destroy', $subscriber))
        ->assertRedirect();

    expect(NewsletterSubscriber::count())->toBe(0);
});
