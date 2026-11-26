<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Invalidates stale Intern sessions without logging out other guards. */
class EnsureInternAuthenticationVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $intern = $request->user('intern');

        if ($intern === null) {
            return $next($request);
        }

        $sessionVersion = $request->session()->get('intern_auth_version');

        if ($sessionVersion !== null && (int) $sessionVersion === (int) $intern->auth_version) {
            return $next($request);
        }

        Auth::guard('intern')->logout();
        $request->session()->forget('intern_auth_version');
        $request->session()->migrate(true);
        $request->session()->regenerateToken();

        return redirect()->route('intern.login')->withErrors([
            'email' => 'Sesi peserta telah berakhir. Silakan login kembali.',
        ]);
    }
}
