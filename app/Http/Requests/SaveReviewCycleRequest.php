<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveReviewCycle;
use App\Models\ReviewCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveReviewCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-review-cycles');
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        $cycle = $this->route('review_cycle');

        return SaveReviewCycle::rules($cycle instanceof ReviewCycle ? $cycle : null);
    }
}
