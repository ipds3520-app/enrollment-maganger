<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternPasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InternPasswordResetRequestController extends Controller
{
    public function index(): View
    {
        $requests = InternPasswordResetRequest::query()
            ->with(['intern.applicant.internshipRequest.institution', 'processor'])
            ->latest()
            ->paginate(20);

        return view('admin.intern-password-reset-requests.index', compact('requests'));
    }

    public function show(int $requestId): Response
    {
        $resetRequest = InternPasswordResetRequest::query()
            ->with(['intern.applicant.internshipRequest.institution', 'processor'])
            ->findOrFail($requestId);

        return response()->view('admin.intern-password-reset-requests.show', compact('resetRequest'))
            ->withHeaders($this->sensitiveHeaders());
    }

    public function approve(int $requestId): Response|RedirectResponse
    {
        $plaintextToken = bin2hex(random_bytes(32));
        $result = DB::transaction(function () use ($requestId, $plaintextToken): ?InternPasswordResetRequest {
            $resetRequest = InternPasswordResetRequest::query()->lockForUpdate()->findOrFail($requestId);

            if ($resetRequest->status !== 'pending') {
                return null;
            }

            $intern = $resetRequest->intern()->lockForUpdate()->firstOrFail();
            $now = now();

            $intern->passwordResetRequests()
                ->where('status', 'approved')
                ->whereNull('token_used_at')
                ->whereNull('token_revoked_at')
                ->whereNotNull('token_hash')
                ->lockForUpdate()
                ->get()
                ->each(function (InternPasswordResetRequest $previous) use ($now): void {
                    $previous->token_revoked_at = $now;
                    $previous->save();
                });

            $resetRequest->forceFill([
                'status' => 'approved',
                'processed_by' => Auth::guard('admin')->id(),
                'processed_at' => $now,
                'token_hash' => hash('sha256', $plaintextToken),
                'token_expires_at' => $now->copy()->addMinutes(30),
                'token_used_at' => null,
                'token_revoked_at' => null,
            ])->save();

            return $resetRequest->load('intern');
        });

        if ($result === null) {
            return redirect()->route('admin.intern-password-reset-requests.show', $requestId)
                ->with('status', 'Permintaan ini sudah diproses. Tautan reset tidak dapat ditampilkan kembali.');
        }

        $resetUrl = route('intern.password.reset').'#'.$plaintextToken;
        if (app()->environment('production')) {
            $resetUrl = preg_replace('/^http:/i', 'https:', $resetUrl) ?? $resetUrl;
        }
        unset($plaintextToken);

        return response()->view('admin.intern-password-reset-requests.issued', [
            'resetRequest' => $result,
            'resetUrl' => $resetUrl,
        ])->withHeaders($this->sensitiveHeaders());
    }

    public function reject(int $requestId): RedirectResponse
    {
        $rejected = DB::transaction(function () use ($requestId): bool {
            $resetRequest = InternPasswordResetRequest::query()->lockForUpdate()->findOrFail($requestId);

            if ($resetRequest->status !== 'pending') {
                return false;
            }

            $resetRequest->forceFill([
                'status' => 'rejected',
                'processed_by' => Auth::guard('admin')->id(),
                'processed_at' => now(),
            ])->save();

            return true;
        });

        return redirect()->route('admin.intern-password-reset-requests.show', $requestId)
            ->with('status', $rejected
                ? 'Permintaan reset password berhasil ditolak.'
                : 'Permintaan ini sudah diproses sebelumnya.');
    }

    /** @return array<string, string> */
    private function sensitiveHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
