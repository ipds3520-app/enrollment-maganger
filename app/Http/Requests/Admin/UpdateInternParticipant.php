<?php

namespace App\Http\Requests\Admin;

use App\Models\UserIntern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInternParticipant extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->isAdmin() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $intern = UserIntern::query()
            ->with('applicant')
            ->find($this->route('userInternId'));
        $applicant = $intern?->applicant;
        $requestId = $applicant?->internship_request_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'student_number' => [
                'present',
                'nullable',
                'string',
                'max:30',
                Rule::unique('internship_applicants', 'student_number')
                    ->where(fn ($query) => $query->where('internship_request_id', $requestId))
                    ->ignore($applicant?->getKey()),
            ],
            'school_major' => ['present', 'nullable', 'string', 'max:100'],
            'phone' => ['present', 'nullable', 'string', 'max:20'],
            'address' => ['present', 'nullable', 'string', 'max:2000'],
            'birth_date' => ['present', 'nullable', 'date', 'before:today'],
            'gender' => ['present', 'nullable', Rule::in(['Laki-laki', 'Perempuan'])],
            'email' => ['prohibited'],
            'nik' => ['prohibited'],
            'school_name' => ['prohibited'],
            'password' => ['prohibited'],
            'applicant_id' => ['prohibited'],
            'internship_request_id' => ['prohibited'],
            'institution_id' => ['prohibited'],
            'registration_token_id' => ['prohibited'],
            'participant_status' => ['prohibited'],
            'completed_at' => ['prohibited'],
        ];
    }
}
