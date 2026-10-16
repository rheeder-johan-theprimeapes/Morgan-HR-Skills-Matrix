<?php

declare(strict_types=1);

namespace App\Services;

use App\DevelopmentState;
use App\Models\DevelopmentAction;
use App\Models\DevelopmentRevision;
use App\Models\Employee;
use App\Models\User;

class DevelopmentAccess
{
    public function __construct(private EmployeeAccess $people, private AssessmentAccess $assessments) {}

    public function canManage(User $user, Employee $employee): bool
    {
        return $this->assessments->canSupervise($user, $employee);
    }

    public function canView(User $user, Employee $employee): bool
    {
        $actor = $this->people->currentAccount($user);

        return $actor !== null && $actor->hasNativeAccess() && ($actor->employee_id === $employee->id || $this->canManage($actor, $employee));
    }

    /** @return array<string,mixed> */
    public function project(User $actor, DevelopmentAction $action): array
    {
        $head = DevelopmentAction::query()->whereKey($action->id)->with(['employee', 'currentRevision.actor'])->firstOrFail();
        $person = $head->employee;
        $current = $head->currentRevision;
        if ($person === null || $current === null) {
            abort(404);
        }
        abort_unless($this->canView($actor, $person), 403);

        return ['id' => $head->id, 'employee_id' => $person->id, 'employee_name' => $person->name, 'revision' => $head->revision, 'origin' => array_intersect_key($head->origin_snapshot, array_flip(['kind', 'label', 'target_source'])), 'can_manage' => $person->is_active && $this->canManage($actor, $person), 'can_progress' => $person->is_active, 'current' => $this->revisionData($current)];
    }

    /** @return array<string,mixed> */
    public function revisionData(DevelopmentRevision $revision): array
    {
        return ['actor' => $revision->actor->name ?? 'Unavailable account', 'id' => $revision->id, 'revision' => $revision->revision, 'change_type' => $revision->change_type, 'title' => $revision->title, 'description' => $revision->description, 'starts_on' => $revision->starts_on->toDateString(), 'due_on' => $revision->due_on->toDateString(), 'resource_label' => $revision->resource_label, 'resource_url' => $revision->resource_url, 'state' => $revision->state->value, 'notes' => $revision->notes, 'completed_on' => $revision->completed_on?->toDateString(), 'reason' => $revision->reason, 'file_name' => $revision->original_name, 'has_file' => $revision->storage_path !== null, 'recorded_at' => $revision->created_at?->toDateTimeString(), 'overdue' => $revision->due_on->isBefore(today()) && ! in_array($revision->state, [DevelopmentState::Completed, DevelopmentState::Cancelled], true)];
    }
}
