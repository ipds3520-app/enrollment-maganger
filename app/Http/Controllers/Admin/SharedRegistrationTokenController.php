<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSharedRegistrationTokenQuota;
use App\Models\InternshipRequest;
use App\Models\SharedRegistrationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SharedRegistrationTokenController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $tokens = SharedRegistrationToken::query()
            ->with(['internshipRequest.institution', 'internshipRequest.creator', 'quotaUpdater'])
            ->when(in_array($status, ['active', 'exhausted', 'revoked'], true),
                fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';
                $query->whereHas('internshipRequest', function ($request) use ($needle): void {
                    $request->whereRaw('LOWER(title) LIKE ?', [$needle])
                        ->orWhereHas('institution', fn ($institution) => $institution->whereRaw('LOWER(name) LIKE ?', [$needle]))
                        ->orWhereHas('creator', fn ($creator) => $creator->whereRaw('LOWER(name) LIKE ?', [$needle]));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.shared-registration-tokens.index', [
            'tokens' => $tokens,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function updateQuota(UpdateSharedRegistrationTokenQuota $request, int $internshipRequestId): RedirectResponse
    {
        $newQuota = (int) $request->validated('quota');
        $adminId = $request->user('admin')->getKey();

        [$oldQuota, $savedQuota] = DB::transaction(function () use ($internshipRequestId, $newQuota, $adminId): array {
            $internshipRequest = InternshipRequest::query()
                ->lockForUpdate()
                ->findOrFail($internshipRequestId);

            if ($internshipRequest->status !== 'approved') {
                throw ValidationException::withMessages([
                    'quota' => 'Kuota hanya dapat diubah pada pengajuan yang telah disetujui.',
                ]);
            }

            $token = SharedRegistrationToken::query()
                ->where('internship_request_id', $internshipRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($newQuota < $token->used_count) {
                throw ValidationException::withMessages([
                    'quota' => 'Kuota baru tidak boleh lebih kecil daripada jumlah peserta yang sudah melengkapi profil.',
                ]);
            }

            $oldQuota = $token->quota;
            $token->quota = $newQuota;
            $token->quota_updated_by = $adminId;
            $token->quota_updated_at = now();

            if ($token->status !== 'revoked') {
                $token->status = $newQuota === $token->used_count ? 'exhausted' : 'active';
            }

            $token->save();

            return [$oldQuota, $token->quota];
        });

        return redirect()
            ->route('admin.internship-requests.show', $internshipRequestId)
            ->with('status', "Kuota peserta diperbarui dari {$oldQuota} menjadi {$savedQuota} peserta.");
    }

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
