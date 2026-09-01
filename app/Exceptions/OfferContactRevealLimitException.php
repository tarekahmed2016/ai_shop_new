<?php

namespace App\Exceptions;

use RuntimeException;

class OfferContactRevealLimitException extends RuntimeException
{
    public static function forRequest(): self
    {
        $message = str_starts_with(strtolower((string) app()->getLocale()), 'ar')
            ? 'لقد وصلت للحد الأقصى لبيانات التواصل المتاحة لهذا الطلب.'
            : 'You have reached the maximum merchant contacts available for this request.';

        return new self($message);
    }
}
