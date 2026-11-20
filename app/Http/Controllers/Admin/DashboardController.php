<?php

namespace App\Http\Controllers\Admin;

use App\Models\InternPasswordResetRequest;
use App\Models\InternshipRequest;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController
{
    /**
     * Ringkasan admin: statistik pengajuan, akun, dan pengajuan terbaru.
     * Hanya query baca; alur persetujuan tetap berada pada controller terkait.
     */
    public function __invoke(Request $request): View
    {
        $statusCounts = InternshipRequest::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'pending' => (int) $statusCounts->get('pending', 0),
            'approved' => (int) $statusCounts->get('approved', 0),
            'rejected' => (int) $statusCounts->get('rejected', 0),
            'operators' => User::query()->where('role', 'operator')->count(),
            'interns' => UserIntern::query()->count(),
            'pending_password_resets' => InternPasswordResetRequest::query()->where('status', 'pending')->count(),
        ];

        $recentRequests = InternshipRequest::query()
            ->with(['institution', 'creator'])
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'user' => $request->user(),
            'stats' => $stats,
            'recentRequests' => $recentRequests,
        ]);
    }
}
