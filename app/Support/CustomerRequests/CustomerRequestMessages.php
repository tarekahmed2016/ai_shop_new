<?php

namespace App\Support\CustomerRequests;

final class CustomerRequestMessages
{
    public static function dailyLimitReached(): string
    {
        if (self::arabic()) {
            return 'لقد وصلت إلى الحد اليومي المسموح به لإرسال الطلبات.';
        }

        return 'You have reached your daily request limit.';
    }

    public static function suspended(): string
    {
        if (self::arabic()) {
            return 'تم إيقاف حساب العميل بسبب محاولة إرسال بيانات تواصل مباشرة داخل الطلب. يمكنك التواصل مع إدارة المنصة لمراجعة الحساب.';
        }

        return 'Your customer account has been suspended because direct contact information was detected in the request. Please contact platform support for review.';
    }

    public static function categoryManualProhibited(): string
    {
        if (self::arabic()) {
            return 'لا يمكن اختيار التصنيف يدويًا. سيتم تصنيفه تلقائيًا.';
        }

        return 'Category cannot be selected manually. Classification is handled automatically.';
    }

    public static function confirmSuggestedOnly(): string
    {
        if (self::arabic()) {
            return 'يمكنك تأكيد أحد التصنيفات المقترحة فقط.';
        }

        return 'You can only confirm a category suggested by classification.';
    }

    private static function arabic(): bool
    {
        return str_starts_with(strtolower((string) app()->getLocale()), 'ar');
    }
}
