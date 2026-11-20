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
            <h1>Detail Pengajuan</h1>
            <p class="subtitle" style="margin-bottom:0">Informasi permohonan dan calon peserta.</p>
        </div>
        <span class="badge" style="text-transform:none;letter-spacing:0">{{ $statusLabel }}</span>
    </div>

    <section class="card" style="margin-bottom:18px">
        <h2>Informasi Permohonan</h2>
        <dl class="detail">
            <dt>Judul</dt><dd>{{ $internshipRequest->title }}</dd>
            <dt>Deskripsi</dt><dd>{{ $internshipRequest->description ?: '—' }}</dd>
            <dt>Tanggal Mulai</dt><dd>{{ $internshipRequest->start_date->format('d/m/Y') }}</dd>
            <dt>Tanggal Selesai</dt><dd>{{ $internshipRequest->end_date->format('d/m/Y') }}</dd>
            <dt>Jumlah peserta diajukan</dt><dd>{{ $internshipRequest->participant_count ?? $internshipRequest->applicants->count() }} peserta</dd>
            <dt>Diajukan</dt><dd>{{ $internshipRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            <dt>Status</dt><dd>{{ $statusLabel }}</dd>
        </dl>
    </section>

    @if ($internshipRequest->sharedRegistrationToken)
        @php($sharedToken = $internshipRequest->sharedRegistrationToken)
        <section class="card" style="margin-bottom:18px">
            <h2>Status Registrasi Peserta</h2>
            <dl class="detail">
                <dt>Kuota</dt><dd>{{ $sharedToken->quota }}</dd>
                <dt>Profil selesai</dt><dd>{{ $sharedToken->used_count }}</dd>
                <dt>Sisa kuota</dt><dd>{{ max(0, $sharedToken->quota - $sharedToken->used_count) }}</dd>
                <dt>Status token</dt><dd>{{ $sharedToken->status }}</dd>
            </dl>
            <p class="muted">Token dibagikan oleh Admin setelah persetujuan. Token asli tidak ditampilkan pada halaman ini.</p>
        </section>
    @endif

    <section class="card" style="margin-bottom:18px">
        <h2>Proposal Magang</h2>
        @if ($internshipRequest->proposal_path)
            <p style="margin:0"><a href="{{ route('operator.internship-requests.proposal', $internshipRequest) }}">Unduh proposal PDF</a></p>
        @else
            <p class="muted" style="margin:0">Proposal belum tersedia untuk pengajuan ini.</p>
        @endif
    </section>

    <section class="card">
        <h2>Peserta Terdaftar ({{ $internshipRequest->applicants->count() }} / {{ $internshipRequest->participant_count ?? $internshipRequest->applicants->count() }})</h2>
        <div style="display:grid;gap:12px">
            @foreach ($internshipRequest->applicants as $applicant)
                <article style="padding:14px;border:1px solid #dde3ee;border-radius:10px">
                    <h3 style="font-size:1rem;margin:0 0 10px">{{ $applicant->name }}</h3>
                    <dl class="detail">
                        <dt>Email</dt><dd>{{ $applicant->email }}</dd>
                        <dt>Telepon</dt><dd>{{ $applicant->phone ?: '—' }}</dd>
                        <dt>Nomor Induk</dt><dd>{{ $applicant->student_number ?: '—' }}</dd>
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
                </article>
            @endforeach
        </div>
    </section>

    @if (in_array($internshipRequest->status, ['approved', 'rejected'], true) && $internshipRequest->reply_letter_path)
        <p style="margin-top:18px">
            <a href="{{ route('operator.internship-requests.reply-letter', $internshipRequest) }}">Unduh surat balasan PDF</a>
        </p>
    @endif

    <p style="margin-top:18px"><a href="{{ route('operator.internship-requests.index') }}">Kembali ke daftar pengajuan</a></p>
@endsection
