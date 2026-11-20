@extends('layouts.app')

@section('title', 'Detail Pengajuan Magang')

@section('content')
    @php
        $statusLabel = match ($internshipRequest->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu diproses',
        };
        $statusClass = match ($internshipRequest->status) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default => 'badge-warning',
        };
    @endphp

    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Detail Pengajuan Magang</h1>
            <p class="page-description">Informasi permohonan dan calon peserta dari institusi Anda.</p>
        </div>
        <div class="page-actions">
            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
            <a class="btn btn-secondary" href="{{ route('operator.internship-requests.index') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Daftar pengajuan</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">
            @include('layouts.partials.icon', ['name' => 'check-circle'])
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="page-stack">
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Informasi Permohonan</h2>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <p class="detail-label">Judul</p>
                    <p class="detail-value">{{ $internshipRequest->title }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Status</p>
                    <p class="detail-value">{{ $statusLabel }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Tanggal mulai</p>
                    <p class="detail-value">{{ $internshipRequest->start_date->format('d/m/Y') }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Tanggal selesai</p>
                    <p class="detail-value">{{ $internshipRequest->end_date->format('d/m/Y') }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Jumlah peserta diajukan</p>
                    <p class="detail-value">{{ $internshipRequest->participant_count ?? $internshipRequest->applicants->count() }} peserta</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Diajukan</p>
                    <p class="detail-value">{{ $internshipRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
                <div class="detail-item detail-item-wide">
                    <p class="detail-label">Deskripsi</p>
                    <p class="detail-value muted">{{ $internshipRequest->description ?: '—' }}</p>
                </div>
            </div>
        </section>

        @if ($internshipRequest->sharedRegistrationToken)
            @php
                $sharedToken = $internshipRequest->sharedRegistrationToken;
                $sharedStatusLabel = match ($sharedToken->status) {
                    'active' => 'Aktif',
                    'exhausted' => 'Kuota habis',
                    'revoked' => 'Dicabut',
                    default => ucfirst($sharedToken->status),
                };
                $sharedStatusClass = match ($sharedToken->status) {
                    'active' => 'badge-success',
                    'revoked' => 'badge-danger',
                    default => 'badge-neutral',
                };
            @endphp
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Status Registrasi Peserta</h2>
                        <p class="card-description">Kuota peserta berdasarkan persetujuan admin.</p>
                    </div>
                    <span class="badge {{ $sharedStatusClass }}">{{ $sharedStatusLabel }}</span>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <p class="detail-label">Kuota</p>
                        <p class="detail-value">{{ $sharedToken->quota }} peserta</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Profil selesai</p>
                        <p class="detail-value">{{ $sharedToken->used_count }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Sisa kuota</p>
                        <p class="detail-value">{{ max(0, $sharedToken->quota - $sharedToken->used_count) }}</p>
                    </div>
                </div>

                <p class="card-note">Token dibagikan oleh Admin setelah persetujuan. Token asli tidak ditampilkan pada halaman ini.</p>
            </section>
        @endif

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Proposal Magang</h2>
            </div>
            @if ($internshipRequest->proposal_path)
                <a class="btn btn-secondary" href="{{ route('operator.internship-requests.proposal', $internshipRequest) }}">
                    @include('layouts.partials.icon', ['name' => 'download'])
                    <span>Unduh proposal PDF</span>
                </a>
            @else
                <p class="muted">Proposal belum tersedia untuk pengajuan ini.</p>
            @endif
        </section>

        <section class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Peserta Terdaftar</h2>
                    <p class="card-description">
                        {{ $internshipRequest->applicants->count() }} / {{ $internshipRequest->participant_count ?? $internshipRequest->applicants->count() }} peserta
                    </p>
                </div>
            </div>

            @if ($internshipRequest->applicants->isEmpty())
                <div class="empty-state">
                    @include('layouts.partials.icon', ['name' => 'users'])
                    <h3 class="empty-state-title">Belum ada peserta</h3>
                    <p class="empty-state-text">Peserta akan muncul setelah mendaftar menggunakan token yang dibagikan.</p>
                </div>
            @else
                <div class="list-stack">
                    @foreach ($internshipRequest->applicants as $applicant)
                        <article class="list-item">
                            <div class="list-item-header">
                                <div>
                                    <h3 class="list-item-title">{{ $applicant->name }}</h3>
                                    <p class="list-item-subtitle">{{ $applicant->email }}</p>
                                </div>
                                @if ($applicant->sharedRegistrationClaim)
                                    @php
                                        $profileComplete = $applicant->sharedRegistrationClaim->status === 'completed';
                                    @endphp
                                    <span class="badge {{ $profileComplete ? 'badge-success' : 'badge-warning' }}">
                                        {{ $profileComplete ? 'Profil lengkap' : 'Profil belum lengkap' }}
                                    </span>
                                @endif
                            </div>

                            <div class="detail-grid">
                                <div class="detail-item">
                                    <p class="detail-label">Telepon</p>
                                    <p class="detail-value">{{ $applicant->phone ?: '—' }}</p>
                                </div>
                                <div class="detail-item">
                                    <p class="detail-label">Nomor induk</p>
                                    <p class="detail-value">{{ $applicant->student_number ?: '—' }}</p>
                                </div>
                                <div class="detail-item">
                                    <p class="detail-label">Jurusan</p>
                                    <p class="detail-value">{{ $applicant->school_major ?: '—' }}</p>
                                </div>
                                @if ($applicant->nik)
                                    <div class="detail-item">
                                        <p class="detail-label">NIK</p>
                                        <p class="detail-value">{{ $applicant->nik }}</p>
                                    </div>
                                    <div class="detail-item">
                                        <p class="detail-label">Asal kampus/sekolah</p>
                                        <p class="detail-value">{{ $applicant->school_name ?: '—' }}</p>
                                    </div>
                                    <div class="detail-item">
                                        <p class="detail-label">Alamat</p>
                                        <p class="detail-value">{{ $applicant->address ?: '—' }}</p>
                                    </div>
                                    <div class="detail-item">
                                        <p class="detail-label">Tanggal lahir</p>
                                        <p class="detail-value">{{ $applicant->birth_date?->format('d/m/Y') ?? '—' }}</p>
                                    </div>
                                    <div class="detail-item">
                                        <p class="detail-label">Jenis kelamin</p>
                                        <p class="detail-value">{{ $applicant->gender ?: '—' }}</p>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if (in_array($internshipRequest->status, ['approved', 'rejected'], true) && $internshipRequest->reply_letter_path)
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Surat Balasan</h2>
                </div>
                <a class="btn btn-secondary" href="{{ route('operator.internship-requests.reply-letter', $internshipRequest) }}">
                    @include('layouts.partials.icon', ['name' => 'download'])
                    <span>Unduh surat balasan PDF</span>
                </a>
            </section>
        @endif
    </div>
@endsection
