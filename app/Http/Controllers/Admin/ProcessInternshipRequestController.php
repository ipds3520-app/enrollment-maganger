<?php

namespace App\Http\Controllers\Admin;

use App\Actions\IssueRegistrationTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProcessInternshipRequest as ProcessInternshipRequestForm;
use App\Models\InternshipRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProcessInternshipRequestController extends Controller
{
    public function __invoke(
        ProcessInternshipRequestForm $request,
        int $internshipRequestId,
        IssueRegistrationTokens $issueRegistrationTokens,
    ): RedirectResponse|Response {
        $data = $request->validated();
        $file = $request->file('reply_letter');
        $adminId = $request->user()->getKey();
        $newPath = null;
        $disk = Storage::disk('local');

        try {
            [$internshipRequest, $issuedTokens] = DB::transaction(function () use ($data, $file, $adminId, $internshipRequestId, $disk, $issueRegistrationTokens, &$newPath): array {
                $internshipRequest = InternshipRequest::query()
                    ->lockForUpdate()
                    ->findOrFail($internshipRequestId);

                if ($internshipRequest->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'decision' => 'Pengajuan ini sudah diproses dan tidak dapat diputuskan kembali.',
                    ]);
                }

                $directory = 'reply-letters';
                do {
                    $newPath = $directory.'/'.Str::uuid().'.pdf';
                } while ($disk->exists($newPath));

                $storedPath = $file->storeAs($directory, basename($newPath), 'local');

                if ($storedPath !== $newPath || ! $disk->exists($newPath)) {
                    throw new RuntimeException('File surat balasan gagal disimpan pada disk privat.');
                }

                $internshipRequest->status = $data['decision'];
                $internshipRequest->processed_by = $adminId;
                $internshipRequest->decided_at = now();
                $internshipRequest->reply_letter_path = $newPath;
                $internshipRequest->save();

                $issuedTokens = $data['decision'] === 'approved'
                    ? $issueRegistrationTokens->forApprovedRequest($internshipRequest)
                    : [];

                return [$internshipRequest, $issuedTokens];
            });
        } catch (Throwable $exception) {
            if ($newPath !== null && $disk->exists($newPath) && ! $disk->delete($newPath)) {
                Log::warning('Gagal membersihkan file surat balasan setelah transaksi dibatalkan.', [
                    'path' => $newPath,
                    'internship_request_id' => $internshipRequestId,
                ]);
            }

            throw $exception;
        }

        if ($data['decision'] === 'approved') {
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
}
