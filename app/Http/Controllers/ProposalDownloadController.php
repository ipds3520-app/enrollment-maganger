<?php

namespace App\Http\Controllers;

use App\Models\InternshipRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalDownloadController extends Controller
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
        $path = $internshipRequest->proposal_path;

        abort_unless(
            is_string($path)
                && preg_match('~\Aproposals/[1-9][0-9]*/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf\z~D', $path) === 1,
            404,
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->download(
            $path,
            'proposal-'.$internshipRequest->getKey().'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
