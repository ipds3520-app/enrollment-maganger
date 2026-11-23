<?php

namespace App\Http\Requests\Intern;

use App\Models\SharedRegistrationClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInternProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $intern = $this->user('intern');

        if ($intern === null || $intern->applicant_id === null) {
            return false;
        }

        $claim = SharedRegistrationClaim::query()
            ->where('user_intern_id', $intern->getKey())
            ->first();

        if ($claim !== null) {
            return $claim->status === 'completed'
                && (int) $claim->internship_applicant_id === (int) $intern->applicant_id;
        }

        return $intern->registration_token_id !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $intern = $this->user('intern');
        $requestId = $intern?->applicant?->internship_request_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'student_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('internship_applicants', 'student_number')
                    ->where(fn ($query) => $query->where('internship_request_id', $requestId))
                    ->ignore($intern?->applicant_id),
            ],
            'school_major' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:2000'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'nik' => ['prohibited'],
            'email' => ['prohibited'],
            'school_name' => ['prohibited'],
            'applicant_id' => ['prohibited'],
            'internship_request_id' => ['prohibited'],
            'user_intern_id' => ['prohibited'],
            'registration_token_id' => ['prohibited'],
            'shared_registration_token_id' => ['prohibited'],
            'shared_registration_claim_id' => ['prohibited'],
            'used_count' => ['prohibited'],
        ];
    }
}
