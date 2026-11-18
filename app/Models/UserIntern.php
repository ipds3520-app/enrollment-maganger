<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['applicant_id', 'registration_token_id', 'name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class UserIntern extends Authenticatable
{
    use Notifiable;

    protected $table = 'users_intern';

    /** @return BelongsTo<InternshipApplicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(InternshipApplicant::class, 'applicant_id');
    }

    /** @return BelongsTo<RegistrationToken, $this> */
    public function registrationToken(): BelongsTo
    {
        return $this->belongsTo(RegistrationToken::class, 'registration_token_id');
    }

    /** @return HasMany<InternPasswordResetRequest, $this> */
    public function passwordResetRequests(): HasMany
    {
        return $this->hasMany(InternPasswordResetRequest::class, 'user_intern_id');
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
