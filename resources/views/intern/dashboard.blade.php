@extends('layouts.app')

@section('title', 'Dashboard Peserta Magang')

@section('content')
    @php
        $claim = $intern->sharedRegistrationClaim;
        $applicant = $intern->applicant;
        $placement = $applicant?->internshipRequest;
        $institution = $placement?->institution;

        $registrationLabel = match ($claim?->status) {
            'completed' => 'Aktif',
            'pending_profile' => 'Melengkapi profil',
            default => 'Terdaftar',
        };
        $registrationClass = match ($claim?->status) {
            'completed' => 'badge-success',
            'pending_profile' => 'badge-warning',
            default => 'badge-neutral',
        };
        $placementStatusLabel = match ($placement?->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'pending' => 'Menunggu diproses',
            default => 'Belum tersedia',
        };
        $placementStatusClass = match ($placement?->status) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'pending' => 'badge-warning',
            default => 'badge-neutral',
        };
    @endphp

    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Dashboard Peserta Magang</h1>
            <p class="page-description">Informasi profil dan status pendaftaran magang Anda.</p>
        </div>
        @if ($claim?->status === 'completed')
            <div class="page-actions">
                <a class="btn btn-secondary" href="{{ route('intern.profile.show') }}">
                    @include('layouts.partials.icon', ['name' => 'user'])
                    <span>Lihat data pribadi</span>
                </a>
            </div>
        @endif
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Status pendaftaran</span>
            <span class="stat-value stat-value-sm">
                <span class="badge {{ $registrationClass }}">{{ $registrationLabel }}</span>
            </span>
            <span class="stat-hint">Status akun peserta</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Institusi</span>
            <span class="stat-value stat-value-sm">{{ $institution?->name ?? 'Belum tersedia' }}</span>
            <span class="stat-hint">Institusi pengaju</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Periode magang</span>
            <span class="stat-value stat-value-sm">
                @if ($placement)
                    {{ $placement->start_date?->format('d/m/Y') ?? '—' }} &ndash; {{ $placement->end_date?->format('d/m/Y') ?? '—' }}
                @else
                    Belum tersedia
                @endif
            </span>
            <span class="stat-hint">Sesuai pengajuan</span>
        </div>
    </div>

    <div class="dashboard-grid">
        <section class="card">
            <div class="section-header">
                <div>
                    <h2 class="section-heading">Informasi magang</h2>
                    <p class="section-subheading">Data pengajuan yang terkait dengan akun Anda.</p>
                </div>
                @if ($placement)
                    <span class="badge {{ $placementStatusClass }}">{{ $placementStatusLabel }}</span>
                @endif
            </div>

            @if ($placement)
                <dl class="data-list">
                    <div class="data-row">
                        <dt>Judul pengajuan</dt>
                        <dd>{{ $placement->title }}</dd>
                    </div>
                    <div class="data-row">
                        <dt>Institusi</dt>
                        <dd>{{ $institution?->name ?? '—' }}</dd>
                    </div>
                    <div class="data-row">
                        <dt>Tanggal mulai</dt>
                        <dd>{{ $placement->start_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="data-row">
                        <dt>Tanggal selesai</dt>
                        <dd>{{ $placement->end_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="data-row">
                        <dt>Status pengajuan</dt>
                        <dd>{{ $placementStatusLabel }}</dd>
                    </div>
                </dl>
            @else
                <div class="empty-state">
                    @include('layouts.partials.icon', ['name' => 'calendar'])
                    <h3 class="empty-state-title">Informasi magang belum tersedia</h3>
                    <p class="empty-state-text">Data penempatan akan tampil setelah pengajuan institusi diproses.</p>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="section-header">
                <div>
                    <h2 class="section-heading">Profil peserta</h2>
                    <p class="section-subheading">Data akun dan identitas Anda.</p>
                </div>
            </div>

            <dl class="data-list">
                <div class="data-row">
                    <dt>Nama</dt>
                    <dd>{{ $intern->name }}</dd>
                </div>
                <div class="data-row">
                    <dt>Email</dt>
                    <dd>{{ $intern->email }}</dd>
                </div>
                <div class="data-row">
                    <dt>Telepon</dt>
                    <dd>{{ $intern->phone ?: ($applicant?->phone ?: 'Belum diisi') }}</dd>
                </div>
                <div class="data-row">
                    <dt>NIM/NIS</dt>
                    <dd>{{ $applicant?->student_number ?: 'Belum diisi' }}</dd>
                </div>
                <div class="data-row">
                    <dt>Asal kampus/sekolah</dt>
                    <dd>{{ $applicant?->school_name ?: 'Belum diisi' }}</dd>
                </div>
                <div class="data-row">
                    <dt>Jurusan</dt>
                    <dd>{{ $applicant?->school_major ?: 'Belum diisi' }}</dd>
                </div>
            </dl>
        </section>
    </div>
@endsection
