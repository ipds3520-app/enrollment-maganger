<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['internship_request_id', 'token_hash', 'quota', 'used_count', 'status'])]
#[Hidden(['token_hash'])]
class SharedRegistrationToken extends Model
{
    /** @return BelongsTo<InternshipRequest, $this> */
    public function internshipRequest(): BelongsTo
    {
        return $this->belongsTo(InternshipRequest::class);
    }

    /** @return HasMany<SharedRegistrationClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(SharedRegistrationClaim::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quota' => 'integer', 'used_count' => 'integer'];
    }
}
