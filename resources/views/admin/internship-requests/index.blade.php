@extends('layouts.app')

@section('title', 'Pengajuan Magang')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Pengajuan Magang</h1>
            <p class="page-description">Tinjau pengajuan dari seluruh institusi dan pantau statusnya.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.internship-requests.index') }}" class="filter-bar">
        <div class="field">
            <label for="q">Cari pengajuan</label>
            <input id="q" name="q" value="{{ $search }}" placeholder="Judul, institusi, atau operator">
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua status</option>
                <option value="pending" @selected($status === 'pending')>Menunggu diproses</option>
                <option value="approved" @selected($status === 'approved')>Disetujui</option>
                <option value="rejected" @selected($status === 'rejected')>Ditolak</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            @if ($search !== '' || $status)
                <a class="btn btn-secondary" href="{{ route('admin.internship-requests.index') }}">Reset</a>
            @endif
        </div>
    </form>

    @if ($requests->isEmpty())
        <div class="table-wrap">
            <div class="empty-state">
                @include('layouts.partials.icon', ['name' => 'inbox'])
                <h2 class="empty-state-title">Tidak ada pengajuan yang cocok</h2>
                <p class="empty-state-text">Ubah kata kunci atau filter status untuk melihat pengajuan lain.</p>
                <a class="btn btn-secondary" href="{{ route('admin.internship-requests.index') }}">Hapus filter</a>
            </div>
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Institusi</th>
                        <th scope="col">Operator</th>
                        <th scope="col">Judul</th>
                        <th scope="col">Periode</th>
                        <th scope="col">Peserta</th>
                        <th scope="col">Diajukan</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $internshipRequest)
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
                        <tr>
                            <td>{{ $internshipRequest->institution->name }}</td>
                            <td class="cell-muted">{{ $internshipRequest->creator->name }}</td>
                            <td>
                                <a href="{{ route('admin.internship-requests.show', $internshipRequest) }}">{{ $internshipRequest->title }}</a>
                            </td>
                            <td class="cell-muted nowrap">
                                {{ $internshipRequest->start_date->format('d/m/Y') }} &ndash; {{ $internshipRequest->end_date->format('d/m/Y') }}
                            </td>
                            <td class="cell-muted">{{ $internshipRequest->participant_count ?? $internshipRequest->applicants_count }}</td>
                            <td class="cell-muted nowrap">{{ $internshipRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $requests->links() }}
    @endif
@endsection
