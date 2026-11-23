<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSharedRegistrationTokenQuota extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->isAdmin() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['quota' => ['required', 'integer', 'min:1', 'max:2147483647']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'quota.required' => 'Kuota peserta wajib diisi.',
            'quota.integer' => 'Kuota peserta harus berupa bilangan bulat.',
            'quota.min' => 'Kuota peserta minimal satu orang.',
            'quota.max' => 'Kuota peserta terlalu besar.',
        ];
    }
}
