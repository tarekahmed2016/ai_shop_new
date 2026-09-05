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

    public static function duplicateRequest(): string
    {
        if (self::arabic()) {
            return 'لديك طلب مشابه موجود بالفعل. يمكنك متابعة العروض من الطلب السابق.';
        }

        return 'You already have a similar request. You can continue with the existing request.';
    }

    public static function tooManyOpenAttempts(): string
    {
        if (self::arabic()) {
            return 'لديك عدد كبير من الطلبات قيد المعالجة. يرجى إكمال أو إلغاء طلب حالي قبل إرسال طلب جديد.';
        }

        return 'You have too many requests currently being processed. Please finish or cancel an existing one before submitting a new request.';
    }

    public static function classificationAlreadyInProgress(): string
    {
        if (self::arabic()) {
            return 'هناك طلب قيد التصنيف حاليًا. يرجى الانتظار حتى ينتهي قبل إرسال طلب آخر.';
        }

        return 'A request is already being classified. Please wait for it to finish before submitting another.';
    }

    public static function classificationFailed(): string
    {
        if (self::arabic()) {
            return 'تعذر تصنيف طلبك تلقائيًا. يمكنك إعادة المحاولة.';
        }

        return 'We could not automatically classify your request. You can retry.';
    }

    public static function processing(): string
    {
        if (self::arabic()) {
            return 'جاري تحليل طلبك بواسطة الذكاء الاصطناعي...';
        }

        return 'Analyzing your request with AI...';
    }

    public static function finalizing(): string
    {
        if (self::arabic()) {
            return 'جاري إنهاء طلبك...';
        }

        return 'Finalizing your request...';
    }

    public static function quotaExhaustedAtFinalization(): string
    {
        if (self::arabic()) {
            return 'تم استهلاك حد الطلبات اليومي قبل إنهاء هذا الطلب. يمكنك المحاولة لاحقاً أو شراء رصيد طلبات إضافية.';
        }

        return 'Your daily request allowance was used up before this request could be finalized. Please try again later, or add extra request credit.';
    }

    public static function requestNoLongerAvailable(): string
    {
        if (self::arabic()) {
            return 'لم يعد هذا الطلب متاحاً. إذا كان طلباً مكرراً يمكنك متابعة الطلب السابق.';
        }

        return 'This request is no longer available. If it was a duplicate, you can continue with your existing request.';
    }

    private static function arabic(): bool
    {
        return str_starts_with(strtolower((string) app()->getLocale()), 'ar');
    }
}
