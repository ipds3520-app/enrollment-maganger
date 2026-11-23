@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Dashboard Admin</h1>
            <p class="page-description">Ringkasan pengajuan magang, akun operator, dan peserta dari seluruh institusi.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary" href="{{ route('admin.internship-requests.index') }}">
                @include('layouts.partials.icon', ['name' => 'file-text'])
                <span>Tinjau pengajuan</span>
            </a>
        </div>
    </div>

    <section class="dashboard-stat-section" aria-labelledby="request-stats-heading">
        <h2 class="dashboard-stat-heading" id="request-stats-heading">Ringkasan pengajuan</h2>
        <div class="stat-grid stat-grid--requests">
            <div class="stat-card">
                <span class="stat-label">Total pengajuan</span>
                <span class="stat-value">{{ number_format($stats['total']) }}</span>
                <span class="stat-hint">Seluruh institusi</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Menunggu</span>
                <span class="stat-value">{{ number_format($stats['pending']) }}</span>
                <span class="stat-hint">Perlu ditinjau</span>
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
    </section>

    <section class="dashboard-stat-section dashboard-stat-section--accounts" aria-labelledby="account-stats-heading">
        <h2 class="dashboard-stat-heading" id="account-stats-heading">Akun terdaftar</h2>
        <div class="stat-grid stat-grid--accounts">
            <div class="stat-card">
                <span class="stat-label">Total operator</span>
                <span class="stat-value">{{ number_format($stats['operators']) }}</span>
                <span class="stat-hint">Akun institusi</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Total peserta magang</span>
                <span class="stat-value">{{ number_format($stats['interns']) }}</span>
                <span class="stat-hint">Akun peserta terdaftar</span>
            </div>
        </div>
    </section>

    <div class="dashboard-grid dashboard-grid--full">
        <section class="card">
            <div class="section-header">
                <div>
                    <h2 class="section-heading">Pengajuan terbaru</h2>
                    <p class="section-subheading">Lima pengajuan terakhir yang masuk.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="{{ route('admin.internship-requests.index') }}">Lihat semua</a>
            </div>

            @if ($recentRequests->isEmpty())
                <div class="empty-state">
                    @include('layouts.partials.icon', ['name' => 'inbox'])
                    <h3 class="empty-state-title">Belum ada pengajuan</h3>
                    <p class="empty-state-text">Pengajuan dari operator institusi akan muncul di sini.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th scope="col">Pengajuan</th>
                                <th scope="col">Institusi</th>
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
                                        <a href="{{ route('admin.internship-requests.show', $recentRequest) }}">{{ $recentRequest->title }}</a>
                                    </td>
                                    <td class="cell-muted">{{ $recentRequest->institution?->name ?? '—' }}</td>
                                    <td class="cell-muted nowrap">{{ $recentRequest->created_at?->format('d/m/Y') ?? '—' }}</td>
                                    <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
