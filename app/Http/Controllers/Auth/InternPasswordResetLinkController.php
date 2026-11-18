<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreInternPasswordResetRequest;
use App\Models\UserIntern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class InternPasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return response()->view('auth.intern-forgot-password')->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function store(StoreInternPasswordResetRequest $request): RedirectResponse
    {
        $email = $request->validated('email');
        $intern = UserIntern::query()->where('email', $email)->first();

        if ($intern !== null) {
            DB::transaction(function () use ($intern): void {
                $lockedIntern = UserIntern::query()->lockForUpdate()->find($intern->getKey());

                if ($lockedIntern === null || $lockedIntern->passwordResetRequests()->where('status', 'pending')->exists()) {
                    return;
                }

                $lockedIntern->passwordResetRequests()->create(['status' => 'pending']);
            });
        }

        return back()->with('status', 'Jika akun tersebut terdaftar, permintaan reset password telah dicatat untuk diperiksa Admin.');
    }
}
