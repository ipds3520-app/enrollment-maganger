<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        $middleware->redirectGuestsTo(
            fn (Request $request) => match (true) {
                $request->is('admin/*') => route('admin.login'),
                $request->is('operator/*') => route('operator.login'),
                $request->is('intern/*') => route('intern.login'),
                default => route('login'),
            }
        );

        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->is('admin/*') => route('admin.dashboard'),
            $request->is('operator/*') || $request->is('register') => route('operator.dashboard'),
            $request->is('intern/*') => route('intern.dashboard'),
            default => route('login'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
