<?php

namespace App\Enums\Categories;

enum Status: int
{
    case Active = 1;
    case Inactive = 2;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Inactive => 'غير نشط',
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
