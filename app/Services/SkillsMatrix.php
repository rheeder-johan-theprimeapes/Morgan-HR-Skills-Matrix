<?php

declare(strict_types=1);

namespace App\Services;

use App\AssessmentKind;
use App\AssessmentState;
use App\Models\Assessment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\SkillTarget;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SkillsMatrix
{
    public function __construct(private EmployeeAccess $people, private AssessmentAccess $assessments, private TargetResolver $targets) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'department_id' => ['nullable', 'integer', 'min:1'], 'job_role_id' => ['nullable', 'integer', 'min:1'], 'employee_id' => ['nullable', 'integer', 'min:1'], 'skill_group_id' => ['nullable', 'integer', 'min:1'], 'skill_id' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', Rule::in(['active', 'retired', 'all'])], 'page' => ['nullable', 'integer', 'between:1,100000'], 'skill_page' => ['nullable', 'integer', 'between:1,100000'], 'sort' => ['prohibited']];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function page(User $user, array $filters): array
    {
        $filters = Validator::make($filters, self::rules())->validate();
        $account = $this->people->currentAccount($user);
        if ($account === null || ! $account->hasNativeAccess()) {
            abort(403);
        }
        $scope = $this->people->query($account);
        $query = (clone $scope)->with(['department', 'jobRole', 'manager']);
        $status = $filters['status'] ?? 'active';
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }
        foreach (['department_id', 'job_role_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['employee_id'])) {
            $query->whereKey($filters['employee_id']);
        }
        if (! empty($filters['search'])) {
            $query->where(fn ($names) => $names->where('name', 'like', '%'.$filters['search'].'%')->orWhere('employee_number', 'like', '%'.$filters['search'].'%'));
        }
        $people = $query->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();
        $skillQuery = Skill::query()->join('skill_groups', 'skills.skill_group_id', '=', 'skill_groups.id')->select('skills.*')->where('skills.is_active', true)->where('skill_groups.is_active', true)->with(['group', 'departments', 'jobRoles']);
        if (! empty($filters['skill_group_id'])) {
            $skillQuery->where('skills.skill_group_id', $filters['skill_group_id']);
        }
        if (! empty($filters['skill_id'])) {
            $skillQuery->where('skills.id', $filters['skill_id']);
        }
        $skills = $skillQuery->orderBy('skill_groups.display_order')->orderBy('skill_groups.id')->orderBy('skills.name')->orderBy('skills.id')->paginate(12, ['skills.*'], 'skill_page')->withQueryString();
        $personIds = $people->getCollection()->map(fn (Employee $person): int => $person->id)->all();
        $skillIds = $skills->getCollection()->map(fn (Skill $skill): int => $skill->id)->all();
        $departmentIds = $people->getCollection()->pluck('department_id')->filter()->unique()->all();
        $roleIds = $people->getCollection()->pluck('job_role_id')->filter()->unique()->all();
        $targets = SkillTarget::query()->whereIn('skill_id', $skillIds)->where('is_active', true)->where(fn ($targetScope) => $targetScope->whereIn('employee_id', $personIds)->orWhereIn('department_id', $departmentIds)->orWhereIn('job_role_id', $roleIds))->with('level')->orderBy('id')->get();
        $supervised = $this->assessments->supervisedIds($account);
        $selfIds = [$account->employee_id, ...$supervised];
        $heads = Assessment::query()->whereIn('employee_id', $personIds)->whereIn('skill_id', $skillIds)->where(function ($ratingScope) use ($selfIds, $supervised): void {
            $ratingScope->where(fn ($self) => $self->where('kind', AssessmentKind::Self->value)->whereIn('employee_id', $selfIds))->orWhere(fn ($manager) => $manager->where('kind', AssessmentKind::Manager->value)->whereIn('employee_id', $supervised));
        })->with(['currentRevision' => fn ($revision) => $revision->select(['id', 'state', 'snapshot', 'assessed_on'])])->get()->keyBy(fn (Assessment $head): string => $head->employee_id.':'.$head->skill_id.':'.$head->kind->value);
        $coverage = ['applicable' => 0, 'self_rated' => 0, 'self_eligible' => 0, 'manager_rated' => 0, 'manager_eligible' => 0, 'self_na' => 0, 'manager_na' => 0];
        $rows = [];
        foreach ($people as $person) {
            $cells = [];
            foreach ($skills as $skill) {
                $target = $this->targets->resolveFrom($person, $skill, $targets);
                $selfAllowed = in_array($person->id, $selfIds, true);
                $managerAllowed = in_array($person->id, $supervised, true);
                $self = $this->rating($heads->get($person->id.':'.$skill->id.':self'), $selfAllowed);
                $manager = $this->rating($heads->get($person->id.':'.$skill->id.':manager'), $managerAllowed);
                $selfGap = $target->ordinal !== null && $self['state'] === 'rated' && $self['ordinal'] !== null ? max(0, $target->ordinal - $self['ordinal']) : null;
                $managerGap = $target->ordinal !== null && $manager['state'] === 'rated' && $manager['ordinal'] !== null ? max(0, $target->ordinal - $manager['ordinal']) : null;
                if ($target->applicable) {
                    $coverage['applicable']++;
                    foreach (['self' => $self, 'manager' => $manager] as $kind => $rating) {
                        if ($rating['state'] !== 'restricted') {
                            $coverage[$kind.'_eligible']++;
                        }
                        if ($rating['state'] === 'rated') {
                            $coverage[$kind.'_rated']++;
                        }
                        if ($rating['state'] === 'not_applicable') {
                            $coverage[$kind.'_na']++;
                        }
                    }
                }
                $cells[] = ['skill_id' => $skill->id, 'applicable' => $target->applicable, 'target' => $target->toArray(), 'self' => $target->applicable ? $self : ['state' => 'not_applicable_to_duties', 'ordinal' => null, 'label' => 'Not applicable to duties'], 'manager' => $target->applicable ? $manager : ['state' => 'not_applicable_to_duties', 'ordinal' => null, 'label' => 'Not applicable to duties'], 'self_gap' => $target->applicable ? $selfGap : null, 'manager_gap' => $target->applicable ? $managerGap : null];
            }
            $rows[$person->id] = EmployeeProfile::fromEmployee($person) + ['cells' => $cells, 'can_assess' => in_array($person->id, $selfIds, true)];
        }
        $people->through(fn (Employee $person): array => $rows[$person->id]);
        $groups = [];
        foreach ($skills as $skill) {
            $id = $skill->skill_group_id;
            $groups[$id] ??= ['name' => $skill->group?->name, 'count' => 0];
            $groups[$id]['count']++;
        }

        return ['people' => $people, 'skills' => $skills, 'groups' => array_values($groups), 'coverage' => $coverage, 'filters' => $filters,
            'departments' => Department::query()->whereIn('id', (clone $scope)->select('department_id'))->orderBy('name')->get(['id', 'name']),
            'jobRoles' => JobRole::query()->whereIn('id', (clone $scope)->select('job_role_id'))->orderBy('name')->get(['id', 'name']),
            'skillGroups' => SkillGroup::query()->where('is_active', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name'])];
    }

    /** @return array{state: string, ordinal: int|null, label: string} */
    private function rating(?Assessment $head, bool $allowed): array
    {
        if (! $allowed) {
            return ['state' => 'restricted', 'ordinal' => null, 'label' => 'Restricted'];
        }
        $revision = $head?->currentRevision;
        if ($revision === null || $revision->state === AssessmentState::Unassessed) {
            return ['state' => 'unassessed', 'ordinal' => null, 'label' => 'Unassessed'];
        }
        if ($revision->state === AssessmentState::NotApplicable) {
            return ['state' => 'not_applicable', 'ordinal' => null, 'label' => 'Not applicable (N/A)'];
        }
        $ordinal = $revision->snapshot['ordinal'] ?? null;
        if (! is_int($ordinal)) {
            return ['state' => 'invalid', 'ordinal' => null, 'label' => 'Needs HR attention'];
        }

        return ['state' => 'rated', 'ordinal' => $ordinal, 'label' => 'Assessed level '.$ordinal];
    }
}
