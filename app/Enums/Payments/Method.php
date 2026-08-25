<?php

namespace App\Enums\Payments;

use App\Enums\CustomerExtraRequests\TransactionSource as ExtraRequestSource;
use App\Enums\MerchantOfferCredits\TransactionSource as MerchantCreditSource;

enum Method: int
{
    case BankTransfer = 1;
    case Cash = 2;
    case Other = 3;

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'تحويل بنكي',
            self::Cash => 'نقدي',
            self::Other => 'أخرى',
        };
    }

    public static function fromMerchantCreditSource(MerchantCreditSource $source): self
    {
        return match ($source) {
            MerchantCreditSource::BankTransfer => self::BankTransfer,
            MerchantCreditSource::Cash => self::Cash,
            default => self::Other,
        };
    }

    public static function fromExtraRequestSource(ExtraRequestSource $source): self
    {
        return match ($source) {
            ExtraRequestSource::BankTransfer => self::BankTransfer,
            ExtraRequestSource::Cash => self::Cash,
            default => self::Other,
        };
    }

    /**
     * @return list<self>
     */
    public static function manualChoices(): array
    {
        return [
            self::BankTransfer,
            self::Cash,
            self::Other,
        ];
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
