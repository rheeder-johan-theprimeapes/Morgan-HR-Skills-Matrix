<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AccountAccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'token_hash', 'expires_at', 'used_at'])]
#[Hidden(['token_hash'])]
class AccountAccessToken extends Model
{
    /** @use HasFactory<AccountAccessTokenFactory> */
    use HasFactory;

    /** @return array{expires_at: 'immutable_datetime', used_at: 'immutable_datetime'} */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime'];
    }
}
