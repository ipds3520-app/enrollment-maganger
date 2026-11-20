<?php

namespace App\Http\Requests\Intern;

use App\Models\SharedRegistrationClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteInternProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $internId = $this->user('intern')?->getKey();

        return $internId !== null && SharedRegistrationClaim::query()
            ->where('user_intern_id', $internId)
            ->where('status', 'pending_profile')
            ->exists();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $intern = $this->user('intern');
        $claim = $intern?->sharedRegistrationClaim;
        $requestId = $claim?->sharedRegistrationToken?->internship_request_id;

        return [
            'nik' => ['required', 'digits:16'],
            'student_number' => [
                'required', 'string', 'max:30',
                Rule::unique('internship_applicants', 'student_number')
                    ->where(fn ($query) => $query->where('internship_request_id', $requestId))
                    ->ignore($intern?->applicant_id),
            ],
            'school_name' => ['required', 'string', 'max:150'],
            'school_major' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:2000'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'applicant_id' => ['prohibited'],
            'user_intern_id' => ['prohibited'],
            'shared_registration_token_id' => ['prohibited'],
            'used_count' => ['prohibited'],
        ];
    }
}
