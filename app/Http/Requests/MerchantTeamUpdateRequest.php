<?php

namespace App\Http\Requests;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status;
use App\Models\MerchantUser;
use App\Support\MerchantAuthorization;
use App\Support\MerchantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MerchantTeamUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MerchantUser|null $membership */
        $membership = $this->route('membership');

        if (! $membership instanceof MerchantUser) {
            return false;
        }

        $auth = app(MerchantAuthorization::class);
        $contextMerchantId = app(MerchantContext::class)->merchantId();

        if ($contextMerchantId === null || $membership->merchant_id !== $contextMerchantId) {
            abort(404);
        }

        return $auth->canEditMembership($membership);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', new Enum(Role::class)],
            'status' => ['required', new Enum(Status::class)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:100'],
            'merchant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }
}
