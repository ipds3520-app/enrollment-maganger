<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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
        // Maksimal 6 percobaan login per menit, dikunci berdasarkan
        // kombinasi email (lowercase) dan alamat IP.
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $key = (is_string($email) ? mb_strtolower(trim($email)) : '').'|'.$request->ip();

            return Limit::perMinute(6)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return redirect()
                        ->route('login')
                        ->withInput($request->only('email', 'remember'))
                        ->withErrors([
                            'email' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 1 menit.',
                        ])
                        ->withHeaders($headers);
                });
        });

        // Isi email reset password dalam Bahasa Indonesia. URL tetap mengarah
        // ke route standar `password.reset` dengan token dari broker Laravel.
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
