@extends('layouts.app')

@section('title', 'Pengajuan Permohonan Magang')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Pengajuan Permohonan Magang</h1>
            <p class="page-description">Daftar pengajuan dari institusi Anda beserta statusnya.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary" href="{{ route('operator.internship-requests.create') }}">
                @include('layouts.partials.icon', ['name' => 'plus'])
                <span>Buat pengajuan</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">
            @include('layouts.partials.icon', ['name' => 'check-circle'])
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($requests->isEmpty())
        <div class="table-wrap">
            <div class="empty-state">
                @include('layouts.partials.icon', ['name' => 'inbox'])
                <h2 class="empty-state-title">Belum ada pengajuan</h2>
                <p class="empty-state-text">Buat pengajuan pertama untuk mengajukan peserta magang dari institusi Anda.</p>
                <a class="btn btn-primary" href="{{ route('operator.internship-requests.create') }}">
                    @include('layouts.partials.icon', ['name' => 'plus'])
                    <span>Buat pengajuan</span>
                </a>
            </div>
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Judul</th>
                        <th scope="col">Periode magang</th>
                        <th scope="col">Calon peserta</th>
                        <th scope="col">Tanggal pengajuan</th>
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
                            <td>
                                <a href="{{ route('operator.internship-requests.show', $internshipRequest) }}">{{ $internshipRequest->title }}</a>
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
