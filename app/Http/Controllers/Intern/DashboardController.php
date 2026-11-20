<?php

namespace App\Http\Controllers\Intern;

use App\Models\UserIntern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        /** @var UserIntern $intern */
        $intern = $request->user('intern');
        $intern->load(['applicant.internshipRequest.institution', 'sharedRegistrationClaim']);

        if ($intern->sharedRegistrationClaim?->status === 'pending_profile') {
            return redirect()->route('intern.profile.show');
        }

        return view('intern.dashboard', [
            'intern' => $intern,
        ]);
    }
}
