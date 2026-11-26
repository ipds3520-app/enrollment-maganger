<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInternParticipant;
use App\Http\Requests\Admin\UpdateInternParticipantStatus;
use App\Models\InternshipApplicant;
use App\Models\UserIntern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InternParticipantController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $status = in_array($status, ['active', 'completed', 'unset'], true) ? $status : '';

        $participants = UserIntern::query()
            ->with(['applicant.internshipRequest.institution'])
            ->whereHas('applicant')
            ->when($status === 'active' || $status === 'completed', function ($query) use ($status): void {
                $query->whereHas('applicant', fn ($applicant) => $applicant->where('participant_status', $status));
            })
            ->when($status === 'unset', function ($query): void {
                $query->whereHas('applicant', fn ($applicant) => $applicant->whereNull('participant_status'));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';
                $query->where(function ($query) use ($needle): void {
                    $query->whereRaw('LOWER(users_intern.name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(users_intern.email) LIKE ?', [$needle])
                        ->orWhereHas('applicant', function ($applicant) use ($needle): void {
                            $applicant->whereRaw('LOWER(name) LIKE ?', [$needle])
                                ->orWhereRaw('LOWER(student_number) LIKE ?', [$needle])
                                ->orWhereHas('internshipRequest.institution', fn ($institution) => $institution->whereRaw('LOWER(name) LIKE ?', [$needle]));
                        });
                });
            })
            ->orderByDesc('users_intern.id')
            ->paginate(10)
            ->withQueryString();

        return response()->view('admin.intern-participants.index', compact('participants', 'search', 'status'))
            ->withHeaders($this->sensitiveHeaders());
    }

    public function show(int $userInternId): Response
    {
        $intern = $this->participantQuery()->findOrFail($userInternId);
        $intern->applicant->load(['statusHistories.changedBy']);

        return response()->view('admin.intern-participants.show', ['intern' => $intern, 'archived' => false])
            ->withHeaders($this->sensitiveHeaders());
    }

    public function archivedIndex(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $participants = UserIntern::onlyTrashed()
            ->with(['applicant.internshipRequest.institution'])
            ->whereHas('applicant')
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';
                $query->where(function ($query) use ($needle): void {
                    $query->whereRaw('LOWER(users_intern.name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(users_intern.email) LIKE ?', [$needle])
                        ->orWhereHas('applicant', function ($applicant) use ($needle): void {
                            $applicant->whereRaw('LOWER(name) LIKE ?', [$needle])
                                ->orWhereRaw('LOWER(student_number) LIKE ?', [$needle])
                                ->orWhereHas('internshipRequest.institution', fn ($institution) => $institution->whereRaw('LOWER(name) LIKE ?', [$needle]));
                        });
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate(10)
            ->withQueryString();

        return response()->view('admin.intern-participants.archived-index', compact('participants', 'search'))
            ->withHeaders($this->sensitiveHeaders());
    }

    public function showArchived(int $userInternId): Response
    {
        $intern = UserIntern::onlyTrashed()
            ->with(['applicant.internshipRequest.institution', 'applicant.statusHistories.changedBy'])
            ->whereHas('applicant')
            ->findOrFail($userInternId);

        return response()->view('admin.intern-participants.show', ['intern' => $intern, 'archived' => true])
            ->withHeaders($this->sensitiveHeaders());
    }

    public function archive(int $userInternId): RedirectResponse
    {
        $intern = UserIntern::query()->findOrFail($userInternId);
        $applicantId = $intern->applicant_id;

        DB::transaction(function () use ($intern, $applicantId): void {
            InternshipApplicant::query()->whereKey($applicantId)->lockForUpdate()->firstOrFail();
            $lockedIntern = UserIntern::query()->lockForUpdate()->findOrFail($intern->getKey());
            $this->archiveAccount($lockedIntern);
        });

        return redirect()->route('admin.intern-participants.index')
            ->with('status', 'Peserta berhasil diarsipkan. Data profil dan riwayat tetap tersimpan.');
    }

    public function bulkArchive(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_intern_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_intern_ids.*' => ['required', 'integer', 'distinct', 'exists:users_intern,id'],
        ], [
            'user_intern_ids.required' => 'Pilih setidaknya satu peserta untuk diarsipkan.',
            'user_intern_ids.array' => 'Daftar peserta tidak valid.',
            'user_intern_ids.min' => 'Pilih setidaknya satu peserta untuk diarsipkan.',
            'user_intern_ids.max' => 'Maksimal 100 peserta dapat diarsipkan dalam satu proses.',
            'user_intern_ids.*.distinct' => 'ID peserta duplikat. Tidak ada peserta yang diarsipkan.',
            'user_intern_ids.*.exists' => 'Satu atau lebih peserta tidak ditemukan. Tidak ada peserta yang diarsipkan.',
        ]);

        $ids = array_map('intval', $validated['user_intern_ids']);

        try {
            DB::transaction(function () use ($ids): void {
                $selected = UserIntern::query()
                    ->whereIn('id', $ids)
                    ->orderBy('id')
                    ->get(['id', 'applicant_id']);

                if ($selected->count() !== count($ids)) {
                    throw ValidationException::withMessages([
                        'user_intern_ids' => 'Satu atau lebih peserta sudah diarsipkan atau tidak aktif. Tidak ada perubahan yang disimpan.',
                    ]);
                }

                $applicantIds = $selected->pluck('applicant_id')->unique()->sort()->values();
                $lockedApplicants = InternshipApplicant::query()
                    ->whereIn('id', $applicantIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                if ($lockedApplicants->count() !== $applicantIds->count()) {
                    throw ValidationException::withMessages([
                        'user_intern_ids' => 'Profil salah satu peserta tidak tersedia. Tidak ada perubahan yang disimpan.',
                    ]);
                }

                $lockedInterns = UserIntern::query()
                    ->whereIn('id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($lockedInterns->count() !== count($ids)) {
                    throw ValidationException::withMessages([
                        'user_intern_ids' => 'Satu atau lebih peserta sudah berubah status. Tidak ada perubahan yang disimpan.',
                    ]);
                }

                $expectedApplicants = $selected->pluck('applicant_id', 'id');

                foreach ($lockedInterns as $intern) {
                    if ((int) $intern->applicant_id !== (int) $expectedApplicants[$intern->getKey()]) {
                        throw ValidationException::withMessages([
                            'user_intern_ids' => 'Relasi profil salah satu peserta berubah. Tidak ada perubahan yang disimpan.',
                        ]);
                    }
                }

                foreach ($lockedInterns as $intern) {
                    $this->archiveAccount($intern);
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.intern-participants.index', $this->listQuery($request, true))
                ->withErrors(['user_intern_ids' => 'Batch gagal diproses. Tidak ada peserta yang diubah; silakan coba kembali.']);
        }

        return redirect()->route('admin.intern-participants.index', $this->listQuery($request, true))
            ->with('status', count($ids).' peserta berhasil diarsipkan. Data profil dan riwayat tetap tersimpan.');
    }

    public function restore(int $userInternId): RedirectResponse
    {
        DB::transaction(function () use ($userInternId): void {
            $intern = UserIntern::onlyTrashed()->lockForUpdate()->findOrFail($userInternId);
            $intern->restore();
        });

        return redirect()->route('admin.intern-participants.index')
            ->with('status', 'Peserta berhasil dipulihkan.');
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_intern_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_intern_ids.*' => ['required', 'integer', 'distinct', 'exists:users_intern,id'],
        ], [
            'user_intern_ids.required' => 'Pilih setidaknya satu peserta untuk dipulihkan.',
            'user_intern_ids.array' => 'Daftar peserta tidak valid.',
            'user_intern_ids.min' => 'Pilih setidaknya satu peserta untuk dipulihkan.',
            'user_intern_ids.max' => 'Maksimal 100 peserta dapat dipulihkan dalam satu proses.',
            'user_intern_ids.*.distinct' => 'ID peserta duplikat. Tidak ada peserta yang dipulihkan.',
            'user_intern_ids.*.exists' => 'Satu atau lebih peserta tidak ditemukan. Tidak ada peserta yang dipulihkan.',
        ]);

        $ids = array_map('intval', $validated['user_intern_ids']);

        try {
            DB::transaction(function () use ($ids): void {
                $lockedInterns = UserIntern::onlyTrashed()
                    ->whereIn('id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($lockedInterns->count() !== count($ids)) {
                    throw ValidationException::withMessages([
                        'user_intern_ids' => 'Satu atau lebih akun tidak berada dalam arsip. Tidak ada peserta yang dipulihkan.',
                    ]);
                }

                foreach ($lockedInterns as $intern) {
                    $intern->restore();
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.intern-participants.archived.index', $this->listQuery($request))
                ->withErrors(['user_intern_ids' => 'Batch gagal diproses. Tidak ada peserta yang diubah; silakan coba kembali.']);
        }

        return redirect()->route('admin.intern-participants.archived.index', $this->listQuery($request))
            ->with('status', count($ids).' peserta berhasil dipulihkan.');
    }

    public function updateLifecycle(UpdateInternParticipantStatus $request, int $userInternId): RedirectResponse
    {
        $status = $request->validated('participant_status');
        $adminId = $request->user('admin')->getKey();
        $intern = UserIntern::query()->findOrFail($userInternId);

        DB::transaction(function () use ($intern, $status, $adminId): void {
            $applicant = InternshipApplicant::query()
                ->whereKey($intern->applicant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedIntern = UserIntern::query()->lockForUpdate()->findOrFail($intern->getKey());

            if ((int) $lockedIntern->applicant_id !== (int) $applicant->getKey()) {
                abort(409, 'Relasi akun dan profil peserta berubah. Muat ulang halaman lalu coba kembali.');
            }

            $oldStatus = $applicant->participant_status;

            if ($oldStatus === $status) {
                return;
            }

            $changedAt = now();
            $applicant->forceFill([
                'participant_status' => $status,
                'completed_at' => $status === 'completed' ? $changedAt : null,
            ])->save();

            $applicant->statusHistories()->create([
                'old_status' => $oldStatus,
                'new_status' => $status,
                'changed_by' => $adminId,
                'changed_at' => $changedAt,
            ]);
        });

        return redirect()->route('admin.intern-participants.show', $userInternId)
            ->with('status', 'Status lifecycle peserta berhasil diperbarui.');
    }

    public function edit(int $userInternId): Response
    {
        $intern = $this->participantQuery()->findOrFail($userInternId);

        return response()->view('admin.intern-participants.edit', [
            'intern' => $intern,
            'applicant' => $intern->applicant,
        ])->withHeaders($this->sensitiveHeaders());
    }

    public function update(UpdateInternParticipant $request, int $userInternId): RedirectResponse
    {
        $data = $request->validated();
        $intern = UserIntern::query()->findOrFail($userInternId);
        $applicantId = $intern->applicant_id;

        try {
            DB::transaction(function () use ($intern, $applicantId, $data): void {
                // Keep the same applicant-then-account lock order as intern profile updates.
                $applicant = InternshipApplicant::query()
                    ->whereKey($applicantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedIntern = UserIntern::query()
                    ->lockForUpdate()
                    ->findOrFail($intern->getKey());

                if ((int) $lockedIntern->applicant_id !== (int) $applicant->getKey()) {
                    abort(409, 'Relasi akun dan profil peserta berubah. Muat ulang halaman lalu coba kembali.');
                }

                $applicant->forceFill([
                    'name' => $data['name'],
                    'student_number' => $data['student_number'] ?? null,
                    'school_major' => $data['school_major'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'birth_date' => $data['birth_date'] ?? null,
                    'gender' => $data['gender'] ?? null,
                ])->save();

                $lockedIntern->forceFill([
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                ])->save();
            });
        } catch (QueryException $exception) {
            if ($this->isStudentNumberConflict($exception)) {
                throw ValidationException::withMessages([
                    'student_number' => 'NIS/NIM sudah digunakan peserta lain dalam pengajuan ini.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('admin.intern-participants.show', $userInternId)
            ->with('status', 'Data peserta magang berhasil diperbarui.');
    }

    private function participantQuery(): Builder
    {
        return UserIntern::query()
            ->with(['applicant.internshipRequest.institution'])
            ->whereHas('applicant');
    }

    private function isStudentNumberConflict(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && (
                str_contains($message, 'internship_applicants_request_student_unique')
                || str_contains($message, 'internship_applicants.student_number')
            );
    }

    private function archiveAccount(UserIntern $intern): void
    {
        $intern->forceFill([
            'auth_version' => (int) $intern->auth_version + 1,
            'remember_token' => Str::random(60),
        ])->save();
        $intern->delete();
    }

    /** @return array<string, string|int> */
    private function listQuery(Request $request, bool $includeStatus = false): array
    {
        $query = [
            'q' => trim((string) $request->query('q', '')),
        ];

        if ($includeStatus) {
            $query['status'] = (string) $request->query('status', '');
        }

        $query['page'] = max(1, (int) $request->query('page', 1));

        return array_filter($query, fn (string|int $value): bool => $value !== '');
    }

    /** @return array<string, string> */
    private function sensitiveHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
