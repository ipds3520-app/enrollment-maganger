<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteOperatorPasswordResetRequest;
use App\Http\Requests\Auth\StartOperatorPasswordResetRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OperatorPasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.operator-forgot-password');
    }

    public function start(StartOperatorPasswordResetRequest $request): RedirectResponse
    {
        // Remove any prior target first so a failed attempt cannot reuse it.
        $request->session()->forget(CompleteOperatorPasswordResetRequest::SESSION_KEY);

        $operator = User::query()
            ->where('email', $request->validated('email'))
            ->where('role', 'operator')
            ->first();

        if ($operator === null) {
            return redirect()
                ->route('operator.password.request')
                ->withErrors(['email' => 'Email tidak ditemukan sebagai akun Operator.']);
        }

        // Rotate the session identifier while retaining other guard sessions.
        $request->session()->regenerate();
        $request->session()->put(CompleteOperatorPasswordResetRequest::SESSION_KEY, [
            'user_id' => $operator->getKey(),
            'started_at' => now()->timestamp,
        ]);

        return redirect()->route('operator.password.reset');
    }

    public function edit(Request $request): Response|RedirectResponse
    {
        if ($this->pendingOperator($request) === null) {
            return redirect()
                ->route('operator.password.request')
                ->withErrors(['email' => 'Sesi reset tidak tersedia atau sudah kedaluwarsa. Silakan mulai kembali.']);
        }

        return response()->view('auth.operator-reset-password')->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function update(CompleteOperatorPasswordResetRequest $request): RedirectResponse
    {
        $operator = $this->pendingOperator($request);

        if ($operator === null) {
            return redirect()
                ->route('operator.password.request')
                ->withErrors(['email' => 'Sesi reset tidak tersedia atau sudah kedaluwarsa. Silakan mulai kembali.']);
        }

        $validated = $request->validated();

        $updated = DB::transaction(function () use ($operator, $validated): bool {
            $lockedOperator = User::query()
                ->lockForUpdate()
                ->find($operator->getKey());

            if ($lockedOperator === null || ! $lockedOperator->isOperator()) {
                return false;
            }

            $lockedOperator->forceFill([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
            ])->save();

            return true;
        });

        $request->session()->forget(CompleteOperatorPasswordResetRequest::SESSION_KEY);

        if (! $updated) {
            return redirect()
                ->route('operator.password.request')
                ->withErrors(['email' => 'Sesi reset tidak tersedia atau akun bukan Operator. Silakan mulai kembali.']);
        }

        return redirect()
            ->route('operator.login')
            ->with('status', 'Password Operator berhasil diubah. Silakan masuk dengan password baru.');
    }

    private function pendingOperator(Request $request): ?User
    {
        $state = $request->session()->get(CompleteOperatorPasswordResetRequest::SESSION_KEY);

        if (! is_array($state)
            || ! isset($state['user_id'], $state['started_at'])
            || ! is_numeric($state['user_id'])
            || ! is_numeric($state['started_at'])) {
            $request->session()->forget(CompleteOperatorPasswordResetRequest::SESSION_KEY);

            return null;
        }

        $startedAt = (int) $state['started_at'];
        $now = now()->timestamp;

        if ($startedAt > $now || $now - $startedAt > CompleteOperatorPasswordResetRequest::SESSION_LIFETIME_MINUTES * 60) {
            $request->session()->forget(CompleteOperatorPasswordResetRequest::SESSION_KEY);

            return null;
        }

        $operator = User::query()->find((int) $state['user_id']);

        if ($operator === null || ! $operator->isOperator()) {
            $request->session()->forget(CompleteOperatorPasswordResetRequest::SESSION_KEY);

            return null;
        }

        return $operator;
    }
}
