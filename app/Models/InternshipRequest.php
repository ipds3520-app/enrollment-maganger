<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model dasar pengajuan magang (fondasi Tahap 3A).
 * Logika status/approval dibangun pada tahap berikutnya.
 */
#[Fillable(['institution_id', 'created_by', 'title', 'description', 'start_date', 'end_date'])]
class InternshipRequest extends Model
{
    /**
     * Calon peserta yang diajukan dalam permohonan ini.
     *
     * @return HasMany<InternshipApplicant, $this>
     */
    public function applicants(): HasMany
    {
        return $this->hasMany(InternshipApplicant::class);
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
        ];
    }
}
