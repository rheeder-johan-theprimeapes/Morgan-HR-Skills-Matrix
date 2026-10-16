<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\ScoringFramework;
use App\Models\User;
use App\Services\EmployeeAccess;
use App\Services\FrameworkDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveFramework
{
    public function __construct(private EmployeeAccess $access, private FrameworkDefinition $definitions) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return ['name' => ['required', 'string', 'max:180'], 'illustrative' => ['required', 'boolean'], 'approval_reference' => ['nullable', 'string', 'max:1000'], 'expected_revision' => ['required', 'integer', 'min:0']] + FrameworkDefinition::rules();
    }

    /** @param array<string, mixed> $input */
    public function handle(User $actor, ?ScoringFramework $framework, array $input): ScoringFramework
    {
        return DB::transaction(function () use ($actor, $framework, $input): ScoringFramework {
            $account = $this->access->currentAccount($actor);
            Gate::forUser($account)->authorize('manage-skills');
            $values = Validator::make($input, self::rules())->validate();
            $record = $framework === null ? new ScoringFramework : ScoringFramework::query()->lockForUpdate()->findOrFail($framework->id);
            $this->assertDraft($record, (int) $values['expected_revision']);
            $record->fill(['name' => $values['name'], 'illustrative' => $values['illustrative'], 'approval_reference' => $values['approval_reference'] ?? null, 'definition' => $this->definitions->canonicalize($values, false), 'revision' => ($record->revision ?? 0) + 1]);
            if (! $record->exists) {
                $record->created_by = $actor->id;
            }
            $record->save();
            $this->audit($actor, $record, 'framework.draft.saved');

            return $record;
        }, 3);
    }

    public function publish(User $actor, ScoringFramework $framework, int $expectedRevision): ScoringFramework
    {
        return DB::transaction(function () use ($actor, $framework, $expectedRevision): ScoringFramework {
            Gate::forUser($this->access->currentAccount($actor))->authorize('manage-skills');
            $record = ScoringFramework::query()->lockForUpdate()->findOrFail($framework->id);
            $this->assertDraft($record, $expectedRevision);
            if (! $record->illustrative && trim($record->approval_reference ?? '') === '') {
                throw ValidationException::withMessages(['approval_reference' => 'Record the HR approval reference before live publication.']);
            }
            $record->definition = $this->definitions->canonicalize($this->definitions->toInput($record->definition), true);
            $record->published_at = CarbonImmutable::now();
            $record->published_by = $actor->id;
            $record->revision++;
            $record->save();
            $this->audit($actor, $record, 'framework.published');

            return $record;
        }, 3);
    }

    public function successor(User $actor, ScoringFramework $framework): ScoringFramework
    {
        return DB::transaction(function () use ($actor, $framework): ScoringFramework {
            Gate::forUser($this->access->currentAccount($actor))->authorize('manage-skills');
            $source = ScoringFramework::query()->lockForUpdate()->findOrFail($framework->id);
            abort_if($source->published_at === null, 409, 'Publish this draft before creating a successor.');
            $record = ScoringFramework::query()->create(['predecessor_id' => $source->id, 'name' => $source->name, 'illustrative' => $source->illustrative, 'approval_reference' => null, 'revision' => 1, 'definition' => $source->definition, 'created_by' => $actor->id]);
            $this->audit($actor, $record, 'framework.successor.created');

            return $record;
        }, 3);
    }

    private function assertDraft(ScoringFramework $record, int $expectedRevision): void
    {
        abort_if($record->published_at !== null, 409, 'Published frameworks are immutable. Create a successor.');
        if (($record->revision ?? 0) !== $expectedRevision) {
            throw ValidationException::withMessages(['expected_revision' => 'This draft changed. Reload it before saving or publishing.']);
        }
    }

    private function audit(User $actor, ScoringFramework $framework, string $event): void
    {
        AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => $event, 'subject_type' => 'scoring_framework', 'subject_id' => $framework->id, 'details' => ['revision' => $framework->revision, 'predecessor_id' => $framework->predecessor_id, 'illustrative' => $framework->illustrative]]);
    }
}
