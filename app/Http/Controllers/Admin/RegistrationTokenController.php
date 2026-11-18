<?php

namespace App\Http\Controllers\Admin;

use App\Actions\IssueRegistrationTokens;
use App\Http\Controllers\Controller;
use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationTokenController extends Controller
{
    public function replace(
        int $internshipRequestId,
        int $applicantId,
        IssueRegistrationTokens $issueRegistrationTokens,
    ): Response {
        [$internshipRequest, $issuedTokens] = DB::transaction(function () use ($internshipRequestId, $applicantId, $issueRegistrationTokens): array {
            $internshipRequest = InternshipRequest::query()
                ->lockForUpdate()
                ->findOrFail($internshipRequestId);

            $this->ensureApproved($internshipRequest);

            $applicant = $internshipRequest->applicants()
                ->lockForUpdate()
                ->findOrFail($applicantId);

            return [$internshipRequest, [$issueRegistrationTokens->replacementFor($applicant)]];
        });

        return $this->issuedResponse($internshipRequest, $issuedTokens, true);
    }

    public function revoke(
        int $internshipRequestId,
        int $registrationTokenId,
        IssueRegistrationTokens $issueRegistrationTokens,
    ): RedirectResponse {
        DB::transaction(function () use ($internshipRequestId, $registrationTokenId, $issueRegistrationTokens): void {
            $internshipRequest = InternshipRequest::query()
                ->lockForUpdate()
                ->findOrFail($internshipRequestId);

            $this->ensureApproved($internshipRequest);

            $token = RegistrationToken::query()
                ->whereHas('applicant', fn ($query) => $query->where('internship_request_id', $internshipRequest->getKey()))
                ->lockForUpdate()
                ->findOrFail($registrationTokenId);

            $issueRegistrationTokens->revoke($token);
        });

        return redirect()
            ->route('admin.internship-requests.show', $internshipRequestId)
            ->with('status', 'Registration token berhasil dicabut.');
    }

    /** @param list<array{applicant: InternshipApplicant, token: string, expires_at: Carbon}> $issuedTokens */
    private function issuedResponse(InternshipRequest $internshipRequest, array $issuedTokens, bool $replacement): Response
    {
        return response()->view('admin.registration-tokens.issued', [
            'internshipRequest' => $internshipRequest,
            'issuedTokens' => $issuedTokens,
            'replacement' => $replacement,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function ensureApproved(InternshipRequest $internshipRequest): void
    {
        if ($internshipRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'token' => 'Token hanya dapat dikelola untuk pengajuan yang disetujui.',
            ]);
        }
    }
}
