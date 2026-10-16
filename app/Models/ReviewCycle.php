<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReviewCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'scoring_framework_id', 'opens_on', 'closes_on', 'requires_employee_input', 'template_approval_reference', 'revision', 'created_by'])]
class ReviewCycle extends Model
{
    /** @use HasFactory<ReviewCycleFactory> */
    use HasFactory;

    /** @return BelongsTo<ScoringFramework,$this> */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(ScoringFramework::class, 'scoring_framework_id');
    }

    /** @return HasMany<PdrReview,$this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(PdrReview::class);
    }

    public function isOpen(): bool
    {
        return $this->opens_on->toDateString() <= today()->toDateString() && $this->closes_on->toDateString() >= today()->toDateString();
    }

    /** @return array{opens_on:'immutable_date',closes_on:'immutable_date',requires_employee_input:'boolean',revision:'integer'} */
    protected function casts(): array
    {
        return ['opens_on' => 'immutable_date', 'closes_on' => 'immutable_date', 'requires_employee_input' => 'boolean', 'revision' => 'integer'];
    }
}
