<?php

namespace App\Http\Controllers\Operator;

use App\Models\InternshipRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController
{
    /**
     * Ringkasan operator untuk institusinya sendiri.
     * Institusi diambil dari relasi pengguna yang sedang login.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $institution = $user->institution;

        $statusCounts = collect();
        $recentRequests = collect();

        if ($institution !== null) {
            $statusCounts = InternshipRequest::query()
                ->where('institution_id', $institution->getKey())
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $recentRequests = InternshipRequest::query()
                ->where('institution_id', $institution->getKey())
                ->withCount('applicants')
                ->latest()
                ->limit(5)
                ->get();
        }

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'pending' => (int) $statusCounts->get('pending', 0),
            'approved' => (int) $statusCounts->get('approved', 0),
            'rejected' => (int) $statusCounts->get('rejected', 0),
        ];

        return view('operator.dashboard', [
            'user' => $user,
            'institution' => $institution,
            'stats' => $stats,
            'recentRequests' => $recentRequests,
        ]);
    }
}
