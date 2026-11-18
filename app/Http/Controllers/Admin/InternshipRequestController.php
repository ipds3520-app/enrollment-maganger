<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternshipRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternshipRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $requests = InternshipRequest::query()
            ->with(['institution', 'creator'])
            ->withCount('applicants')
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true),
                fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $needle = '%'.mb_strtolower($search).'%';
                    $query->whereRaw('LOWER(title) LIKE ?', [$needle])
                        ->orWhereHas('institution', fn ($institution) => $institution->whereRaw('LOWER(name) LIKE ?', [$needle]))
                        ->orWhereHas('creator', fn ($creator) => $creator->whereRaw('LOWER(name) LIKE ?', [$needle]));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.internship-requests.index', [
            'requests' => $requests,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(int $internshipRequestId): View
    {
        $internshipRequest = InternshipRequest::query()
            ->with(['institution', 'creator', 'processor', 'applicants'])
            ->findOrFail($internshipRequestId);

        return view('admin.internship-requests.show', [
            'internshipRequest' => $internshipRequest,
        ]);
    }
}
