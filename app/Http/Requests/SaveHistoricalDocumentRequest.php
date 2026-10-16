<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SaveHistoricalDocument;
use App\Models\Employee;
use App\Models\HistoricalDocument;
use App\Models\User;
use App\Services\HistoricalDocumentAccess;
use Illuminate\Foundation\Http\FormRequest;

class SaveHistoricalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $document = $this->route('historical_document');
        $person = $this->route('employee');
        if ($document instanceof HistoricalDocument) {
            $person = $document->employee;
        }

        return $actor instanceof User && $person instanceof Employee && app(HistoricalDocumentAccess::class)->canAdminister($actor, $person);
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return SaveHistoricalDocument::rules(! $this->route('historical_document') instanceof HistoricalDocument);
    }
}
