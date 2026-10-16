<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\AllocatePdrReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AllocatePdrReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-review-cycles');
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return AllocatePdrReview::rules();
    }
}
