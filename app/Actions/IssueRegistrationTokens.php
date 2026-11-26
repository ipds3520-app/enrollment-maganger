<?php

namespace App\Actions;

use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueRegistrationTokens
{
    /**
     * Issue one token for each applicant in a newly approved request.
     * The caller must run this action inside the decision transaction.
     *
     * @return list<array{applicant: InternshipApplicant, token: string, expires_at: Carbon}>
     */
    public function forApprovedRequest(InternshipRequest $request): array
    {
        $applicants = $request->applicants()->orderBy('id')->get();

        if ($applicants->isEmpty()) {
            throw ValidationException::withMessages([
                'decision' => 'Pengajuan tanpa peserta tidak dapat disetujui.',
            ]);
        }

        if (RegistrationToken::query()->whereIn('applicant_id', $applicants->modelKeys())->exists()) {
            throw ValidationException::withMessages([
                'decision' => 'Data token peserta tidak konsisten. Persetujuan dibatalkan untuk mencegah token ganda.',
            ]);
        }

        $issued = [];

        foreach ($applicants as $applicant) {
            $issued[] = $this->createForApplicant($applicant);
        }

        return $issued;
    }

    /**
     * Reissue only after a previous token is revoked or expired.
     * The caller must lock the applicant and run this inside a transaction.
     *
     * @return array{applicant: InternshipApplicant, token: string, expires_at: Carbon}
     */
    public function replacementFor(InternshipApplicant $applicant): array
    {
        if (DB::table('users_intern')->where('applicant_id', $applicant->getKey())->exists()) {
            throw ValidationException::withMessages([
                'token' => 'Peserta sudah memiliki akun. Token tidak dapat diterbitkan ulang.',
            ]);
        }

        $tokens = $applicant->registrationTokens()->lockForUpdate()->orderByDesc('id')->get();

        if ($tokens->isEmpty()) {
            throw ValidationException::withMessages([
                'token' => 'Token sebelumnya tidak ditemukan. Penggantian token tidak dapat dilakukan.',
            ]);
        }

        if ($tokens->contains(fn (RegistrationToken $token): bool => $token->status === 'used')) {
            throw ValidationException::withMessages([
                'token' => 'Token peserta yang sudah digunakan tidak dapat diganti.',
            ]);
        }

        $activeTokens = $tokens->where('status', 'active');

        if ($activeTokens->count() > 1) {
            throw ValidationException::withMessages([
                'token' => 'Data token peserta tidak konsisten. Penggantian dibatalkan.',
            ]);
        }

        if ($activeTokens->isEmpty()) {
            if (! $tokens->contains(fn (RegistrationToken $token): bool => $token->status === 'revoked')) {
                throw ValidationException::withMessages([
                    'token' => 'Token sebelumnya harus dicabut atau kedaluwarsa sebelum diganti.',
                ]);
            }
        } else {
            /** @var RegistrationToken $activeToken */
            $activeToken = $activeTokens->first();

            if ($activeToken->expires_at->isFuture()) {
                throw ValidationException::withMessages([
                    'token' => 'Token yang masih berlaku harus dicabut sebelum diganti.',
                ]);
            }

            $activeToken->status = 'revoked';
            $activeToken->save();
        }

        return $this->createForApplicant($applicant);
    }

    /**
     * Persist only the digest and return the plaintext to the caller once.
     *
     * @return array{applicant: InternshipApplicant, token: string, expires_at: Carbon}
     */
    private function createForApplicant(InternshipApplicant $applicant): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addDays(30);

        $applicant->registrationTokens()->create([
            'token_hash' => hash('sha256', $plainToken),
            'status' => 'active',
            'expires_at' => $expiresAt,
            'used_at' => null,
        ]);

        return [
            'applicant' => $applicant,
            'token' => $plainToken,
            'expires_at' => $expiresAt,
        ];
    }
}
