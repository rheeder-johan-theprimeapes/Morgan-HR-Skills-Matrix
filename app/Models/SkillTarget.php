<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SkillTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['skill_id', 'skill_level_id', 'department_id', 'job_role_id', 'employee_id', 'approved_by', 'reason', 'is_active'])]
class SkillTarget extends Model
{
    /** @use HasFactory<SkillTargetFactory> */
    use HasFactory;

    /** @return BelongsTo<Skill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /** @return BelongsTo<SkillLevel, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(SkillLevel::class, 'skill_level_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<JobRole, $this> */
    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    /** @return array{is_active: 'boolean'} */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
