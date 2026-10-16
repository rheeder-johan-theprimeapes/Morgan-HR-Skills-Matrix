<?php

declare(strict_types=1);

namespace App\Models;

use App\PdrState;
use Database\Factories\PdrReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property array{version:string,name:string,employee_prompt:string,manager_prompt:string} $section_template */
#[Fillable(['review_cycle_id', 'employee_id', 'manager_user_id', 'section_profile', 'section_template', 'state', 'version', 'current_submission_id', 'current_feedback_id', 'current_release_id', 'current_draft_id', 'allocated_by'])]
#[Hidden(['section_template'])]
class PdrReview extends Model
{
    /** @use HasFactory<PdrReviewFactory> */
    use HasFactory;

    /** @return BelongsTo<ReviewCycle,$this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class, 'review_cycle_id');
    }

    /** @return BelongsTo<Employee,$this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User,$this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /** @return BelongsTo<PdrDraft,$this> */
    public function currentDraft(): BelongsTo
    {
        return $this->belongsTo(PdrDraft::class, 'current_draft_id');
    }

    /** @return BelongsTo<PdrSubmission,$this> */
    public function currentSubmission(): BelongsTo
    {
        return $this->belongsTo(PdrSubmission::class, 'current_submission_id');
    }

    /** @return HasMany<PdrSubmission,$this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(PdrSubmission::class)->orderByDesc('submission_number');
    }

    /** @return HasMany<PdrDraft,$this> */
    public function drafts(): HasMany
    {
        return $this->hasMany(PdrDraft::class)->orderBy('draft_number');
    }

    /** @return array{section_template:'array',state:'App\PdrState',version:'integer'} */
    protected function casts(): array
    {
        return ['section_template' => 'array', 'state' => PdrState::class, 'version' => 'integer'];
    }
}
