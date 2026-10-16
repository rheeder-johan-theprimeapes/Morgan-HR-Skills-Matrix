<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SavePdrDraft;
use App\Capability;
use App\Models\PdrReview;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavePdrDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('review');
        $section = $this->input('section');

        return $review instanceof PdrReview && in_array($section, ['employee', 'manager'], true) && Gate::allows($section === 'employee' ? 'editEmployee' : 'editManager', $review);
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        $user = $this->user();
        $section = $this->input('section');

        return SavePdrDraft::rules(is_string($section) ? $section : '', $user instanceof User && $user->hasCapability(Capability::RecommendPay), $user instanceof User && $user->hasCapability(Capability::Benchmarks));
    }
}
