<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AccountAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'subject_id', 'action'])]
class AccountAudit extends Model
{
    /** @use HasFactory<AccountAuditFactory> */
    use HasFactory;
}
