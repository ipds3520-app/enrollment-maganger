<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * Semua pengguna tamu boleh mendaftar sebagai operator.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi input sebelum validasi.
     *
     * `role` dan `institution_id` tidak pernah dibaca dari input; keduanya
     * ditetapkan server pada controller.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'institution_name' => is_string($this->input('institution_name')) ? trim($this->input('institution_name')) : $this->input('institution_name'),
            'institution_address' => $this->filledOrNull('institution_address'),
            'institution_phone' => $this->filledOrNull('institution_phone'),
            'institution_email' => is_string($this->input('institution_email')) && trim($this->input('institution_email')) !== ''
                ? mb_strtolower(trim($this->input('institution_email')))
                : null,
        ]);
    }

    /**
     * Ambil nilai opsional: string kosong diubah menjadi null.
     */
    private function filledOrNull(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['required', 'string', 'max:150'],
            'institution_type' => ['required', 'string', Rule::in(['smk', 'sma', 'university', 'other'])],
            'institution_address' => ['nullable', 'string', 'max:1000'],
            'institution_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'institution_email' => ['nullable', 'string', 'email', 'max:150'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution_name.required' => 'Nama institusi wajib diisi.',
            'institution_name.string' => 'Format nama institusi tidak valid.',
            'institution_name.max' => 'Nama institusi maksimal 150 karakter.',
            'institution_type.required' => 'Jenis institusi wajib dipilih.',
            'institution_type.in' => 'Jenis institusi tidak valid.',
            'institution_address.string' => 'Format alamat institusi tidak valid.',
            'institution_address.max' => 'Alamat institusi maksimal 1000 karakter.',
            'institution_phone.string' => 'Format nomor telepon tidak valid.',
            'institution_phone.max' => 'Nomor telepon maksimal 20 karakter.',
            'institution_phone.regex' => 'Format nomor telepon tidak valid.',
            'institution_email.email' => 'Format email institusi tidak valid.',
            'institution_email.max' => 'Email institusi maksimal 150 karakter.',
            'name.required' => 'Nama operator wajib diisi.',
            'name.string' => 'Format nama operator tidak valid.',
            'name.max' => 'Nama operator maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Format password tidak valid.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'institution_name' => 'nama institusi',
            'institution_type' => 'jenis institusi',
            'institution_address' => 'alamat institusi',
            'institution_phone' => 'nomor telepon institusi',
            'institution_email' => 'email institusi',
            'name' => 'nama operator',
            'email' => 'email',
            'password' => 'password',
        ];
    }
}
