<?php

namespace App\Http\Requests\Auth;

use App\Models\SharedRegistrationToken;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterInternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $token = $this->input('token');
        $isSharedToken = is_string($token)
            && $token !== ''
            && SharedRegistrationToken::query()->where('token_hash', hash('sha256', trim($token)))->exists();

        return [
            'token' => ['required', 'string', 'max:255'],
            'name' => [Rule::requiredIf($isSharedToken), 'nullable', 'string', 'max:100'],
            'email' => [Rule::requiredIf($isSharedToken), 'nullable', 'string', 'email', 'max:150', Rule::unique('users_intern', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'token' => is_string($this->input('token')) ? trim($this->input('token')) : $this->input('token'),
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'token.required' => 'Registration token wajib diisi.',
            'token.string' => 'Registration token tidak valid.',
            'token.max' => 'Registration token tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Format password tidak valid.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh akun peserta lain.',
            '*.prohibited' => 'Data identitas tidak dapat ditentukan melalui formulir.',
        ];
    }

    /**
     * Jangan pernah menaruh token plaintext di session old input.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('intern.register')
                ->withErrors($validator)
                ->withInput($this->except(['token', 'password', 'password_confirmation']))
        );
    }
}
