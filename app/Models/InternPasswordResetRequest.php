<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_intern_id', 'status', 'processed_by', 'processed_at', 'token_hash', 'token_expires_at', 'token_used_at', 'token_revoked_at'])]
#[Hidden(['token_hash'])]
class InternPasswordResetRequest extends Model
{
    /** @return BelongsTo<UserIntern, $this> */
    public function intern(): BelongsTo
    {
        return $this->belongsTo(UserIntern::class, 'user_intern_id');
    }

    /** @return BelongsTo<User, $this> */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'token_used_at' => 'datetime',
            'token_revoked_at' => 'datetime',
        ];
    }
}
