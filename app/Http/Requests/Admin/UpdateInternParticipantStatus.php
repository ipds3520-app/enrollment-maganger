<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInternParticipantStatus extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->isAdmin() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'participant_status' => ['required', Rule::in(['active', 'completed'])],
            'completed_at' => ['prohibited'],
            'changed_by' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'applicant_id' => ['prohibited'],
            'internship_request_id' => ['prohibited'],
        ];
    }
}
