<?php

namespace App\Enums\ActivityLogs;

enum Event: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'تم الإنشاء',
            self::Updated => 'تم التحديث',
            self::Deleted => 'تم الحذف',
        };
    }

    /**
     * @return list<array{value: string, label: string, name: string}>
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
