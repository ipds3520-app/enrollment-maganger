@extends('layouts.app')

@section('title', 'Permintaan Reset Password Intern')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
        <div>
            <h1>Permintaan Reset Password Intern</h1>
            <p class="subtitle" style="margin-bottom:0">Riwayat permintaan tetap tersimpan setelah diproses.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}">Kembali ke dashboard</a>
    </div>

    <section class="card" style="overflow-x:auto">
        @if ($requests->isEmpty())
            <p class="muted" style="margin:0">Belum ada permintaan reset password.</p>
        @else
            <table style="width:100%;border-collapse:collapse;min-width:760px">
                <thead><tr style="text-align:left;border-bottom:1px solid #dde3ee">
                    <th style="padding:10px 8px">Intern</th><th style="padding:10px 8px">Email</th>
                    <th style="padding:10px 8px">Institusi</th><th style="padding:10px 8px">Diajukan</th>
                    <th style="padding:10px 8px">Status</th><th style="padding:10px 8px">Detail</th>
                </tr></thead>
                <tbody>
                    @foreach ($requests as $resetRequest)
                        @php($intern = $resetRequest->intern)
                        <tr style="border-bottom:1px solid #eef1f6">
                            <td style="padding:12px 8px">{{ $intern->name }}</td>
                            <td style="padding:12px 8px">{{ $intern->email }}</td>
                            <td style="padding:12px 8px">{{ $intern->applicant?->internshipRequest?->institution?->name ?? '—' }}</td>
                            <td style="padding:12px 8px">{{ $resetRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td style="padding:12px 8px">{{ ucfirst($resetRequest->status) }}</td>
                            <td style="padding:12px 8px"><a href="{{ route('admin.intern-password-reset-requests.show', $resetRequest) }}">Periksa</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:16px">{{ $requests->links() }}</div>
        @endif
    </section>
@endsection
