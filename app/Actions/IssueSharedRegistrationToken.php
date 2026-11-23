<?php

namespace App\Actions;

use App\Models\InternshipRequest;
use App\Models\SharedRegistrationToken;
use Illuminate\Validation\ValidationException;

class IssueSharedRegistrationToken
{
    /** @return array{token: SharedRegistrationToken, plaintext: string} */
    public function forApprovedRequest(InternshipRequest $request): array
    {
        $quota = (int) $request->participant_count;

        if ($quota < 1) {
            throw ValidationException::withMessages([
                'decision' => 'Jumlah peserta harus tersedia dan lebih besar dari nol sebelum pengajuan dapat disetujui.',
            ]);
        }

        $plaintext = bin2hex(random_bytes(32));
        $token = new SharedRegistrationToken([
            'internship_request_id' => $request->getKey(),
            'token_hash' => hash('sha256', $plaintext),
            'quota' => $quota,
            'used_count' => 0,
            'status' => 'active',
        ]);
        $token->token_encrypted = $plaintext;
        $token->save();

        return ['token' => $token, 'plaintext' => $plaintext];
    }
}
