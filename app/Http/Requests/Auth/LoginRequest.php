<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Semua pengguna tamu boleh mengirim form login.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Format password tidak valid.',
            'remember.boolean' => 'Nilai "Ingat Saya" tidak valid.',
        ];
    }

    /**
     * Melakukan percobaan autentikasi.
     *
     * Pesan gagal sengaja generik agar tidak mengungkap apakah email
     * terdaftar atau password yang salah.
     *
     * @throws ValidationException
     */
    public function authenticate(string $guard, ?string $role = null): void
    {
        $email = $this->input('email');
        $password = $this->input('password');

        $credentials = [
            'email' => is_string($email) ? mb_strtolower(trim($email)) : '',
            'password' => is_string($password) ? $password : '',
        ];

        if ($role !== null) {
            $credentials['role'] = $role;
        }

        if (! Auth::guard($guard)->attempt($credentials, $this->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }
    }
}
