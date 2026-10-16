<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'skill_group_id', 'allows_na', 'requires_evidence', 'is_active'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /** @return BelongsTo<SkillGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SkillGroup::class, 'skill_group_id');
    }

    /** @return HasMany<SkillLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(SkillLevel::class)->orderBy('ordinal');
    }

    /** @return BelongsToMany<Department, $this> */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /** @return BelongsToMany<JobRole, $this> */
    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class);
    }

    /** @return array{is_active: 'boolean', allows_na: 'boolean', requires_evidence: 'boolean'} */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'allows_na' => 'boolean', 'requires_evidence' => 'boolean'];
    }
}
