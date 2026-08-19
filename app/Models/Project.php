<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable([
    'name_ar',
    'name_en',
    'client_name_ar',
    'client_name_en',
    'description_ar',
    'description_en',
    'project_date',
    'project_url',
    'ordering',
    'is_active',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
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
            'project_date' => 'date',
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
