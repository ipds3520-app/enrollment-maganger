<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternshipRequest;
use App\Models\SharedRegistrationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedRegistrationTokenController extends Controller
{
    public function revoke(Request $request, int $internshipRequestId): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        DB::transaction(function () use ($internshipRequestId): void {
            InternshipRequest::query()->lockForUpdate()->findOrFail($internshipRequestId);
            $token = SharedRegistrationToken::query()
                ->where('internship_request_id', $internshipRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($token->status !== 'active') {
                throw ValidationException::withMessages([
                    'token' => 'Token tidak lagi aktif dan tidak dapat dicabut.',
                ]);
            }

            $token->status = 'revoked';
            $token->save();
        });

        return redirect()
            ->route('admin.internship-requests.show', $internshipRequestId)
            ->with('status', 'Shared registration token berhasil dicabut. Data peserta yang sudah terdaftar tetap dipertahankan.');
    }
}
