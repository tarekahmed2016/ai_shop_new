<?php

namespace App\Http\Requests\Concerns;

use App\Support\RichTextSanitizer;

trait SanitizesRichTextInput
{
    /**
     * @return list<string>
     */
    abstract protected function richTextFields(): array;

    protected function sanitizeRichTextInput(): void
    {
        $this->merge(
            RichTextSanitizer::sanitizeFields($this->all(), $this->richTextFields())
        );
    }
}
