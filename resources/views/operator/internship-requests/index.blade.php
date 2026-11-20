@extends('layouts.app')

@section('title', 'Pengajuan Magang')

@section('content')
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
        <div>
            <h1>Pengajuan Permohonan Magang</h1>
            <p class="subtitle" style="margin-bottom:0">Daftar pengajuan dari institusi Anda.</p>
        </div>
        <a href="{{ route('operator.internship-requests.create') }}" style="display:inline-block;padding:10px 16px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600">Buat Pengajuan</a>
    </div>

    @if (session('status'))
        <p class="notice" role="status" style="margin-bottom:16px">{{ session('status') }}</p>
    @endif

    <section class="card" style="overflow-x:auto">
        @if ($requests->isEmpty())
            <p class="muted" style="margin:0">Belum ada pengajuan. Pilih “Buat Pengajuan” untuk memulai.</p>
        @else
            <table style="width:100%;border-collapse:collapse;min-width:680px">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #dde3ee">
                        <th style="padding:10px 8px">Judul</th>
                        <th style="padding:10px 8px">Periode Magang</th>
                        <th style="padding:10px 8px">Calon Peserta</th>
                        <th style="padding:10px 8px">Tanggal Pengajuan</th>
                        <th style="padding:10px 8px">Status</th>
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
                        @endphp
                        <tr style="border-bottom:1px solid #eef1f6">
                            <td style="padding:12px 8px"><a href="{{ route('operator.internship-requests.show', $internshipRequest) }}">{{ $internshipRequest->title }}</a></td>
                            <td style="padding:12px 8px">{{ $internshipRequest->start_date->format('d/m/Y') }} – {{ $internshipRequest->end_date->format('d/m/Y') }}</td>
                            <td style="padding:12px 8px">{{ $internshipRequest->participant_count ?? $internshipRequest->applicants_count }}</td>
                            <td style="padding:12px 8px">{{ $internshipRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td style="padding:12px 8px"><span class="badge" style="text-transform:none;letter-spacing:0">{{ $statusLabel }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:16px">{{ $requests->links() }}</div>
        @endif
    </section>

    <p style="margin-top:18px"><a href="{{ route('operator.dashboard') }}">Kembali ke dashboard</a></p>
@endsection
