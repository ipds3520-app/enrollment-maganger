<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Tampilkan form reset password dari tautan email.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => (string) $request->route('token'),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Proses reset password melalui password broker bawaan Laravel.
     *
     * Token disimpan sebagai hash, berlaku 60 menit, dan dihapus setelah
     * dipakai sehingga tidak dapat digunakan ulang.
     */
    public function store(NewPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                // Cast 'hashed' pada model User meng-hash password otomatis.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Password berhasil diubah. Silakan masuk dengan password baru.');
        }

        // Pesan generik untuk token tidak valid, token kedaluwarsa, maupun
        // email yang tidak terdaftar (anti-enumerasi).
        throw ValidationException::withMessages([
            'email' => 'Tautan reset password tidak valid atau sudah kedaluwarsa.',
        ]);
    }
}
