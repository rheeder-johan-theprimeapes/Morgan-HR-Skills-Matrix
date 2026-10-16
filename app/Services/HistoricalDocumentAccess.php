<?php

declare(strict_types=1);

namespace App\Services;

use App\Capability;
use App\DocumentVisibility;
use App\Models\Employee;
use App\Models\HistoricalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class HistoricalDocumentAccess
{
    public function __construct(private EmployeeAccess $people, private ReportingTree $tree) {}

    public function canAdminister(User $user, Employee $employee): bool
    {
        $actor = $this->people->currentAccount($user);

        return $actor !== null && $actor->hasNativeAccess() && $actor->hasCapability(Capability::AdministerReviews) && $actor->hasCapability(Capability::ActualPay) && $actor->hasCapability(Capability::SensitiveContext) && $this->people->canView($actor, $employee);
    }

    /** @return Builder<HistoricalDocument> */
    public function query(User $user, Employee $employee): Builder
    {
        $query = HistoricalDocument::query()->where('employee_id', $employee->id);
        if ($this->canAdminister($user, $employee)) {
            return $query;
        }
        $actor = $this->people->currentAccount($user);
        if ($actor === null || ! $actor->hasNativeAccess()) {
            return $query->whereRaw('1 = 0');
        }
        if ($actor->employee_id === $employee->id) {
            return $query->where('visibility', DocumentVisibility::EmployeeShared);
        }
        if ($actor->employee !== null && in_array($employee->id, $this->tree->descendantIds($actor->employee), true)) {
            return $query->whereIn('visibility', [DocumentVisibility::Supervisors, DocumentVisibility::EmployeeShared]);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canList(User $user, Employee $employee): bool
    {
        $actor = $this->people->currentAccount($user);

        return $actor !== null && $actor->hasNativeAccess() && ($actor->employee_id === $employee->id || $actor->employee !== null && in_array($employee->id, $this->tree->descendantIds($actor->employee), true) || $this->canAdminister($actor, $employee));
    }

    public function canView(User $user, HistoricalDocument $document): bool
    {
        $employee = $document->employee;

        return $employee !== null && $this->query($user, $employee)->whereKey($document->id)->exists();
    }

    /** @return array<string,mixed> */
    public function project(HistoricalDocument $document): array
    {
        return ['id' => $document->id, 'employee_id' => $document->employee_id, 'title' => $document->title, 'document_date' => $document->document_date->toDateString(), 'source' => $document->source, 'original_name' => $document->original_name, 'byte_size' => $document->byte_size, 'visibility' => $document->visibility->value, 'visibility_label' => $document->visibility->label(), 'revision' => $document->revision, 'uploaded_at' => $document->created_at?->toDateTimeString()];
    }
}
