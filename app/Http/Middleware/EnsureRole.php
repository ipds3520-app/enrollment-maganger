<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses route berdasarkan role pengguna.
 *
 * Contoh pemakaian: ->middleware(['auth', 'role:admin'])
 *                   ->middleware(['auth', 'role:operator'])
 *
 * Pengguna tanpa role yang sesuai ditolak dengan HTTP 403 — bukan redirect,
 * sehingga tidak ada risiko redirect loop.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Pengguna tamu seharusnya sudah ditangani middleware `auth`,
        // tetapi tetap dijaga agar tidak lolos ketika middleware ini dipakai sendiri.
        if ($user === null) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        $allowedRoles = array_map('trim', $roles);

        if (! in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
