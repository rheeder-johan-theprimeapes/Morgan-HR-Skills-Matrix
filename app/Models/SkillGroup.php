<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SkillGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'display_order', 'is_active'])]
class SkillGroup extends Model
{
    /** @use HasFactory<SkillGroupFactory> */
    use HasFactory;

    /** @return HasMany<Skill, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    /** @return array{is_active: 'boolean', display_order: 'integer'} */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'display_order' => 'integer'];
    }
}
