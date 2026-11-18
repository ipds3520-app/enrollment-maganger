<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['applicant_id', 'token_hash', 'status', 'expires_at', 'used_at'])]
#[Hidden(['token_hash'])]
class RegistrationToken extends Model
{
    protected $table = 'registration_tokens';

    /**
     * The applicant this token was issued for.
     *
     * @return BelongsTo<InternshipApplicant, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(InternshipApplicant::class);
    }

    /** @return HasOne<UserIntern, $this> */
    public function internAccount(): HasOne
    {
        return $this->hasOne(UserIntern::class, 'registration_token_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
