<?php

declare(strict_types=1);

namespace App\Models;

use App\AssessmentState;
use Database\Factories\AssessmentRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['assessment_id', 'revision', 'actor_id', 'assessed_on', 'state', 'skill_level_id', 'comment', 'evidence_notes', 'snapshot'])]
#[Hidden(['snapshot', 'comment', 'evidence_notes'])]
class AssessmentRevision extends Model
{
    /** @use HasFactory<AssessmentRevisionFactory> */
    use HasFactory;

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return HasMany<EvidenceAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EvidenceAttachment::class);
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Assessment revisions are immutable; append a new revision.');
        });
        static::deleting(function (): never {
            throw new LogicException('Assessment history cannot be deleted.');
        });
    }

    /** @return array{revision: 'integer', state: 'App\AssessmentState', assessed_on: 'immutable_date', snapshot: 'array'} */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'state' => AssessmentState::class, 'assessed_on' => 'immutable_date', 'snapshot' => 'array'];
    }
}
