<?php

namespace App\Http\Controllers;

use App\Models\InternshipRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReplyLetterDownloadController extends Controller
{
    public function admin(int $internshipRequestId): StreamedResponse
    {
        $internshipRequest = InternshipRequest::query()->findOrFail($internshipRequestId);

        return $this->download($internshipRequest);
    }

    public function operator(Request $request, int $internshipRequestId): StreamedResponse
    {
        /** @var User $operator */
        $operator = $request->user();
        $internshipRequest = $operator->institution()
            ->firstOrFail()
            ->internshipRequests()
            ->findOrFail($internshipRequestId);

        return $this->download($internshipRequest);
    }

    private function download(InternshipRequest $internshipRequest): StreamedResponse
    {
        abort_unless(
            in_array($internshipRequest->status, ['approved', 'rejected'], true)
                && is_string($internshipRequest->reply_letter_path)
                && $internshipRequest->reply_letter_path !== '',
            404,
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($internshipRequest->reply_letter_path), 404);

        return $disk->download(
            $internshipRequest->reply_letter_path,
            'surat-balasan-'.$internshipRequest->getKey().'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
