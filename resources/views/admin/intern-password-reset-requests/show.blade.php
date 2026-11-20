@extends('layouts.app')

@section('title', 'Detail Permintaan Reset Peserta')


@section('content')
    @php
        $intern = $resetRequest->intern;
        $applicant = $intern->applicant;
        $internshipRequest = $applicant?->internshipRequest;
        $statusLabel = match ($resetRequest->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu',
        };
        $statusClass = match ($resetRequest->status) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default => 'badge-warning',
        };
    @endphp

    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Detail Permintaan Reset</h1>
            <p class="page-description">Verifikasi identitas peserta sebelum mengambil keputusan.</p>
        </div>
        <div class="page-actions">
            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
            <a class="btn btn-secondary" href="{{ route('admin.intern-password-reset-requests.index') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Daftar permintaan</span>
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
                <h2 class="card-title">Identitas Intern</h2>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <p class="detail-label">Nama</p>
                    <p class="detail-value">{{ $intern->name }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Email akun</p>
                    <p class="detail-value">{{ $intern->email }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Nomor identitas</p>
                    <p class="detail-value">{{ $applicant?->student_number ?: '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Institusi asal</p>
                    <p class="detail-value">{{ $internshipRequest?->institution?->name ?? '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Pengajuan magang</p>
                    <p class="detail-value">{{ $internshipRequest?->title ?? '—' }}</p>
                </div>
                @if ($internshipRequest)
                    <div class="detail-item">
                        <p class="detail-label">Periode</p>
                        <p class="detail-value">
                            {{ $internshipRequest->start_date?->format('d/m/Y') ?? '—' }} &ndash; {{ $internshipRequest->end_date?->format('d/m/Y') ?? '—' }}
                        </p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Status pengajuan</p>
                        <p class="detail-value">
                            {{ match ($internshipRequest->status) {
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                                default => 'Menunggu diproses',
                            } }}
                        </p>
                    </div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Permintaan</h2>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <p class="detail-label">Status</p>
                    <p class="detail-value">{{ $statusLabel }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Diajukan</p>
                    <p class="detail-value">{{ $resetRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Diproses oleh</p>
                    <p class="detail-value">{{ $resetRequest->processor?->name ?? '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Waktu proses</p>
                    <p class="detail-value">{{ $resetRequest->processed_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
            </div>
        </section>

        @if ($resetRequest->status === 'pending')
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Keputusan</h2>
                        <p class="card-description">Setujui untuk membuat tautan reset sekali pakai, atau tolak permintaan ini.</p>
                    </div>
                </div>
                <div class="form-actions form-actions-flush">
                    <form method="POST" action="{{ route('admin.intern-password-reset-requests.approve', $resetRequest) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Setujui dan buat tautan</button>
                    </form>
                    <form method="POST" action="{{ route('admin.intern-password-reset-requests.reject', $resetRequest) }}">
                        @csrf
                        <button class="btn btn-secondary" type="submit">Tolak permintaan</button>
                    </form>
                </div>
            </section>
        @else
            <div class="notice">
                @include('layouts.partials.icon', ['name' => 'info'])
                <span>Permintaan telah diproses. Token reset tidak dapat ditampilkan kembali.</span>
            </div>
        @endif
    </div>
@endsection
