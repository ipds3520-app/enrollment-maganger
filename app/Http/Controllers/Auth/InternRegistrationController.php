<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInternRequest;
use App\Models\RegistrationToken;
use App\Models\UserIntern;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
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
            // Unique conflicts (including a duplicate intern email) roll back
            // both account creation and token consumption without disclosure.
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
