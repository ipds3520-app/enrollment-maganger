<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Tampilkan halaman login.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Proses autentikasi dan arahkan pengguna sesuai role dari database.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Cegah session fixation.
        $request->session()->regenerate();

        $user = $request->user();

        $dashboardRoute = match (true) {
            $user->isAdmin() => 'admin.dashboard',
            $user->isOperator() => 'operator.dashboard',
            default => null,
        };

        // Defensif: role hanya boleh admin atau operator (dijaga juga oleh
        // CHECK constraint di database produksi).
        if ($dashboardRoute === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Role akun tidak dikenali. Hubungi administrator.',
            ]);
        }

        return redirect()->route($dashboardRoute);
    }

    /**
     * Akhiri sesi login pengguna.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah berhasil keluar.');
    }
}
