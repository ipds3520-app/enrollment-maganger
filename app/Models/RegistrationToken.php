<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
