<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titleEn = fake()->unique()->words(3, true);

        return [
            'title_ar' => 'صفحة '.fake()->word(),
            'title_en' => ucfirst($titleEn),
            'menu_title_ar' => null,
            'menu_title_en' => null,
            'slug' => Str::slug($titleEn),
            'content_ar' => '<p>محتوى عربي</p>',
            'content_en' => '<p>English content</p>',
            'show_in_main_menu' => false,
            'menu_order' => 100,
            'is_active' => true,
        ];
    }
}
