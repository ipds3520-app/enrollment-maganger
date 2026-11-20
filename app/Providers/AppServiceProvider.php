<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.app');
        Paginator::defaultSimpleView('vendor.pagination.app');

        // Maksimal 6 percobaan login per menit, dikunci berdasarkan
        // kombinasi email (lowercase) dan alamat IP.
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $loginType = match (true) {
                $request->routeIs('admin.login.store') => 'admin',
                $request->routeIs('operator.login.store') => 'operator',
                default => 'intern',
            };
            $key = $loginType.'|'.(is_string($email) ? mb_strtolower(trim($email)) : '').'|'.$request->ip();

            return Limit::perMinute(6)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return redirect()
                        ->route($request->routeIs('admin.login.store') ? 'admin.login' : ($request->routeIs('operator.login.store') ? 'operator.login' : 'intern.login'))
                        ->withInput($request->only('email', 'remember'))
                        ->withErrors([
                            'email' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 1 menit.',
                        ])
                        ->withHeaders($headers);
                });
        });

        RateLimiter::for('intern-password-reset', function (Request $request) {
            $email = $request->input('email');
            $emailKey = is_string($email) ? hash('sha256', mb_strtolower(trim($email))) : 'invalid';

            $ip = $request->ip();

            return [
                Limit::perMinute(20)->by('intern-reset-ip:'.$ip),
                Limit::perMinute(5)->by('intern-reset-email:'.$ip.'|'.$emailKey),
            ];
        });

        // Email reset tetap digunakan untuk Admin/Operator melalui broker users.
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return (new MailMessage)
                ->subject('Reset Password Akun Anda')
                ->greeting('Halo '.$notifiable->name.',')
                ->line('Kami menerima permintaan reset password untuk akun Anda.')
                ->action('Reset Password', $url)
                ->line('Tautan ini berlaku selama '.(int) config('auth.passwords.users.expire').' menit.')
                ->line('Jika Anda tidak meminta reset password, abaikan email ini.')
                ->salutation('Salam,');
        });
    }
}
