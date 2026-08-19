<?php

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title_ar',
        'title_en',
        'menu_title_ar',
        'menu_title_en',
        'slug',
        'content_ar',
        'content_en',
        'show_in_main_menu',
        'menu_order',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['is_active_formatted', 'show_in_main_menu_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_in_main_menu' => 'boolean',
            'is_active' => 'boolean',
            'menu_order' => 'integer',
        ];
    }

    /**
     * @return Attribute<array{value: bool, label: string, name: string}, never>
     */
    protected function isActiveFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'value' => (bool) $this->is_active,
                'label' => $this->is_active ? 'نشط' : 'غير نشط',
                'name' => $this->is_active ? 'Active' : 'Inactive',
            ]
        );
    }

    /**
     * @return Attribute<array{value: bool, label: string, name: string}, never>
     */
    protected function showInMainMenuFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'value' => (bool) $this->show_in_main_menu,
                'label' => $this->show_in_main_menu ? 'نعم' : 'لا',
                'name' => $this->show_in_main_menu ? 'Yes' : 'No',
            ]
        );
    }
}
