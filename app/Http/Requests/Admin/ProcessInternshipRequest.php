<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessInternshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'reply_letter' => [
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
            'letter_number' => ['prohibited'],
            'admin_notes' => ['prohibited'],
            'participant_count' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Pilih keputusan pengajuan.',
            'decision.in' => 'Keputusan harus berupa disetujui atau ditolak.',
            'reply_letter.required' => 'Surat balasan PDF dari TU wajib diunggah.',
            'reply_letter.file' => 'File surat balasan tidak valid.',
            'reply_letter.mimes' => 'File surat balasan harus berformat PDF.',
            'reply_letter.mimetypes' => 'Isi file surat balasan harus terdeteksi sebagai PDF.',
            'reply_letter.extensions' => 'Ekstensi file surat balasan harus .pdf.',
            'reply_letter.max' => 'Ukuran surat balasan maksimal 10 MB.',
            '*.prohibited' => 'Field keputusan tersebut tidak dapat ditentukan melalui formulir.',
        ];
    }
}
