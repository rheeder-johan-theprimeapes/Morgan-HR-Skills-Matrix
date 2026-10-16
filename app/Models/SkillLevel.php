<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SkillLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['skill_id', 'ordinal', 'label', 'guidance', 'is_active'])]
class SkillLevel extends Model
{
    /** @use HasFactory<SkillLevelFactory> */
    use HasFactory;

    /** @return BelongsTo<Skill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /** @return array{is_active: 'boolean', ordinal: 'integer'} */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'ordinal' => 'integer'];
    }
}
