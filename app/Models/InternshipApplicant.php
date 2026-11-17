<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
