<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shared_registration_token_id', 'user_intern_id', 'internship_applicant_id', 'status', 'claimed_at', 'completed_at'])]
class SharedRegistrationClaim extends Model
{
    /** @return BelongsTo<SharedRegistrationToken, $this> */
    public function sharedRegistrationToken(): BelongsTo
    {
        return $this->belongsTo(SharedRegistrationToken::class);
    }

    /** @return BelongsTo<UserIntern, $this> */
    public function intern(): BelongsTo
    {
        return $this->belongsTo(UserIntern::class, 'user_intern_id');
    }

    /** @return BelongsTo<InternshipApplicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(InternshipApplicant::class, 'internship_applicant_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['claimed_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
