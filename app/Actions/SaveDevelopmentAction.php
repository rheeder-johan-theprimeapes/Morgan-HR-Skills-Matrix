<?php

declare(strict_types=1);

namespace App\Actions;

use App\DevelopmentState;
use App\Models\AssessmentRevision;
use App\Models\AuditEvent;
use App\Models\DevelopmentAction;
use App\Models\DevelopmentRevision;
use App\Models\Employee;
use App\Models\PdrRelease;
use App\Models\PdrReview;
use App\Models\Skill;
use App\Models\User;
use App\Services\AssessmentAccess;
use App\Services\DevelopmentAccess;
use App\Services\EmployeeAccess;
use App\Services\EvidenceAvailability;
use App\Services\TargetResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Throwable;

class SaveDevelopmentAction
{
    public function __construct(private EmployeeAccess $people, private DevelopmentAccess $access, private AssessmentAccess $assessments, private TargetResolver $targets, private EvidenceAvailability $evidence) {}

    /** @return array<string,array<int,mixed>> */
    public static function rules(string $mode, bool $creating): array
    {
        $rules = ['mode' => ['required', Rule::in(['plan', 'progress'])], 'expected_revision' => ['required', 'integer', 'min:0'], 'employee_id' => ['prohibited'], 'created_by' => ['prohibited'], 'actor_id' => ['prohibited']];
        if ($mode === 'plan') {
            $rules += ['title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:8000'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'resource_label' => ['required_with:resource_url', 'nullable', 'string', 'max:180'], 'resource_url' => ['required_with:resource_label', 'nullable', 'url:http,https', 'max:2048'], 'state' => ['prohibited'], 'notes' => ['prohibited'], 'completed_on' => ['prohibited'], 'attachment' => ['prohibited']];
        } else {
            $rules += ['state' => ['required', Rule::enum(DevelopmentState::class)], 'notes' => ['nullable', 'string', 'max:8000'], 'completed_on' => ['required_if:state,completed', 'prohibited_unless:state,completed', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'reason' => ['nullable', 'string', 'max:4000'], 'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'], 'title' => ['prohibited'], 'description' => ['prohibited'], 'starts_on' => ['prohibited'], 'due_on' => ['prohibited'], 'resource_label' => ['prohibited'], 'resource_url' => ['prohibited']];
        }
        foreach (['origin_kind', 'origin_skill_id', 'origin_assessment_revision_id', 'origin_release_id', 'origin_objective_index', 'origin_objective'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        if ($creating && $mode === 'plan') {
            $rules['origin_objective'] = ['prohibited_unless:origin_kind,review', 'nullable', 'string', 'max:40', 'regex:/^[1-9]\d{0,17}:\d{1,2}$/D'];
            $rules['origin_kind'] = ['required', Rule::in(['manual', 'skill', 'review'])];
            $rules['origin_skill_id'] = ['required_if:origin_kind,skill', 'prohibited_unless:origin_kind,skill', 'nullable', 'integer', 'min:1'];
            $rules['origin_assessment_revision_id'] = ['prohibited_unless:origin_kind,skill', 'nullable', 'integer', 'min:1'];
            $rules['origin_release_id'] = ['required_if:origin_kind,review', 'prohibited_unless:origin_kind,review', 'nullable', 'integer', 'min:1'];
            $rules['origin_objective_index'] = ['required_if:origin_kind,review', 'prohibited_unless:origin_kind,review', 'nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    /** @param array<string,mixed> $input */
    public function handle(User $actor, Employee $employee, ?DevelopmentAction $action, array $input, ?UploadedFile $attachment = null): DevelopmentAction
    {
        $mode = is_string($input['mode'] ?? null) ? $input['mode'] : '';
        abort_unless($mode === 'plan' ? $this->access->canManage($actor, $employee) : $this->access->canView($actor, $employee), 403);
        $input['attachment'] = $attachment;
        $values = Validator::make($input, self::rules($mode, $action === null))->validate();
        if (isset($values['resource_url'])) {
            $url = parse_url($values['resource_url']);
            if (! is_array($url) || isset($url['user']) || isset($url['pass'])) {
                throw ValidationException::withMessages(['resource_url' => 'Use a public HTTP/HTTPS resource address without embedded credentials.']);
            }
        }
        $metadata = null;
        $storedPath = null;
        try {
            if ($attachment !== null) {
                $digest = hash_file('sha256', $attachment->getPathname());
                $size = $attachment->getSize();
                if (! is_string($digest) || ! is_int($size)) {
                    throw ValidationException::withMessages(['attachment' => 'The completion file could not be inspected.']);
                }
                $name = trim(substr(preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($attachment->getClientOriginalName())) ?? 'completion', 0, 180), '. ');
                $filename = $attachment->hashName();
                $storedPath = 'development/'.$filename;
                try {
                    $stored = Storage::disk('evidence')->putFileAs('development', $attachment, $filename);
                } catch (FilesystemException $storageFailure) {
                    report($storageFailure);
                    throw ValidationException::withMessages(['attachment' => 'The completion file could not be stored. Retry.']);
                }
                if (! is_string($stored)) {
                    throw ValidationException::withMessages(['attachment' => 'The completion file could not be stored. Retry.']);
                }
                $metadata = ['storage_path' => $storedPath, 'original_name' => $name === '' ? 'completion' : $name, 'mime_type' => $attachment->getMimeType() ?? 'application/octet-stream', 'byte_size' => $size, 'sha256' => $digest];
            }

            return DB::transaction(function () use ($actor, $employee, $action, $mode, $values, $metadata): DevelopmentAction {
                $account = $this->people->currentAccount($actor);
                if ($account === null) {
                    abort(403);
                }
                $person = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
                abort_unless($person->is_active && ($mode === 'plan' ? $this->access->canManage($account, $person) : $this->access->canView($account, $person)), 403);
                $head = $action === null ? null : DevelopmentAction::query()->whereKey($action->id)->where('employee_id', $person->id)->lockForUpdate()->firstOrFail();
                if (($head->revision ?? 0) !== (int) $values['expected_revision']) {
                    throw ValidationException::withMessages(['expected_revision' => 'This action changed. Your input is retained; reload before saving again.']);
                }
                $current = $head === null ? null : DevelopmentRevision::query()->whereKey($head->current_revision_id)->where('development_action_id', $head->id)->lockForUpdate()->firstOrFail();
                if ($head === null) {
                    abort_unless($mode === 'plan', 403);
                    $origin = $this->origin($account, $person, $values);
                    $head = DevelopmentAction::query()->create(['employee_id' => $person->id, 'revision' => 0, 'origin_kind' => $values['origin_kind'], 'origin_skill_id' => $values['origin_skill_id'] ?? null, 'origin_assessment_revision_id' => $values['origin_assessment_revision_id'] ?? null, 'origin_release_id' => $values['origin_release_id'] ?? null, 'origin_objective_index' => $values['origin_objective_index'] ?? null, 'origin_snapshot' => $origin, 'created_by' => $account->id]);
                }
                $body = $current === null ? ['state' => DevelopmentState::Planned, 'notes' => null, 'completed_on' => null] : $current->only(['title', 'description', 'starts_on', 'due_on', 'resource_label', 'resource_url', 'state', 'notes', 'completed_on', 'original_name', 'storage_path', 'mime_type', 'byte_size', 'sha256']);
                if ($mode === 'plan') {
                    if ($current !== null && in_array($current->state, [DevelopmentState::Completed, DevelopmentState::Cancelled], true)) {
                        throw ValidationException::withMessages(['state' => 'Reopen a terminal action with a reason before changing its plan.']);
                    }
                    foreach (['title', 'description', 'starts_on', 'due_on', 'resource_label', 'resource_url'] as $field) {
                        $body[$field] = $values[$field] ?? null;
                    }
                } else {
                    if ($current === null) {
                        abort(409);
                    }
                    $state = DevelopmentState::from($values['state']);
                    $terminal = in_array($current->state, [DevelopmentState::Completed, DevelopmentState::Cancelled], true);
                    if ($terminal || $state === DevelopmentState::Cancelled || ($current->state === DevelopmentState::InProgress && $state === DevelopmentState::Planned)) {
                        abort_unless($this->access->canManage($account, $person), 403);
                        Validator::make($values, ['reason' => ['required', 'string']])->validate();
                        if ($terminal && ! in_array($state, [DevelopmentState::Planned, DevelopmentState::InProgress], true)) {
                            throw ValidationException::withMessages(['state' => 'Reopen the terminal action before recording further progress.']);
                        }
                    }
                    $body['state'] = $state;
                    $body['notes'] = $values['notes'] ?? null;
                    $body['completed_on'] = $values['completed_on'] ?? null;
                    if ($metadata !== null) {
                        $body = array_replace($body, $metadata);
                    }
                    if ($state === DevelopmentState::Completed) {
                        Validator::make($values, ['completed_on' => ['after_or_equal:'.$current->starts_on->toDateString()]])->validate();
                        $hasFile = is_string($body['storage_path'] ?? null) && is_int($body['byte_size'] ?? null) && is_string($body['sha256'] ?? null) && $this->evidence->isIntactFile($body['storage_path'], $body['byte_size'], $body['sha256']);
                        if (trim($body['notes'] ?? '') === '' && ! $hasFile) {
                            throw ValidationException::withMessages(['notes' => 'Add completion evidence notes or an intact private attachment.']);
                        }
                    }
                }
                $revision = DevelopmentRevision::query()->create($body + ['development_action_id' => $head->id, 'revision' => $head->revision + 1, 'change_type' => $mode, 'reason' => $values['reason'] ?? null, 'actor_id' => $account->id]);
                $head->update(['revision' => $revision->revision, 'current_revision_id' => $revision->id]);
                AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'development.revision.saved', 'subject_type' => 'development_action', 'subject_id' => $head->id, 'details' => ['revision' => $revision->revision, 'change_type' => $mode]]);

                return $head;
            }, 3);
        } catch (Throwable $failure) {
            if ($storedPath !== null) {
                try {
                    if (! DevelopmentRevision::query()->where('storage_path', $storedPath)->exists()) {
                        Storage::disk('evidence')->delete($storedPath);
                    }
                } catch (Throwable $cleanupFailure) {
                    report($cleanupFailure);
                }
            }
            throw $failure;
        }
    }

    /**
     * @param  array<string,mixed>  $values
     * @return array<string,mixed>
     */
    private function origin(User $actor, Employee $person, array $values): array
    {
        if ($values['origin_kind'] === 'manual') {
            return ['kind' => 'manual', 'label' => 'Explicit development / leadership plan'];
        }
        if ($values['origin_kind'] === 'skill') {
            $skill = Skill::query()->whereKey($values['origin_skill_id'])->lockForUpdate()->first();
            if ($skill === null) {
                throw ValidationException::withMessages(['origin_skill_id' => 'Choose an available skill.']);
            }
            $skill->load(['group' => fn ($query) => $query->lockForUpdate(), 'departments' => fn ($query) => $query->lockForUpdate(), 'jobRoles' => fn ($query) => $query->lockForUpdate()]);
            $target = $this->targets->resolve($person, $skill);
            if (! $target->applicable) {
                throw ValidationException::withMessages(['origin_skill_id' => 'Choose an applicable active skill.']);
            }
            if (isset($values['origin_assessment_revision_id'])) {
                $revision = AssessmentRevision::query()->whereKey($values['origin_assessment_revision_id'])->lockForUpdate()->first();
                $assessment = $revision->assessment ?? null;
                if ($revision === null || $assessment === null || $assessment->employee_id !== $person->id || $assessment->skill_id !== $skill->id || ! $this->assessments->canView($actor, $assessment)) {
                    throw ValidationException::withMessages(['origin_assessment_revision_id' => 'Choose permitted assessment evidence for this employee and skill.']);
                }
            }

            return ['kind' => 'skill', 'label' => $skill->name, 'target_source' => $target->source];
        }
        $release = PdrRelease::query()->whereKey($values['origin_release_id'])->first();
        $review = $release === null ? null : PdrReview::query()->whereKey($release->pdr_review_id)->first();
        $goals = $release->summary['goals'] ?? [];
        $index = (int) $values['origin_objective_index'];
        if ($release === null || $review === null || $review->employee_id !== $person->id || ! isset($goals[$index])) {
            throw ValidationException::withMessages(['origin_release_id' => 'Choose a released objective belonging to this employee.']);
        }

        return ['kind' => 'review', 'label' => $goals[$index]['description']];
    }
}
