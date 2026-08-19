<?php

namespace App\Models;

use Database\Factories\HeroSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable([
    'title_ar',
    'title_en',
    'description_ar',
    'description_en',
    'cta_text_ar',
    'cta_text_en',
    'cta_url',
    'ordering',
    'is_active',
])]
class HeroSlide extends Model
{
    /** @use HasFactory<HeroSlideFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['is_active_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ordering' => 'integer',
        ];
    }

    /**
     * @return MorphOne<Attachment, $this>
     */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
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
}
