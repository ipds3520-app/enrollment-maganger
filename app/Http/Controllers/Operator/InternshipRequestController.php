<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreInternshipRequest;
use App\Models\InternshipRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

        $internshipRequest = DB::transaction(function () use ($data, $institution, $user): InternshipRequest {
            $internshipRequest = $institution->internshipRequests()->create([
                'created_by' => $user->getKey(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);

            foreach ($data['applicants'] as $applicant) {
                $internshipRequest->applicants()->create($applicant);
            }

            return $internshipRequest;
        });

        return redirect()
            ->route('operator.internship-requests.show', $internshipRequest)
            ->with('status', 'Pengajuan permohonan magang berhasil disimpan.');
    }

    public function show(Request $request, int $internshipRequestId): View
    {
        /** @var User $user */
        $user = $request->user();
        $internshipRequest = $user->institution()
            ->firstOrFail()
            ->internshipRequests()
            ->with('applicants')
            ->findOrFail($internshipRequestId);

        return view('operator.internship-requests.show', [
            'internshipRequest' => $internshipRequest,
        ]);
    }
}
