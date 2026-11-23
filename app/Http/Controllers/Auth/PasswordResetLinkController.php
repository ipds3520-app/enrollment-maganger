<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Tampilkan halaman permintaan tautan reset password.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Kirim tautan reset password melalui password broker bawaan Laravel.
     *
     * Pesan respons selalu sama, baik email terdaftar maupun tidak, sehingga
     * keberadaan akun tidak dapat ditebak dari halaman ini.
     */
    public function store(PasswordResetLinkRequest $request): RedirectResponse
    {
        /** @var array{email: string} $credentials */
        $credentials = $request->only('email');

        $admin = User::query()
            ->where('email', $credentials['email'])
            ->where('role', 'admin')
            ->first();

        if ($admin !== null) {
            Password::broker('users')->sendResetLink(['email' => $admin->email]);
        }

        return back()->with(
            'status',
            'Jika alamat email tersebut terdaftar, kami telah mengirimkan tautan reset password. Silakan periksa kotak masuk Anda.'
        );
    }
}
