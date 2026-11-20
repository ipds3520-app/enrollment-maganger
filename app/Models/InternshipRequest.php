<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model dasar pengajuan magang (fondasi Tahap 3A).
 * Logika status/approval dibangun pada tahap berikutnya.
 */
#[Fillable(['institution_id', 'created_by', 'title', 'description', 'start_date', 'end_date', 'participant_count'])]
class InternshipRequest extends Model
{
    /**
     * Institusi pengaju.
     *
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Operator yang membuat pengajuan.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Admin yang memproses keputusan.
     *
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Calon peserta yang diajukan dalam permohonan ini.
     *
     * @return HasMany<InternshipApplicant, $this>
     */
    public function applicants(): HasMany
    {
        return $this->hasMany(InternshipApplicant::class);
    }

    /** @return HasOne<SharedRegistrationToken, $this> */
    public function sharedRegistrationToken(): HasOne
    {
        return $this->hasOne(SharedRegistrationToken::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'decided_at' => 'datetime',
            'participant_count' => 'integer',
        ];
    }
}
