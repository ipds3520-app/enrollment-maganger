<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateInternParticipantStatus extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->isAdmin() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'user_intern_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_intern_ids.*' => ['required', 'integer', 'distinct', 'exists:users_intern,id'],
            'participant_status' => ['required', Rule::in(['active', 'completed'])],
            'completed_at' => ['prohibited'],
            'changed_by' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'applicant_id' => ['prohibited'],
            'internship_request_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_intern_ids.required' => 'Pilih setidaknya satu peserta untuk mengubah status.',
            'user_intern_ids.array' => 'Daftar peserta tidak valid.',
            'user_intern_ids.min' => 'Pilih setidaknya satu peserta untuk mengubah status.',
            'user_intern_ids.max' => 'Maksimal 100 peserta dapat diproses dalam satu batch.',
            'user_intern_ids.*.distinct' => 'ID peserta duplikat. Tidak ada status yang diubah.',
            'user_intern_ids.*.exists' => 'Satu atau lebih peserta tidak ditemukan.',
            'participant_status.required' => 'Pilih status Aktif atau Selesai.',
            'participant_status.in' => 'Status hanya dapat diubah menjadi Aktif atau Selesai.',
            '*.prohibited' => 'Field tersebut tidak dapat ditentukan melalui formulir.',
        ];
    }
}
