<?php

namespace App\Enums\CustomerDailyRequestLimitChanges;

enum ChangeType: string
{
    case SetOverride = 'set_override';
    case UpdateOverride = 'update_override';
    case ClearOverride = 'clear_override';

    public function label(): string
    {
        return match ($this) {
            self::SetOverride => 'تعيين حد خاص',
            self::UpdateOverride => 'تحديث الحد الخاص',
            self::ClearOverride => 'إزالة الحد الخاص',
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
