<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteInternPasswordResetRequest;
use App\Models\InternPasswordResetRequest as ResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InternNewPasswordController extends Controller
{
    public function create(): Response
    {
        return response()->view('auth.intern-reset-password', [
            'token' => '',
        ])->withHeaders($this->sensitiveHeaders());
    }

    public function store(CompleteInternPasswordResetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tokenHash = hash('sha256', $validated['token']);

        $reset = DB::transaction(function () use ($tokenHash, $validated): bool {
            $resetRequest = ResetRequest::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($resetRequest === null
                || $resetRequest->status !== 'approved'
                || $resetRequest->token_used_at !== null
                || $resetRequest->token_revoked_at !== null
                || $resetRequest->token_expires_at === null
                || $resetRequest->token_expires_at->isPast()) {
                return false;
            }

            $intern = $resetRequest->intern()->lockForUpdate()->first();

            if ($intern === null) {
                return false;
            }

            $intern->forceFill([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
            ])->save();

            $resetRequest->token_used_at = now();
            $resetRequest->save();

            return true;
        });

        if ($reset === false) {
            return redirect()->to(route('intern.password.reset').'#'.$validated['token'])
                ->withErrors(['token' => 'Tautan reset password tidak valid atau sudah kedaluwarsa.'])
                ->withHeaders($this->sensitiveHeaders());
        }

        return redirect()->route('intern.login')
            ->with('status', 'Password berhasil diubah. Silakan masuk kembali.')
            ->withHeaders($this->sensitiveHeaders());
    }

    /** @return array<string, string> */
    private function sensitiveHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
