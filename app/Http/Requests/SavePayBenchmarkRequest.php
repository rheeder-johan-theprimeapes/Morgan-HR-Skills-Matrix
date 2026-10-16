<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SavePayBenchmark;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavePayBenchmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-benchmarks');
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return SavePayBenchmark::rules();
    }
}
