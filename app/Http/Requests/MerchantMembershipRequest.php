<?php

namespace App\Http\Requests;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status;
use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class MerchantMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $membership = $this->route('membership');

        if ($membership) {
            return $this->user()?->can('update', $membership) === true;
        }

        return $this->user()?->can('create', MerchantUser::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Merchant|null $merchant */
        $merchant = $this->route('merchant');
        $membership = $this->route('membership');

        $userIdRules = [
            $membership ? 'sometimes' : 'required',
            'integer',
            'exists:users,id',
        ];

        if ($merchant && ! $membership) {
            $userIdRules[] = Rule::unique('merchant_user', 'user_id')->where(
                fn ($query) => $query->where('merchant_id', $merchant->id)
            );
        }

        return [
            'user_id' => $userIdRules,
            'role' => ['required', new Enum(Role::class)],
            'status' => ['required', new Enum(Status::class)],
        ];
    }
}
