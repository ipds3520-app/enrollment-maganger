<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['applicant_id', 'old_status', 'new_status', 'changed_by', 'changed_at'])]
class InternshipParticipantStatusHistory extends Model
{
    /** @return BelongsTo<InternshipApplicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(InternshipApplicant::class, 'applicant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
