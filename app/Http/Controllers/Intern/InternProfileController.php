<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Requests\Intern\CompleteInternProfileRequest;
use App\Http\Requests\Intern\UpdateInternProfileRequest;
use App\Models\InternshipApplicant;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\UserIntern;
use Illuminate\Database\QueryException;
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
        $intern->load([
            'applicant',
            'sharedRegistrationClaim.sharedRegistrationToken.internshipRequest.institution',
        ]);

        if ($intern->applicant === null) {
            return redirect()->route('intern.dashboard');
        }

        if ($intern->sharedRegistrationClaim?->status === 'pending_profile') {
            return response()->view('intern.profile.edit', [
                'intern' => $intern,
                'schoolName' => $intern->sharedRegistrationClaim->sharedRegistrationToken->internshipRequest->institution->name,
            ])
                ->withHeaders($this->sensitiveHeaders());
        }

        return response()->view('intern.profile.show', ['intern' => $intern])
            ->withHeaders($this->sensitiveHeaders());
    }

    public function edit(Request $request): Response|RedirectResponse
    {
        /** @var UserIntern $intern */
        $intern = $request->user('intern');
        $intern->load(['applicant', 'sharedRegistrationClaim']);

        if ($intern->applicant === null) {
            return redirect()->route('intern.dashboard');
        }

        if (! $this->canEditCompletedProfile($intern, $intern->sharedRegistrationClaim)) {
            return redirect()->route('intern.profile.show');
        }

        return response()->view('intern.profile.edit-account', [
            'intern' => $intern,
            'applicant' => $intern->applicant,
        ])->withHeaders($this->sensitiveHeaders());
    }

    public function updateDetails(UpdateInternProfileRequest $request): RedirectResponse
    {
        /** @var UserIntern $intern */
        $intern = $request->user('intern');
        $data = $request->validated();

        try {
            DB::transaction(function () use ($intern, $data): void {
                $claim = SharedRegistrationClaim::query()
                    ->where('user_intern_id', $intern->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($claim !== null) {
                    if (
                        $claim->status !== 'completed'
                        || (int) $claim->internship_applicant_id !== (int) $intern->applicant_id
                    ) {
                        abort(403);
                    }
                } elseif ($intern->registration_token_id === null) {
                    abort(403);
                }

                $applicant = InternshipApplicant::query()
                    ->whereKey($intern->applicant_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedIntern = UserIntern::query()
                    ->lockForUpdate()
                    ->findOrFail($intern->getKey());

                $applicantChanges = [
                    'name' => $data['name'],
                    'nik' => $data['nik'],
                    'student_number' => $data['student_number'],
                    'school_major' => $data['school_major'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'birth_date' => $data['birth_date'],
                    'gender' => $data['gender'],
                ];

                if ($applicant->participant_status === null) {
                    $applicantChanges['participant_status'] = 'active';
                    $applicantChanges['completed_at'] = null;
                }

                $applicant->forceFill($applicantChanges)->save();

                $lockedIntern->forceFill([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                ])->save();
            });
        } catch (QueryException $exception) {
            if ($this->isApplicantStudentNumberConflict($exception)) {
                throw ValidationException::withMessages([
                    'student_number' => 'NIM/NIS sudah digunakan peserta lain dalam pengajuan ini.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('intern.profile.show')
            ->with('status', 'Data pribadi berhasil diperbarui.');
    }

    public function update(CompleteInternProfileRequest $request): RedirectResponse
    {
        /** @var UserIntern $intern */
        $intern = $request->user('intern');
        $data = $request->validated();
        $sessionAuthVersion = $request->session()->get('intern_auth_version');
        $claim = SharedRegistrationClaim::query()
            ->where('user_intern_id', $intern->getKey())
            ->firstOrFail();

        $saved = DB::transaction(function () use ($claim, $intern, $data, $sessionAuthVersion): string {
            $token = SharedRegistrationToken::query()
                ->lockForUpdate()
                ->findOrFail($claim->shared_registration_token_id);
            $lockedClaim = SharedRegistrationClaim::query()
                ->lockForUpdate()
                ->findOrFail($claim->getKey());

            $alreadyCompleted = $lockedClaim->status === 'completed';

            if (! $alreadyCompleted && ($token->status !== 'active' || $token->used_count >= $token->quota)) {
                throw ValidationException::withMessages([
                    'profile' => 'Token sudah dicabut atau kuotanya telah terpenuhi. Hubungi Operator institusi Anda.',
                ]);
            }

            $applicant = $lockedClaim->applicant()->lockForUpdate()->firstOrFail();
            $lockedIntern = UserIntern::query()
                ->lockForUpdate()
                ->findOrFail($intern->getKey());

            if (
                $sessionAuthVersion === null
                || (int) $lockedIntern->auth_version !== (int) $sessionAuthVersion
            ) {
                abort(403, 'Sesi peserta telah berakhir. Silakan login kembali.');
            }

            if ((int) $lockedIntern->applicant_id !== (int) $applicant->getKey()) {
                abort(409, 'Relasi akun dan profil peserta berubah. Muat ulang halaman lalu coba kembali.');
            }

            if ($alreadyCompleted) {
                return 'already_completed';
            }

            $schoolName = $token->internshipRequest()
                ->with('institution')
                ->firstOrFail()
                ->institution
                ->name;

            $applicant->forceFill([
                'nik' => $data['nik'],
                'student_number' => $data['student_number'],
                'school_name' => $schoolName,
                'school_major' => $data['school_major'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'participant_status' => 'active',
                'completed_at' => null,
            ])->save();

            $lockedIntern->forceFill(['phone' => $data['phone']])->save();
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

    private function canEditCompletedProfile(UserIntern $intern, ?SharedRegistrationClaim $claim): bool
    {
        if ($claim !== null) {
            return $claim->status === 'completed'
                && (int) $claim->internship_applicant_id === (int) $intern->applicant_id;
        }

        return $intern->registration_token_id !== null && $intern->applicant_id !== null;
    }

    private function isApplicantStudentNumberConflict(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && (
                str_contains($message, 'internship_applicants_request_student_unique')
                || str_contains($message, 'internship_applicants.student_number')
            );
    }
}
