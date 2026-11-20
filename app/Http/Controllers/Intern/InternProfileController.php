<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\CompleteInternProfileRequest;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\UserIntern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternProfileController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        /** @var UserIntern $intern */
        $intern = $request->user('intern');
        $intern->load(['applicant', 'sharedRegistrationClaim']);

        if ($intern->sharedRegistrationClaim === null) {
            return redirect()->route('intern.dashboard');
        }

        if ($intern->sharedRegistrationClaim->status === 'pending_profile') {
            return response()->view('intern.profile.edit', ['intern' => $intern])
                ->withHeaders($this->sensitiveHeaders());
        }

        return response()->view('intern.profile.show', ['intern' => $intern])
            ->withHeaders($this->sensitiveHeaders());
    }

    public function update(CompleteInternProfileRequest $request): RedirectResponse
    {
        /** @var UserIntern $intern */
        $intern = $request->user('intern');
        $data = $request->validated();
        $claim = SharedRegistrationClaim::query()
            ->where('user_intern_id', $intern->getKey())
            ->firstOrFail();

        $saved = DB::transaction(function () use ($claim, $intern, $data): string {
            $token = SharedRegistrationToken::query()
                ->lockForUpdate()
                ->findOrFail($claim->shared_registration_token_id);
            $lockedClaim = SharedRegistrationClaim::query()
                ->lockForUpdate()
                ->findOrFail($claim->getKey());

            if ($lockedClaim->status === 'completed') {
                return 'already_completed';
            }

            if ($token->status !== 'active' || $token->used_count >= $token->quota) {
                throw ValidationException::withMessages([
                    'profile' => 'Token sudah dicabut atau kuotanya telah terpenuhi. Hubungi Operator institusi Anda.',
                ]);
            }

            $applicant = $lockedClaim->applicant()->lockForUpdate()->firstOrFail();
            $applicant->forceFill([
                'nik' => $data['nik'],
                'student_number' => $data['student_number'],
                'school_name' => $data['school_name'],
                'school_major' => $data['school_major'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
            ])->save();

            $intern->forceFill(['phone' => $data['phone']])->save();
            $lockedClaim->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            $token->used_count++;
            if ($token->used_count === $token->quota) {
                $token->status = 'exhausted';
            }
            $token->save();

            return 'completed';
        });

        return redirect()
            ->route('intern.profile.show')
            ->with('status', $saved === 'already_completed'
                ? 'Profil Anda sudah lengkap.'
                : 'Data pribadi berhasil disimpan. Kuota peserta telah digunakan.');
    }

    /** @return array<string, string> */
    private function sensitiveHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
