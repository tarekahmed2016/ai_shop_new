<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeHttpUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail(__('validation.url'));

            return;
        }

        if (strlen($value) > 2048) {
            $fail(__('validation.max.string', ['attribute' => $attribute, 'max' => 2048]));

            return;
        }

        if (preg_match('/^(javascript|data|vbscript):/i', $value)) {
            $fail('The :attribute must be a valid http or https URL.');

            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail(__('validation.url'));

            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must be a valid http or https URL.');
        }
    }
}
