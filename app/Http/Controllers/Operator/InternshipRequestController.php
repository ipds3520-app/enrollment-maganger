<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreInternshipRequest;
use App\Models\InternshipRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class InternshipRequestController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $institution = $user->institution()->firstOrFail();
        $requests = $institution->internshipRequests()
            ->withCount('applicants')
            ->latest()
            ->paginate(10);

        return view('operator.internship-requests.index', [
            'requests' => $requests,
        ]);
    }

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->institution()->firstOrFail();

        return view('operator.internship-requests.create');
    }

    public function store(StoreInternshipRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $institution = $user->institution()->firstOrFail();
        $data = $request->validated();
        $proposal = $request->file('proposal');
        $newProposalPath = null;
        $disk = Storage::disk('local');

        try {
            $internshipRequest = DB::transaction(function () use ($data, $institution, $user, $proposal, $disk, &$newProposalPath): InternshipRequest {
                $internshipRequest = $institution->internshipRequests()->create([
                    'created_by' => $user->getKey(),
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'participant_count' => $data['participant_count'],
                ]);

                $directory = 'proposals/'.$internshipRequest->getKey();
                do {
                    $newProposalPath = $directory.'/'.Str::uuid().'.pdf';
                } while ($disk->exists($newProposalPath));

                $storedPath = $proposal->storeAs($directory, basename($newProposalPath), 'local');

                if ($storedPath !== $newProposalPath || ! $disk->exists($newProposalPath)) {
                    throw new RuntimeException('File proposal gagal disimpan pada disk privat.');
                }

                // Assign explicitly: proposal_path is controlled by the server and not mass-assigned.
                $internshipRequest->proposal_path = $newProposalPath;
                $internshipRequest->save();

                return $internshipRequest;
            });
        } catch (Throwable $exception) {
            if ($newProposalPath !== null && $disk->exists($newProposalPath) && ! $disk->delete($newProposalPath)) {
                Log::warning('Gagal membersihkan file proposal setelah transaksi dibatalkan.', [
                    'path' => $newProposalPath,
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('operator.internship-requests.show', $internshipRequest)
            ->with('status', 'Pengajuan permohonan magang berhasil disimpan.');
    }

    public function show(Request $request, int $internshipRequestId): Response
    {
        /** @var User $user */
        $user = $request->user();
        $internshipRequest = $user->institution()
            ->firstOrFail()
            ->internshipRequests()
            ->with(['applicants.sharedRegistrationClaim', 'sharedRegistrationToken'])
            ->findOrFail($internshipRequestId);

        return response()->view('operator.internship-requests.show', [
            'internshipRequest' => $internshipRequest,
        ])->withHeaders($this->sensitiveHeaders());
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
