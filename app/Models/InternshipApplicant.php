<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['internship_request_id', 'name', 'email', 'phone', 'student_number', 'school_major'])]
class InternshipApplicant extends Model
{
    /**
     * Permohonan yang memuat calon peserta ini.
     *
     * @return BelongsTo<InternshipRequest, $this>
     */
    public function internshipRequest(): BelongsTo
    {
        return $this->belongsTo(InternshipRequest::class);
    }

    /**
     * Token history for this applicant, including replacements.
     *
     * @return HasMany<RegistrationToken, $this>
     */
    public function registrationTokens(): HasMany
    {
        return $this->hasMany(RegistrationToken::class, 'applicant_id');
    }

    /**
     * Akun peserta, jika registration token telah digunakan.
     *
     * @return HasOne<UserIntern, $this>
     */
    public function internAccount(): HasOne
    {
        return $this->hasOne(UserIntern::class, 'applicant_id');
    }
}
