<?php

declare(strict_types=1);

namespace App\Actions;

use App\AssessmentKind;
use App\AssessmentState;
use App\Models\Assessment;
use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\EvidenceAttachment;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\User;
use App\Services\EmployeeAccess;
use App\Services\TargetResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SaveAssessment
{
    public function __construct(private EmployeeAccess $people, private TargetResolver $targets) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(AssessmentKind::class)], 'state' => ['required', Rule::enum(AssessmentState::class)],
            'skill_level_id' => ['nullable', 'integer', 'exists:skill_levels,id'], 'assessed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:8000'], 'evidence_notes' => ['nullable', 'string', 'max:8000'], 'expected_revision' => ['required', 'integer', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Employee $employee, Skill $skill, array $data, ?UploadedFile $attachment = null): Assessment
    {
        $data['attachment'] = $attachment;
        $values = Validator::make($data, self::rules())->validate();
        $kind = AssessmentKind::from($values['kind']);
        $state = AssessmentState::from($values['state']);
        Gate::forUser($actor->fresh())->authorize('create', [Assessment::class, $employee, $kind]);
        $storedPath = null;
        $metadata = null;
        if ($attachment !== null) {
            $digest = hash_file('sha256', $attachment->getPathname());
            $size = $attachment->getSize();
            if (! is_string($digest) || ! is_int($size)) {
                throw new RuntimeException('Unable to inspect evidence.');
            }
            $name = trim(substr(preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($attachment->getClientOriginalName())) ?? 'evidence', 0, 180), '. ');
            $storedPath = $attachment->store('', 'evidence');
            if (! is_string($storedPath)) {
                throw new RuntimeException('Unable to store evidence. Please retry.');
            }
            $metadata = ['original_name' => $name === '' ? 'evidence' : $name, 'storage_path' => $storedPath, 'mime_type' => $attachment->getMimeType() ?? 'application/octet-stream', 'byte_size' => $size, 'sha256' => $digest];
        }
        try {
            return DB::transaction(function () use ($actor, $employee, $skill, $values, $kind, $state, $metadata): Assessment {
                $account = $this->people->currentAccount($actor);
                if ($account === null) {
                    abort(403);
                }
                $person = Employee::query()->lockForUpdate()->findOrFail($employee->id);
                Gate::forUser($account)->authorize('create', [Assessment::class, $person, $kind]);
                $record = Skill::query()->lockForUpdate()->findOrFail($skill->id);
                $record->load(['group' => fn ($query) => $query->lockForUpdate(), 'departments' => fn ($query) => $query->lockForUpdate(), 'jobRoles' => fn ($query) => $query->lockForUpdate()]);
                $resolved = $this->targets->resolve($person, $record);
                if (! $resolved->applicable) {
                    throw ValidationException::withMessages(['skill' => 'This skill is not currently applicable.']);
                }
                $level = null;
                if ($state === AssessmentState::Rated) {
                    $level = SkillLevel::query()->whereKey($values['skill_level_id'] ?? null)->where('skill_id', $record->id)->where('is_active', true)->lockForUpdate()->first();
                    if ($level === null) {
                        throw ValidationException::withMessages(['skill_level_id' => 'Choose an active rating definition for this skill.']);
                    }
                    if ($record->requires_evidence && trim($values['evidence_notes'] ?? '') === '' && $metadata === null) {
                        throw ValidationException::withMessages(['evidence_notes' => 'Add dated evidence notes or an attachment for this rating.']);
                    }
                } elseif (($values['skill_level_id'] ?? null) !== null) {
                    throw ValidationException::withMessages(['skill_level_id' => 'N/A and unassessed states cannot contain a rating.']);
                }
                if ($state === AssessmentState::NotApplicable && (! $record->allows_na || trim($values['comment'] ?? '') === '')) {
                    throw ValidationException::withMessages(['comment' => 'N/A must be permitted for this skill and include a reason.']);
                }
                $head = Assessment::query()->where('employee_id', $person->id)->where('skill_id', $record->id)->where('kind', $kind->value)->lockForUpdate()->first();
                if (($head->revision ?? 0) !== (int) $values['expected_revision']) {
                    throw ValidationException::withMessages(['expected_revision' => 'This assessment changed since you opened it. Keep your text, reload the latest revision and compare before saving.']);
                }
                $head ??= Assessment::query()->create(['employee_id' => $person->id, 'skill_id' => $record->id, 'kind' => $kind, 'revision' => 0]);
                $revision = $head->revisions()->create([
                    'revision' => $head->revision + 1, 'actor_id' => $account->id, 'assessed_on' => $values['assessed_on'], 'state' => $state,
                    'skill_level_id' => $level?->id, 'comment' => $values['comment'] ?? null, 'evidence_notes' => $values['evidence_notes'] ?? null,
                    'snapshot' => ['skill_id' => $record->id, 'skill_code' => $record->code, 'skill_name' => $record->name, 'group_name' => $record->group?->name, 'ordinal' => $level?->ordinal, 'rating_label' => $level?->label, 'rating_guidance' => $level?->guidance, 'requires_evidence' => $record->requires_evidence, 'target' => $resolved->toArray()],
                ]);
                if ($metadata !== null) {
                    $revision->attachments()->create($metadata + ['uploaded_by' => $account->id]);
                }
                $head->forceFill(['revision' => $revision->revision, 'current_revision_id' => $revision->id])->save();
                AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'assessment.revision.created', 'subject_type' => 'assessment_revision', 'subject_id' => $revision->id, 'details' => ['employee_id' => $person->id, 'skill_id' => $record->id, 'kind' => $kind->value, 'revision' => $revision->revision]]);

                return $head;
            }, 3);
        } catch (Throwable $failure) {
            if ($storedPath !== null) {
                try {
                    if (! EvidenceAttachment::query()->where('storage_path', $storedPath)->exists()) {
                        Storage::disk('evidence')->delete($storedPath);
                    }
                } catch (Throwable $cleanupFailure) {
                    report($cleanupFailure);
                }
            }
            throw $failure;
        }
    }
}
