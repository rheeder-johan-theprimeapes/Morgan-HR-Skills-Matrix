<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPdrReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'expected_revision' => ['required', 'integer', 'min:1']];
    }
}
