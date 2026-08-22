<?php

namespace App\Enums\RequestClassifications;

enum Status: int
{
    case Suggested = 1;
    case NeedsReview = 2;
    case Confirmed = 3;
    case Rejected = 4;
    case Failed = 5;

    public function label(): string
    {
        return match ($this) {
            self::Suggested => 'مقترح',
            self::NeedsReview => 'يحتاج مراجعة',
            self::Confirmed => 'مؤكد',
            self::Rejected => 'مرفوض',
            self::Failed => 'فشل',
        };
    }

    /**
     * @return list<array{value: int, label: string, name: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'name' => $case->name,
            ],
            self::cases()
        );
    }
}
