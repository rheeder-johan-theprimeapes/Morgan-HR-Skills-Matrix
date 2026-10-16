<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\RecordPdrFeedback;
use Illuminate\Foundation\Http\FormRequest;

class RecordPdrFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return RecordPdrFeedback::rules();
    }
}
