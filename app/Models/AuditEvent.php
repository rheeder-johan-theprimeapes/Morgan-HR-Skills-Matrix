<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'event', 'subject_type', 'subject_id', 'details'])]
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory;

    /** @return array{details: 'array'} */
    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
