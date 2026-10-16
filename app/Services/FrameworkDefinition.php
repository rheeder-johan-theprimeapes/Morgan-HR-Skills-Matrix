<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ScoringFramework;
use App\Models\Skill;
use App\Models\SkillGroup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type FrameworkLevel array{level_id:int, ordinal:int, label:string, points:int}
 * @phpstan-type FrameworkSkill array{skill_id:int, name:string, allows_na:bool, required:bool, requires_evidence:bool, department_ids:list<int>, job_role_ids:list<int>, levels:list<FrameworkLevel>}
 * @phpstan-type FrameworkComponent array{group_id:int, name:string, weight:int, skills:list<FrameworkSkill>}
 * @phpstan-type InputLevel array{level_id:int, points:string}
 * @phpstan-type InputSkill array{skill_id:int, included:bool, required:bool, requires_evidence:bool, levels:list<InputLevel>}
 * @phpstan-type FrameworkInput array{components:list<array{group_id:int,weight:int,skills:list<InputSkill>}>, bands:list<array{label:string,from:string,to:string}>}
 * @phpstan-type FrameworkConfig array{components:list<FrameworkComponent>, bands:list<array{label:string, from:int, to:int}>}
 */
class FrameworkDefinition
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'components' => ['present', 'array', 'max:50'], 'components.*' => ['array:group_id,weight,skills'],
            'components.*.group_id' => ['required', 'integer', 'distinct'], 'components.*.weight' => ['required', 'integer', 'between:1,10000'],
            'components.*.skills' => ['present', 'array', 'max:200'], 'components.*.skills.*' => ['array:skill_id,included,required,requires_evidence,levels'],
            'components.*.skills.*.skill_id' => ['required', 'integer', 'distinct'], 'components.*.skills.*.included' => ['required', 'boolean'],
            'components.*.skills.*.required' => ['required', 'boolean'], 'components.*.skills.*.requires_evidence' => ['required', 'boolean'],
            'components.*.skills.*.levels' => ['sometimes', 'array', 'max:101'], 'components.*.skills.*.levels.*' => ['array:level_id,points'],
            'components.*.skills.*.levels.*.level_id' => ['required', 'integer', 'distinct'],
            'components.*.skills.*.levels.*.points' => ['required', 'regex:/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/'],
            'bands' => ['present', 'array', 'max:10'], 'bands.*' => ['array:label,from,to'], 'bands.*.label' => ['nullable', 'string', 'max:120'],
            'bands.*.from' => ['nullable', 'regex:/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/'],
            'bands.*.to' => ['nullable', 'regex:/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return FrameworkConfig
     */
    public function canonicalize(array $input, bool $publishing): array
    {
        $values = Validator::make($input, self::rules())->validate();
        $components = [];
        foreach ($values['components'] as $component) {
            $skills = [];
            $group = SkillGroup::query()->whereKey($component['group_id'])->lockForUpdate()->first();
            foreach ($component['skills'] as $selection) {
                if (! $selection['included']) {
                    continue;
                }
                $skill = Skill::query()->whereKey($selection['skill_id'])->lockForUpdate()->first();
                if ($group === null || $skill === null || $skill->skill_group_id !== $group->id || ($publishing && (! $skill->is_active || ! $group->is_active))) {
                    $this->invalid('A selected skill/group is missing, retired or mismatched.');
                }
                $available = $skill->levels()->lockForUpdate()->get()->keyBy('id');
                $levels = [];
                foreach (($selection['levels'] ?? []) as $mapping) {
                    $level = $available->get((int) $mapping['level_id']);
                    if ($level === null || ($publishing && ! $level->is_active)) {
                        $this->invalid('Choose active rating definitions belonging to each selected skill.');
                    }
                    $levels[] = ['level_id' => $level->id, 'ordinal' => $level->ordinal, 'label' => $level->label, 'points' => $this->hundredths((string) $mapping['points'])];
                }
                usort($levels, fn (array $a, array $b): int => $a['ordinal'] <=> $b['ordinal']);
                if ($publishing) {
                    if (count($levels) < 2 || count($levels) !== $available->where('is_active', true)->count()) {
                        $this->invalid('Map every active level, with at least two levels per skill.');
                    }
                    $previous = -1;
                    foreach ($levels as $level) {
                        if ($level['points'] <= $previous) {
                            $this->invalid('Rating points must increase strictly with rating ordinal.');
                        }
                        $previous = $level['points'];
                    }
                }
                $departments = $skill->departments()->lockForUpdate()->pluck('departments.id')->map(fn (int $id): int => $id)->sort()->values()->all();
                $roles = $skill->jobRoles()->lockForUpdate()->pluck('job_roles.id')->map(fn (int $id): int => $id)->sort()->values()->all();
                $skills[] = ['skill_id' => $skill->id, 'name' => $skill->name, 'allows_na' => $skill->allows_na, 'required' => (bool) $selection['required'], 'requires_evidence' => (bool) $selection['requires_evidence'], 'department_ids' => array_values($departments), 'job_role_ids' => array_values($roles), 'levels' => $levels];
            }
            usort($skills, fn (array $a, array $b): int => $a['skill_id'] <=> $b['skill_id']);
            if ($skills !== [] && $group !== null) {
                $components[] = ['group_id' => $group->id, 'name' => $group->name, 'weight' => (int) $component['weight'], 'skills' => $skills];
            }
        }
        usort($components, fn (array $a, array $b): int => $a['group_id'] <=> $b['group_id']);
        $bands = [];
        foreach ($values['bands'] as $band) {
            if (trim((string) ($band['label'] ?? '')) === '' && ($band['from'] ?? null) === null && ($band['to'] ?? null) === null) {
                continue;
            }
            if (trim((string) ($band['label'] ?? '')) === '' || ($band['from'] ?? null) === null || ($band['to'] ?? null) === null) {
                $this->invalid('Every band needs a name and both edges.');
            }
            $bands[] = ['label' => trim($band['label']), 'from' => $this->hundredths((string) $band['from']), 'to' => $this->hundredths((string) $band['to'])];
        }
        usort($bands, fn (array $a, array $b): int => $a['from'] <=> $b['from']);
        if ($publishing) {
            if ($components === [] || $bands === []) {
                $this->invalid('Include at least one skill and a complete set of score bands.');
            }
            $edge = 0;
            foreach ($bands as $band) {
                if ($band['from'] !== $edge || $band['to'] <= $band['from']) {
                    $this->invalid('Bands must cover 0.00–100.00 without gaps or overlaps.');
                }
                $edge = $band['to'];
            }
            if ($edge !== 10000) {
                $this->invalid('The final band must end at 100.00.');
            }
        }

        return ['components' => $components, 'bands' => $bands];
    }

    public function hundredths(string $number): int
    {
        $parts = explode('.', $number);

        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public function display(int $hundredths): string
    {
        return number_format($hundredths / 100, 2, '.', '');
    }

    /**
     * @param  FrameworkConfig  $config
     * @return FrameworkInput
     */
    public function toInput(array $config): array
    {
        $components = [];
        foreach ($config['components'] as $component) {
            $skills = [];
            foreach ($component['skills'] as $skill) {
                $levels = [];
                foreach ($skill['levels'] as $level) {
                    $levels[] = ['level_id' => $level['level_id'], 'points' => $this->display($level['points'])];
                }
                $skills[] = ['skill_id' => $skill['skill_id'], 'included' => true, 'required' => $skill['required'], 'requires_evidence' => $skill['requires_evidence'], 'levels' => $levels];
            }
            $components[] = ['group_id' => $component['group_id'], 'weight' => $component['weight'], 'skills' => $skills];
        }

        return ['components' => $components, 'bands' => array_map(fn (array $band): array => ['label' => $band['label'], 'from' => $this->display($band['from']), 'to' => $this->display($band['to'])], $config['bands'])];
    }

    /** @return array<string, mixed> */
    public function editor(?ScoringFramework $framework): array
    {
        $existing = $framework === null ? ['components' => [], 'bands' => []] : $this->toInput($framework->definition);
        $oldGroups = collect($existing['components'])->keyBy('group_id');
        $groups = SkillGroup::query()->with(['skills.levels', 'skills.departments', 'skills.jobRoles'])->orderBy('id')->get();
        $components = [];
        foreach ($groups as $group) {
            $oldGroup = $oldGroups->get($group->id);
            $oldSkills = collect($oldGroup['skills'] ?? [])->keyBy('skill_id');
            $skills = [];
            foreach ($group->skills as $skill) {
                $oldSkill = $oldSkills->get($skill->id);
                if ((! $group->is_active || ! $skill->is_active) && $oldSkill === null) {
                    continue;
                }
                $oldLevels = collect($oldSkill['levels'] ?? [])->keyBy('level_id');
                $levels = [];
                $activeLevels = $skill->levels->where('is_active', true);
                $maximum = max(1, (int) $activeLevels->max('ordinal'));
                foreach ($skill->levels as $level) {
                    if (! $level->is_active) {
                        continue;
                    }
                    $levels[] = ['level_id' => $level->id, 'label' => $level->label, 'ordinal' => $level->ordinal, 'points' => $oldLevels->get($level->id)['points'] ?? $this->display((int) round($level->ordinal / $maximum * 10000)), 'is_active' => $level->is_active];
                }
                $skills[] = ['skill_id' => $skill->id, 'name' => $skill->name, 'included' => $oldSkill !== null || $framework === null, 'required' => $oldSkill['required'] ?? true, 'requires_evidence' => $oldSkill['requires_evidence'] ?? $skill->requires_evidence, 'levels' => $levels, 'is_active' => $skill->is_active];
            }
            if ($skills !== []) {
                $components[] = ['group_id' => $group->id, 'name' => $group->name, 'weight' => $oldGroup['weight'] ?? 1, 'skills' => $skills];
            }
        }

        return ['components' => $components, 'bands' => $existing['bands'] ?: [['label' => 'Illustrative developing', 'from' => '0.00', 'to' => '50.00'], ['label' => 'Illustrative established', 'from' => '50.00', 'to' => '100.00']]];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['definition' => $message]);
    }
}
