<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInternRequest;
use App\Models\RegistrationToken;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\UserIntern;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class InternRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.intern-register');
    }

    public function store(RegisterInternRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tokenHash = hash('sha256', $data['token']);

        if (SharedRegistrationToken::query()->where('token_hash', $tokenHash)->exists()) {
            return $this->registerWithSharedToken($request, $data, $tokenHash);
        }

        return $this->registerWithLegacyToken($data, $tokenHash);
    }

    /** @param array<string, mixed> $data */
    private function registerWithSharedToken(RegisterInternRequest $request, array $data, string $tokenHash): RedirectResponse
    {
        try {
            $account = DB::transaction(function () use ($data, $tokenHash): UserIntern|false {
                $token = SharedRegistrationToken::query()
                    ->where('token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->first();

                if ($token === null || $token->status !== 'active' || $token->used_count >= $token->quota) {
                    return false;
                }

                $internshipRequest = $token->internshipRequest()->firstOrFail();
                $applicant = $internshipRequest->applicants()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);

                $account = UserIntern::create([
                    'applicant_id' => $applicant->getKey(),
                    'registration_token_id' => null,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);

                SharedRegistrationClaim::create([
                    'shared_registration_token_id' => $token->getKey(),
                    'user_intern_id' => $account->getKey(),
                    'internship_applicant_id' => $applicant->getKey(),
                    'status' => 'pending_profile',
                    'claimed_at' => now(),
                ]);

                return $account;
            });
        } catch (QueryException) {
            return $this->failedRegistration();
        }

        if ($account === false) {
            return $this->failedRegistration();
        }

        // New shared-token accounts must enter profile completion immediately.
        // Legacy registrations below retain their previous no-auto-login behavior.
        Auth::guard('intern')->login($account);
        $request->session()->regenerate();

        return redirect()
            ->route('intern.profile.show')
            ->with('status', 'Akun berhasil dibuat. Lengkapi data pribadi untuk menggunakan kuota peserta.');
    }

    /** @param array<string, mixed> $data */
    private function registerWithLegacyToken(array $data, string $tokenHash): RedirectResponse
    {
        try {
            $registered = DB::transaction(function () use ($tokenHash, $data): bool {
                $token = RegistrationToken::query()
                    ->where('token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->first();

                if (
                    $token === null
                    || $token->status !== 'active'
                    || ! $token->expires_at->isFuture()
                    || DB::table('users_intern')->where('applicant_id', $token->applicant_id)->exists()
                ) {
                    return false;
                }

                $applicant = $token->applicant()->first();

                if ($applicant === null) {
                    return false;
                }

                UserIntern::create([
                    'applicant_id' => $applicant->getKey(),
                    'registration_token_id' => $token->getKey(),
                    'name' => $applicant->name,
                    'email' => $applicant->email,
                    'password' => Hash::make($data['password']),
                    'phone' => $applicant->phone,
                ]);

                $token->status = 'used';
                $token->used_at = now();
                $token->save();

                return true;
            });
        } catch (QueryException) {
            // Unique conflicts roll back both account creation and token consumption.
            return $this->failedRegistration();
        }

        if (! $registered) {
            return $this->failedRegistration();
        }

        return redirect()
            ->route('intern.register')
            ->with('status', 'Registrasi akun peserta berhasil. Silakan hubungi pengelola untuk informasi akses berikutnya.');
    }

    private function failedRegistration(): RedirectResponse
    {
        return redirect()
            ->route('intern.register')
            ->withErrors(['token' => 'Registration token tidak valid atau tidak dapat digunakan.']);
    }
}
