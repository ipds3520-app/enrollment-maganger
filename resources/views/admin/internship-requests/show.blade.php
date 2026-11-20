@extends('layouts.app')

@section('title', 'Detail Pengajuan Magang')

@section('content')
    @php
        $statusLabel = match ($internshipRequest->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu diproses',
        };
    @endphp

    @if (session('status'))
        <p class="notice" role="status" style="margin-bottom:16px">{{ session('status') }}</p>
    @endif

    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
        <div>
            <h1>Detail Pengajuan Magang</h1>
            <p class="subtitle" style="margin-bottom:0">Periksa permohonan dan data seluruh peserta.</p>
        </div>
        <span class="badge" style="text-transform:none;letter-spacing:0">{{ $statusLabel }}</span>
    </div>

    <section class="card" style="margin-bottom:18px">
        <h2>Institusi dan Pengaju</h2>
        <dl class="detail">
            <dt>Institusi</dt><dd>{{ $internshipRequest->institution->name }}</dd>
            <dt>Alamat</dt><dd>{{ $internshipRequest->institution->address ?: '—' }}</dd>
            <dt>Operator</dt><dd>{{ $internshipRequest->creator->name }}</dd>
            <dt>Email operator</dt><dd>{{ $internshipRequest->creator->email }}</dd>
        </dl>
    </section>

    <section class="card" style="margin-bottom:18px">
        <h2>Informasi Permohonan</h2>
        <dl class="detail">
            <dt>Judul</dt><dd>{{ $internshipRequest->title }}</dd>
            <dt>Deskripsi</dt><dd>{{ $internshipRequest->description ?: '—' }}</dd>
            <dt>Tanggal mulai</dt><dd>{{ $internshipRequest->start_date->format('d/m/Y') }}</dd>
            <dt>Tanggal selesai</dt><dd>{{ $internshipRequest->end_date->format('d/m/Y') }}</dd>
            <dt>Jumlah peserta diajukan</dt><dd>{{ $internshipRequest->participant_count ?? $internshipRequest->applicants->count() }} peserta</dd>
            <dt>Diajukan</dt><dd>{{ $internshipRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            <dt>Status</dt><dd>{{ $statusLabel }}</dd>
            @if ($internshipRequest->processed_by)
                <dt>Diproses oleh</dt><dd>{{ $internshipRequest->processor?->name ?? '—' }}</dd>
                <dt>Waktu keputusan</dt><dd>{{ $internshipRequest->decided_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            @endif
        </dl>
    </section>

    <section class="card" style="margin-bottom:18px">
        <h2>Peserta Terdaftar ({{ $internshipRequest->applicants->count() }} / {{ $internshipRequest->participant_count ?? $internshipRequest->applicants->count() }})</h2>
        <div style="display:grid;gap:12px">
            @foreach ($internshipRequest->applicants as $applicant)
                <article style="padding:14px;border:1px solid #dde3ee;border-radius:10px">
                    <h3 style="font-size:1rem;margin:0 0 10px">{{ $applicant->name }}</h3>
                    <dl class="detail">
                        <dt>Email</dt><dd>{{ $applicant->email }}</dd>
                        <dt>Telepon</dt><dd>{{ $applicant->phone ?: '—' }}</dd>
                        <dt>Nomor induk</dt><dd>{{ $applicant->student_number ?: '—' }}</dd>
                        <dt>Jurusan</dt><dd>{{ $applicant->school_major ?: '—' }}</dd>
                        @if ($applicant->nik)
                            <dt>NIK</dt><dd>{{ $applicant->nik }}</dd>
                            <dt>Asal kampus/sekolah</dt><dd>{{ $applicant->school_name ?: '—' }}</dd>
                            <dt>Alamat</dt><dd>{{ $applicant->address ?: '—' }}</dd>
                            <dt>Tanggal lahir</dt><dd>{{ $applicant->birth_date?->format('d/m/Y') ?? '—' }}</dd>
                            <dt>Jenis kelamin</dt><dd>{{ $applicant->gender ?: '—' }}</dd>
                        @endif
                        @if ($applicant->sharedRegistrationClaim)
                            <dt>Status profil</dt><dd>{{ $applicant->sharedRegistrationClaim->status === 'completed' ? 'Lengkap' : 'Belum lengkap' }}</dd>
                        @endif
                    </dl>

                    @if ($internshipRequest->status === 'approved' && ! $internshipRequest->sharedRegistrationToken)
                        @php
                            $tokenHistory = $applicant->registrationTokens->sortByDesc('id');
                            $latestToken = $tokenHistory->first();
                            $hasUsedToken = $tokenHistory->contains(fn ($token) => $token->status === 'used');
                            $hasExpiredActiveToken = $tokenHistory->contains(fn ($token) => $token->status === 'active' && $token->expires_at->isPast());
                            $hasAccount = in_array($applicant->id, $accountApplicantIds, true);
                        @endphp
                        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #dde3ee">
                            <h4 style="margin:0 0 8px">Registration Token</h4>
                            @forelse ($tokenHistory as $token)
                                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:8px 0">
                                    <span>Status: <strong>{{ $token->status }}</strong></span>
                                    <span>Kedaluwarsa: {{ $token->expires_at->format('d/m/Y H:i') }}</span>
                                    @if ($token->used_at)
                                        <span>Digunakan: {{ $token->used_at->format('d/m/Y H:i') }}</span>
                                    @endif
                                    @if ($token->status === 'active' && ! $hasAccount)
                                        <form method="POST" action="{{ route('admin.internship-requests.registration-tokens.revoke', [$internshipRequest, $token]) }}">
                                            @csrf
                                            <button type="submit" style="padding:6px 10px;border:1px solid #b91c1c;border-radius:6px;background:#fff;color:#b91c1c;cursor:pointer">Cabut token</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="muted" style="margin:0 0 8px">Belum ada token untuk peserta ini.</p>
                            @endforelse

                            @if (! $hasAccount && ! $hasUsedToken && $latestToken && ($latestToken->status === 'revoked' || $hasExpiredActiveToken))
                                <form method="POST" action="{{ route('admin.internship-requests.registration-tokens.replace', [$internshipRequest, $applicant]) }}" style="margin-top:8px">
                                    @csrf
                                    <button type="submit" style="padding:7px 11px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer">Terbitkan token pengganti</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    @if ($internshipRequest->sharedRegistrationToken)
        @php($sharedToken = $internshipRequest->sharedRegistrationToken)
        <section class="card" style="margin-bottom:18px">
            <h2>Shared Registration Token</h2>
            <dl class="detail">
                <dt>Kuota</dt><dd>{{ $sharedToken->quota }}</dd>
                <dt>Terpakai</dt><dd>{{ $sharedToken->used_count }}</dd>
                <dt>Sisa</dt><dd>{{ max(0, $sharedToken->quota - $sharedToken->used_count) }}</dd>
                <dt>Status</dt><dd>{{ $sharedToken->status }}</dd>
            </dl>
            <p class="muted">Plaintext token tidak dapat dilihat kembali. Operator menerima token dari Admin yang meneruskannya setelah approval.</p>
            @if ($sharedToken->status === 'active')
                <form method="POST" action="{{ route('admin.internship-requests.shared-registration-token.revoke', $internshipRequest) }}">
                    @csrf
                    <button type="submit" style="padding:7px 11px;border:1px solid #b91c1c;border-radius:6px;background:#fff;color:#b91c1c;cursor:pointer">Cabut shared token</button>
                </form>
            @endif
        </section>
    @endif

    <section class="card" style="margin-bottom:18px">
        <h2>Proposal Magang</h2>
        @if ($internshipRequest->proposal_path)
            <p style="margin:0"><a href="{{ route('admin.internship-requests.proposal', $internshipRequest) }}">Unduh proposal PDF</a></p>
        @else
            <p class="muted" style="margin:0">Proposal belum tersedia untuk pengajuan ini.</p>
        @endif
    </section>

    @if ($internshipRequest->status === 'pending')
        <section class="card" style="margin-bottom:18px">
            <h2>Keputusan Admin</h2>
            @if ($errors->any())
                <div role="alert" style="margin-bottom:14px;color:#b91c1c">
                    <ul style="margin:0;padding-left:20px">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.internship-requests.decision', $internshipRequest) }}" enctype="multipart/form-data" style="display:grid;gap:14px">
                @csrf
                <label for="decision">Keputusan
                    <select id="decision" name="decision" required style="display:block;width:100%;max-width:420px;padding:10px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
                        <option value="">Pilih keputusan</option>
                        <option value="approved" @selected(old('decision') === 'approved')>Setujui</option>
                        <option value="rejected" @selected(old('decision') === 'rejected')>Tolak</option>
                    </select>
                </label>
                <label for="reply_letter">Surat balasan PDF dari TU (maksimal 10 MB)
                    <input id="reply_letter" name="reply_letter" type="file" accept=".pdf,application/pdf" required style="display:block;width:100%;max-width:520px;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
                </label>
                <p class="muted" style="margin:0">Keputusan berlaku untuk seluruh peserta pada pengajuan ini.</p>
                <button type="submit" style="justify-self:start;padding:11px 17px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer">Simpan Keputusan</button>
            </form>
        </section>
    @elseif ($internshipRequest->reply_letter_path)
        <p><a href="{{ route('admin.internship-requests.reply-letter', $internshipRequest) }}">Unduh surat balasan PDF</a></p>
    @endif

    <p><a href="{{ route('admin.internship-requests.index') }}">Kembali ke daftar pengajuan</a></p>
@endsection
