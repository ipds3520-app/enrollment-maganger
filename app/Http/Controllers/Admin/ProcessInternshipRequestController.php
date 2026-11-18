<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProcessInternshipRequest as ProcessInternshipRequestForm;
use App\Models\InternshipRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProcessInternshipRequestController extends Controller
{
    public function __invoke(ProcessInternshipRequestForm $request, int $internshipRequestId): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('reply_letter');
        $adminId = $request->user()->getKey();
        $newPath = null;
        $disk = Storage::disk('local');

        try {
            $internshipRequest = DB::transaction(function () use ($data, $file, $adminId, $internshipRequestId, $disk, &$newPath): InternshipRequest {
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

                return $internshipRequest;
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

        return redirect()
            ->route('admin.internship-requests.show', $internshipRequest)
            ->with('status', 'Keputusan pengajuan dan surat balasan berhasil disimpan.');
    }
}
