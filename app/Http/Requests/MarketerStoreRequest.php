<?php

namespace App\Http\Requests;

use App\Enums\Marketers\Status;
use App\Models\Marketer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class MarketerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Marketer::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $mode = (string) $this->input('mode');

        $rules = [
            'mode' => ['required', 'in:attach,create'],
            'status' => ['required', new Enum(Status::class), Rule::in([Status::Pending->value, Status::Active->value])],
        ];

        if ($mode === 'attach') {
            return array_merge($rules, [
                'user_email' => ['required', 'email', 'max:255'],
                'name' => ['prohibited'],
                'email' => ['prohibited'],
                'phone' => ['prohibited'],
                'password' => ['prohibited'],
            ]);
        }

        return array_merge($rules, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'user_email' => ['prohibited'],
        ]);
    }
}
