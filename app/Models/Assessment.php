<?php

declare(strict_types=1);

namespace App\Models;

use App\AssessmentKind;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee_id', 'skill_id', 'kind', 'revision', 'current_revision_id'])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Skill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /** @return BelongsTo<AssessmentRevision, $this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(AssessmentRevision::class, 'current_revision_id');
    }

    /** @return HasMany<AssessmentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(AssessmentRevision::class)->orderByDesc('revision');
    }

    /** @return array{kind: 'App\AssessmentKind', revision: 'integer'} */
    protected function casts(): array
    {
        return ['kind' => AssessmentKind::class, 'revision' => 'integer'];
    }
}
