<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInternshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'applicants' => ['required', 'array', 'min:1'],
            'applicants.*' => ['required', 'array:name,email,phone,student_number,school_major'],
            'applicants.*.name' => ['required', 'string', 'max:100'],
            'applicants.*.email' => ['required', 'string', 'email', 'max:150'],
            'applicants.*.phone' => ['nullable', 'string', 'max:20'],
            'applicants.*.student_number' => ['nullable', 'string', 'max:30'],
            'applicants.*.school_major' => ['nullable', 'string', 'max:100'],
            'proposal' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'extensions:pdf',
                'max:10240',
            ],
            'institution_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'status' => ['prohibited'],
            'processed_by' => ['prohibited'],
            'decided_at' => ['prohibited'],
            'reply_letter_path' => ['prohibited'],
            'proposal_path' => ['prohibited'],
            'letter_number' => ['prohibited'],
            'admin_notes' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $applicants = $this->input('applicants', []);

            if (! is_array($applicants)) {
                return;
            }

            $numbers = collect($applicants)
                ->filter(fn ($applicant): bool => is_array($applicant))
                ->pluck('student_number')
                ->filter(fn ($number): bool => is_string($number) && trim($number) !== '');

            if ($numbers->count() !== $numbers->unique()->count()) {
                $validator->errors()->add(
                    'applicants',
                    'Nomor induk peserta tidak boleh duplikat dalam satu pengajuan.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul pengajuan wajib diisi.',
            'title.max' => 'Judul pengajuan maksimal 150 karakter.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date_format' => 'Format tanggal mulai tidak valid.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.date_format' => 'Format tanggal selesai tidak valid.',
            'end_date.after' => 'Tanggal selesai harus setelah tanggal mulai.',
            'applicants.required' => 'Tambahkan minimal satu calon peserta.',
            'applicants.min' => 'Tambahkan minimal satu calon peserta.',
            'applicants.*.name.required' => 'Nama calon peserta wajib diisi.',
            'applicants.*.name.max' => 'Nama calon peserta maksimal 100 karakter.',
            'applicants.*.email.required' => 'Email calon peserta wajib diisi.',
            'applicants.*.email.email' => 'Format email calon peserta tidak valid.',
            'applicants.*.email.max' => 'Email calon peserta maksimal 150 karakter.',
            'applicants.*.phone.max' => 'Nomor telepon maksimal 20 karakter.',
            'applicants.*.student_number.max' => 'Nomor induk maksimal 30 karakter.',
            'applicants.*.school_major.max' => 'Jurusan maksimal 100 karakter.',
            'proposal.required' => 'Proposal magang wajib diunggah.',
            'proposal.file' => 'File proposal tidak valid.',
            'proposal.mimes' => 'Proposal harus berformat PDF.',
            'proposal.mimetypes' => 'Isi file proposal harus terdeteksi sebagai PDF.',
            'proposal.extensions' => 'Ekstensi file proposal harus .pdf.',
            'proposal.max' => 'Ukuran proposal maksimal 10 MB.',
            '*.prohibited' => 'Field tersebut tidak dapat ditentukan melalui formulir.',
        ];
    }
}
