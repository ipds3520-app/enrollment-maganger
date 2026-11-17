<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController
{
    /**
     * Dashboard sementara admin (placeholder Tahap 3B-1).
     * Data diambil dari pengguna yang sedang login.
     */
    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'user' => $request->user(),
        ]);
    }
}
