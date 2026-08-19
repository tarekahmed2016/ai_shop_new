<?php

namespace App\Models;

use App\Enums\CertificateAwardType;
use Database\Factories\CertificateAwardFactory;
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
    'issuer_ar',
    'issuer_en',
    'description_ar',
    'description_en',
    'issued_date',
    'external_url',
    'ordering',
    'is_active',
])]
class CertificateAward extends Model
{
    /** @use HasFactory<CertificateAwardFactory> */
    use HasFactory;

    protected $table = 'certificates_awards';

    /**
     * @var list<string>
     */
    protected $appends = ['is_active_formatted', 'type_formatted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateAwardType::class,
            'issued_date' => 'date',
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

    /**
     * @return Attribute<array{value: string, label: string, name: string}, never>
     */
    protected function typeFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
                'name' => $this->type->labelEn(),
            ]
        );
    }
}
