<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JobRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_active'])]
class JobRole extends Model
{
    /** @use HasFactory<JobRoleFactory> */
    use HasFactory;

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return array{is_active: 'boolean'} */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
