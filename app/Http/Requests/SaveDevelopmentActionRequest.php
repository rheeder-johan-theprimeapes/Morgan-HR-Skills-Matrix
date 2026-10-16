<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveDevelopmentAction;
use Illuminate\Foundation\Http\FormRequest;

class SaveDevelopmentActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $choice = $this->input('origin_objective');
        if ($this->input('origin_kind') === 'review' && is_string($choice) && preg_match('/^([1-9]\d{0,17}):(\d{1,2})$/D', $choice, $matches) === 1) {
            $this->merge(['origin_release_id' => $matches[1], 'origin_objective_index' => $matches[2]]);
        }
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        $mode = $this->input('mode');

        return SaveDevelopmentAction::rules(is_string($mode) ? $mode : '', $this->route('development_action') === null);
    }
}
