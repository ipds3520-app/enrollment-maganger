@extends('layouts.app')

@section('title', 'Review Pengajuan Magang')

@section('content')
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
        <div>
            <h1>Pengajuan Magang</h1>
            <p class="subtitle" style="margin-bottom:0">Pengajuan dari seluruh institusi.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}">Kembali ke dashboard</a>
    </div>

    <form method="GET" action="{{ route('admin.internship-requests.index') }}" class="card" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:18px">
        <label for="q">Cari pengajuan, institusi, atau operator
            <input id="q" name="q" value="{{ $search }}" style="display:block;width:100%;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
        </label>
        <label for="status">Status
            <select id="status" name="status" style="display:block;width:100%;padding:10px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
                <option value="">Semua status</option>
                <option value="pending" @selected($status === 'pending')>Menunggu diproses</option>
                <option value="approved" @selected($status === 'approved')>Disetujui</option>
                <option value="rejected" @selected($status === 'rejected')>Ditolak</option>
            </select>
        </label>
        <div style="align-self:end;display:flex;gap:10px">
            <button type="submit" style="padding:10px 15px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer">Terapkan</button>
            <a href="{{ route('admin.internship-requests.index') }}" style="padding:10px">Reset</a>
        </div>
    </form>

    <section class="card" style="overflow-x:auto">
        @if ($requests->isEmpty())
            <p class="muted" style="margin:0">Tidak ada pengajuan yang cocok.</p>
        @else
            <table style="width:100%;border-collapse:collapse;min-width:900px">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #dde3ee">
                        <th style="padding:10px 8px">Institusi</th>
                        <th style="padding:10px 8px">Operator</th>
                        <th style="padding:10px 8px">Judul</th>
                        <th style="padding:10px 8px">Periode</th>
                        <th style="padding:10px 8px">Peserta</th>
                        <th style="padding:10px 8px">Diajukan</th>
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
                            <td style="padding:12px 8px">{{ $internshipRequest->institution->name }}</td>
                            <td style="padding:12px 8px">{{ $internshipRequest->creator->name }}</td>
                            <td style="padding:12px 8px"><a href="{{ route('admin.internship-requests.show', $internshipRequest) }}">{{ $internshipRequest->title }}</a></td>
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
@endsection
