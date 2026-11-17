<?php

namespace App\Http\Controllers\Operator;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController
{
    /**
     * Dashboard sementara operator (placeholder Tahap 3B-1).
     * Institusi diambil dari relasi model milik pengguna yang sedang login,
     * bukan dari input pengguna.
     */
    public function __invoke(Request $request): View
    {
        return view('operator.dashboard', [
            'user' => $request->user(),
            'institution' => $request->user()->institution,
        ]);
    }
}
