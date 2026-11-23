@extends('layouts.app')

@section('title', 'Dashboard Operator')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Dashboard Operator</h1>
            <p class="page-description">Ringkasan pengajuan magang untuk institusi Anda.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary" href="{{ route('operator.internship-requests.create') }}">
                @include('layouts.partials.icon', ['name' => 'plus'])
                <span>Buat pengajuan</span>
            </a>
        </div>
    </div>

    @if ($institution === null)
        <div class="alert alert-warning" role="alert">
            @include('layouts.partials.icon', ['name' => 'alert-circle'])
            <span>Akun operator ini belum terhubung ke institusi. Hubungi admin sebelum membuat pengajuan.</span>
        </div>
    @endif

    <section class="card card-tight operator-registration-guide" aria-labelledby="registration-guide-heading">
        <div class="card-header">
            <div>
                <h2 class="card-title" id="registration-guide-heading">Panduan pendaftaran peserta</h2>
                <p class="card-description">Token diterbitkan Admin setelah permohonan disetujui.</p>
            </div>
        </div>
        <ol class="operator-guide-list">
            <li>Ajukan permohonan dan tunggu statusnya menjadi disetujui.</li>
            <li>Buka detail permohonan untuk melihat dan menyalin token.</li>
            <li>Bagikan token kepada calon peserta dan arahkan mereka ke <a href="{{ route('intern.register') }}">halaman pendaftaran peserta</a>.</li>
            <li>Peserta memasukkan token, membuat akun, lalu melengkapi profilnya.</li>
            <li>Pantau jumlah profil selesai dan sisa kuota pada detail permohonan.</li>
        </ol>
        <p class="card-note">Kuota baru berkurang setelah peserta melengkapi dan menyimpan profil.</p>
    </section>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-label">Total pengajuan</span>
            <span class="stat-value">{{ number_format($stats['total']) }}</span>
            <span class="stat-hint">Seluruh pengajuan institusi</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Menunggu</span>
            <span class="stat-value">{{ number_format($stats['pending']) }}</span>
            <span class="stat-hint">Menunggu keputusan admin</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Disetujui</span>
            <span class="stat-value">{{ number_format($stats['approved']) }}</span>
            <span class="stat-hint">Pengajuan diterima</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Ditolak</span>
            <span class="stat-value">{{ number_format($stats['rejected']) }}</span>
            <span class="stat-hint">Pengajuan ditolak</span>
        </div>
    </div>

    <div class="dashboard-grid">
        <section class="card">
            <div class="section-header">
                <div>
                    <h2 class="section-heading">Pengajuan terbaru</h2>
                    <p class="section-subheading">Lima pengajuan terakhir dari institusi Anda.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="{{ route('operator.internship-requests.index') }}">Lihat semua</a>
            </div>

            @if ($recentRequests->isEmpty())
                <div class="empty-state">
                    @include('layouts.partials.icon', ['name' => 'inbox'])
                    <h3 class="empty-state-title">Belum ada pengajuan</h3>
                    <p class="empty-state-text">Mulai dengan membuat pengajuan permohonan magang untuk institusi Anda.</p>
                    <a class="btn btn-primary" href="{{ route('operator.internship-requests.create') }}">
                        @include('layouts.partials.icon', ['name' => 'plus'])
                        <span>Buat pengajuan</span>
                    </a>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th scope="col">Judul</th>
                                <th scope="col">Peserta</th>
                                <th scope="col">Diajukan</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentRequests as $recentRequest)
                                @php
                                    $statusLabel = match ($recentRequest->status) {
                                        'approved' => 'Disetujui',
                                        'rejected' => 'Ditolak',
                                        default => 'Menunggu diproses',
                                    };
                                    $statusClass = match ($recentRequest->status) {
                                        'approved' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        default => 'badge-warning',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('operator.internship-requests.show', $recentRequest) }}">{{ $recentRequest->title }}</a>
                                    </td>
                                    <td class="cell-muted">{{ $recentRequest->participant_count ?? $recentRequest->applicants_count }}</td>
                                    <td class="cell-muted nowrap">{{ $recentRequest->created_at?->format('d/m/Y') ?? '—' }}</td>
                                    <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="page-stack">
            <section class="card">
                <div class="section-header">
                    <div>
                        <h2 class="section-heading">Institusi</h2>
                        <p class="section-subheading">Data institusi akun Anda.</p>
                    </div>
                </div>

                @if ($institution)
                    @php
                        $typeLabel = match ($institution->type) {
                            'smk' => 'SMK',
                            'sma' => 'SMA',
                            'university' => 'Perguruan Tinggi',
                            default => 'Lainnya',
                        };
                    @endphp
                    <dl class="data-list">
                        <div class="data-row">
                            <dt>Nama</dt>
                            <dd>{{ $institution->name }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Jenis</dt>
                            <dd>{{ $typeLabel }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Telepon</dt>
                            <dd>{{ $institution->phone ?: 'Belum diisi' }}</dd>
                        </div>
                        <div class="data-row">
                            <dt>Email</dt>
                            <dd>{{ $institution->email ?: 'Belum diisi' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="muted">Belum ada institusi yang terhubung.</p>
                @endif
            </section>
        </div>
    </div>
@endsection
