<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgePdrReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'choice' => ['required', 'in:receipt,disagree'], 'comment' => ['nullable', 'string', 'max:4000']];
    }
}
