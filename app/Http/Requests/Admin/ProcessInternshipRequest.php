<?php

namespace App\Http\Requests\Admin;

use App\Rules\GoogleDriveUrl;
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
            'reply_letter' => ['prohibited'],
            'reply_letter_url' => ['required', 'string', 'max:255', new GoogleDriveUrl],
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
            'reply_letter_url.required' => 'Link surat balasan Google Drive wajib diisi.',
            'reply_letter_url.max' => 'Link surat balasan terlalu panjang.',
            '*.prohibited' => 'Field keputusan tersebut tidak dapat ditentukan melalui formulir.',
        ];
    }
}
