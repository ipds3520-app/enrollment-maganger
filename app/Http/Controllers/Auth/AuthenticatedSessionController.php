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
    public function create(): View
    {
        return view('auth.login-options');
    }

    public function createAdmin(): View
    {
        return $this->loginView('admin', 'Admin');
    }

    public function createOperator(): View
    {
        return $this->loginView('operator', 'Operator');
    }

    public function createIntern(): View
    {
        return $this->loginView('intern', 'Peserta Magang');
    }

    public function storeAdmin(LoginRequest $request): RedirectResponse
    {
        return $this->authenticate($request, 'admin', 'admin', 'admin.dashboard');
    }

    public function storeOperator(LoginRequest $request): RedirectResponse
    {
        return $this->authenticate($request, 'operator', 'operator', 'operator.dashboard');
    }

    public function storeIntern(LoginRequest $request): RedirectResponse
    {
        return $this->authenticate($request, 'intern', null, 'intern.dashboard');
    }

    public function destroyAdmin(Request $request): RedirectResponse
    {
        return $this->logoutGuard($request, 'admin', 'admin.login');
    }

    public function destroyOperator(Request $request): RedirectResponse
    {
        return $this->logoutGuard($request, 'operator', 'operator.login');
    }

    public function destroyIntern(Request $request): RedirectResponse
    {
        return $this->logoutGuard($request, 'intern', 'intern.login');
    }

    private function loginView(string $type, string $label): View
    {
        return view('auth.login', [
            'accountType' => $type,
            'accountLabel' => $label,
        ]);
    }

    private function authenticate(LoginRequest $request, string $guard, ?string $role, string $dashboard): RedirectResponse
    {
        $request->authenticate($guard, $role);

        // SessionGuard::login rotates the session identifier while preserving
        // other guard keys stored in the same browser session.
        return redirect()->route($dashboard);
    }

    private function logoutGuard(Request $request, string $guard, string $loginRoute): RedirectResponse
    {
        Auth::guard($guard)->logout();

        // Retain the other guards' session values while rotating the identifier.
        $request->session()->migrate(true);
        $request->session()->regenerateToken();

        return redirect()->route($loginRoute)->with('status', 'Anda telah berhasil keluar.');
    }
}
