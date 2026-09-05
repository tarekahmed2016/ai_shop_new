<?php

namespace App\Http\Requests;

use App\Support\AdminAccess;
use App\Support\SafeRasterImage;
use Illuminate\Foundation\Http\FormRequest;

class RichTextImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdminAccess::canUploadRichText($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'upload' => SafeRasterImage::inlineUploadRules(),
        ];
    }
}
