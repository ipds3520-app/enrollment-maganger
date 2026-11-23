<?php

namespace App\Http\Controllers\Admin;

use App\Actions\IssueRegistrationTokens;
use App\Actions\IssueSharedRegistrationToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProcessInternshipRequest as ProcessInternshipRequestForm;
use App\Models\InternshipRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessInternshipRequestController extends Controller
{
    public function __invoke(
        ProcessInternshipRequestForm $request,
        int $internshipRequestId,
        IssueRegistrationTokens $issueRegistrationTokens,
        IssueSharedRegistrationToken $issueSharedRegistrationToken,
    ): RedirectResponse|Response {
        $data = $request->validated();
        $adminId = $request->user()->getKey();

        [$internshipRequest, $issuedTokens, $sharedTokenIssue] = DB::transaction(function () use ($data, $adminId, $internshipRequestId, $issueRegistrationTokens, $issueSharedRegistrationToken): array {
            $internshipRequest = InternshipRequest::query()
                ->lockForUpdate()
                ->findOrFail($internshipRequestId);

            if ($internshipRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'Pengajuan ini sudah diproses dan tidak dapat diputuskan kembali.',
                ]);
            }

            $internshipRequest->status = $data['decision'];
            $internshipRequest->processed_by = $adminId;
            $internshipRequest->decided_at = now();
            // Keep the existing database column for compatibility with prior records.
            $internshipRequest->reply_letter_path = $data['reply_letter_url'];
            $internshipRequest->save();

            $issuedTokens = [];
            $sharedTokenIssue = null;

            if ($data['decision'] === 'approved') {
                if ($internshipRequest->participant_count !== null) {
                    $sharedTokenIssue = $issueSharedRegistrationToken->forApprovedRequest($internshipRequest);
                } else {
                    $issuedTokens = $issueRegistrationTokens->forApprovedRequest($internshipRequest);
                }
            }

            return [$internshipRequest, $issuedTokens, $sharedTokenIssue];
        });

        if ($data['decision'] === 'approved') {
            if ($sharedTokenIssue !== null) {
                return response()->view('admin.shared-registration-tokens.issued', [
                    'internshipRequest' => $internshipRequest,
                    'sharedToken' => $sharedTokenIssue['token'],
                    'plaintextToken' => $sharedTokenIssue['plaintext'],
                ])->withHeaders($this->sensitiveHeaders());
            }

            return response()->view('admin.registration-tokens.issued', [
                'internshipRequest' => $internshipRequest,
                'issuedTokens' => $issuedTokens,
            ])->withHeaders([
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
            ]);
        }

        return redirect()
            ->route('admin.internship-requests.show', $internshipRequest)
            ->with('status', 'Keputusan pengajuan dan surat balasan berhasil disimpan.');
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
