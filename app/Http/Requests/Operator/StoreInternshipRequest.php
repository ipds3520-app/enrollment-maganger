<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

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
            'participant_count' => ['required', 'integer', 'min:1', 'max:2147483647'],
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
            'participant_count.required' => 'Jumlah peserta wajib diisi.',
            'participant_count.integer' => 'Jumlah peserta harus berupa bilangan bulat.',
            'participant_count.min' => 'Jumlah peserta minimal satu orang.',
            'participant_count.max' => 'Jumlah peserta terlalu besar.',
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
