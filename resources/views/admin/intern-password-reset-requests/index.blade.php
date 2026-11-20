@extends('layouts.app')

@section('title', 'Permintaan Reset Password Peserta')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Permintaan Reset Password Peserta</h1>
            <p class="page-description">Periksa permintaan reset password peserta. Riwayat tetap tersimpan setelah diproses.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </div>

    @if ($requests->isEmpty())
        <div class="table-wrap">
            <div class="empty-state">
                @include('layouts.partials.icon', ['name' => 'key'])
                <h2 class="empty-state-title">Belum ada permintaan</h2>
                <p class="empty-state-text">Permintaan reset password dari peserta magang akan muncul di sini.</p>
            </div>
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Peserta</th>
                        <th scope="col">Email</th>
                        <th scope="col">Institusi</th>
                        <th scope="col">Diajukan</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="cell-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $resetRequest)
                        @php
                            $intern = $resetRequest->intern;
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
                        <tr>
                            <td>{{ $intern->name }}</td>
                            <td class="cell-muted">{{ $intern->email }}</td>
                            <td class="cell-muted">{{ $intern->applicant?->internshipRequest?->institution?->name ?? '—' }}</td>
                            <td class="cell-muted nowrap">{{ $resetRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="cell-actions">
                                <a class="btn btn-secondary btn-sm" href="{{ route('admin.intern-password-reset-requests.show', $resetRequest) }}">Periksa</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $requests->links() }}
    @endif
@endsection
