<?php

namespace App\Models;

use App\Enums\HomepagePromoLayout;
use App\Enums\HomepagePromoType;
use Database\Factories\HomepagePromoBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable([
    'type',
    'title_ar',
    'title_en',
    'description_ar',
    'description_en',
    'cta_text_ar',
    'cta_text_en',
    'cta_url',
    'layout_variant',
    'ordering',
    'is_active',
])]
class HomepagePromoBlock extends Model
{
    /** @use HasFactory<HomepagePromoBlockFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['is_active_formatted', 'type_formatted', 'layout_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HomepagePromoType::class,
            'layout_variant' => HomepagePromoLayout::class,
            'is_active' => 'boolean',
            'ordering' => 'integer',
        ];
    }

    /**
     * @return MorphOne<Attachment, $this>
     */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where(function ($query) {
                $query->whereNull('collection')->orWhere('collection', 'default');
            });
    }

    /**
     * @return MorphOne<Attachment, $this>
     */
    public function badgeAttachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('collection', 'badge');
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
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
     * @return Attribute<array{value: string, label: string, name: string}, never>
     */
    protected function typeFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                $type = $this->type instanceof HomepagePromoType
                    ? $this->type
                    : HomepagePromoType::from((string) $this->type);

                return [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'name' => $type->labelEn(),
                ];
            }
        );
    }

    /**
     * @return Attribute<array{value: string, label: string, name: string}, never>
     */
    protected function layoutFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                $layout = $this->layout_variant instanceof HomepagePromoLayout
                    ? $this->layout_variant
                    : HomepagePromoLayout::from((string) ($this->layout_variant ?? HomepagePromoLayout::ContentLeft->value));

                return [
                    'value' => $layout->value,
                    'label' => $layout->label(),
                    'name' => $layout->labelEn(),
                ];
            }
        );
    }
}
