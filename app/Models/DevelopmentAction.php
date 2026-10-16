<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DevelopmentActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['employee_id', 'revision', 'current_revision_id', 'origin_kind', 'origin_skill_id', 'origin_assessment_revision_id', 'origin_release_id', 'origin_objective_index', 'origin_snapshot', 'created_by'])]
#[Hidden(['origin_snapshot', 'origin_assessment_revision_id'])]
class DevelopmentAction extends Model
{
    /** @use HasFactory<DevelopmentActionFactory> */
    use HasFactory;

    /** @return BelongsTo<Employee,$this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<DevelopmentRevision,$this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRevision::class, 'current_revision_id');
    }

    /** @return HasMany<DevelopmentRevision,$this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(DevelopmentRevision::class)->orderByDesc('revision');
    }

    protected static function booted(): void
    {
        static::updating(function (self $head): void {
            if ($head->isDirty(['employee_id', 'origin_kind', 'origin_skill_id', 'origin_assessment_revision_id', 'origin_release_id', 'origin_objective_index', 'origin_snapshot', 'created_by'])) {
                throw new LogicException('Development ownership and origin are immutable.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Development history cannot be deleted.');
        });
    }

    /** @return array{revision:'integer',origin_snapshot:'array',origin_objective_index:'integer'} */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'origin_snapshot' => 'array', 'origin_objective_index' => 'integer'];
    }
}
