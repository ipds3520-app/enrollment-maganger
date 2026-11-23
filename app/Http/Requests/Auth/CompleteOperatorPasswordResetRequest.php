<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CompleteOperatorPasswordResetRequest extends FormRequest
{
    public const SESSION_KEY = 'operator_password_reset';

    public const SESSION_LIFETIME_MINUTES = 15;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $state = $this->session()->get(self::SESSION_KEY);

        if (! is_array($state)
            || ! isset($state['user_id'], $state['started_at'])
            || ! is_numeric($state['user_id'])
            || ! is_numeric($state['started_at'])) {
            $this->session()->forget(self::SESSION_KEY);

            return;
        }

        $startedAt = (int) $state['started_at'];
        $now = now()->timestamp;

        if ($startedAt > $now || $now - $startedAt > self::SESSION_LIFETIME_MINUTES * 60) {
            $this->session()->forget(self::SESSION_KEY);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'email' => ['prohibited'],
            'user_id' => ['prohibited'],
            'operator_id' => ['prohibited'],
            'id' => ['prohibited'],
            'role' => ['prohibited'],
            'institution_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
