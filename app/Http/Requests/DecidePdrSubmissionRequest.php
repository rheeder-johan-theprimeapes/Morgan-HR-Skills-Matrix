<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\DecidePdrSubmission;
use Illuminate\Foundation\Http\FormRequest;

class DecidePdrSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return DecidePdrSubmission::rules();
    }
}
