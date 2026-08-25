<?php

namespace App\Models;

use Database\Factories\PlatformSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class PlatformSetting extends Model
{
    /** @use HasFactory<PlatformSettingFactory> */
    use HasFactory;

    public const KEY_OFFER_CREDIT_ENFORCEMENT = 'merchant_offer_credit_enforcement';

    public const KEY_DAILY_CUSTOMER_REQUEST_LIMIT = 'daily_customer_request_limit';

    public const KEY_MARKETER_COMMISSION_CUSTOMER = 'mk_comm_customer_rate';

    public const KEY_MARKETER_COMMISSION_MERCHANT = 'mk_comm_merchant_rate';
}
