<?php

namespace App\Http\Controllers\Intern;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController
{
    public function __invoke(Request $request): View
    {
        return view('intern.dashboard', [
            'intern' => $request->user('intern'),
        ]);
    }
}
