<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // Tamu diarahkan ke halaman login. Route `login` baru dibuat pada
        // sub-tahap 3B-2, sehingga selama belum tersedia digunakan halaman root
        // agar tidak terjadi error "route not defined".
        $middleware->redirectGuestsTo(
            fn (Request $request) => Route::has('login') ? route('login') : '/'
        );

        // Pengguna yang sudah login dan membuka halaman guest diarahkan ke root.
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
