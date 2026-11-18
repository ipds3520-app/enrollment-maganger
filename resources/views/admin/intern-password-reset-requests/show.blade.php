@extends('layouts.app')

@section('title', 'Detail Permintaan Reset Intern')

@section('content')
    @php
        $intern = $resetRequest->intern;
        $applicant = $intern->applicant;
        $internshipRequest = $applicant?->internshipRequest;
    @endphp

    @if (session('status'))<p class="notice" role="status" style="margin-bottom:16px">{{ session('status') }}</p>@endif
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
        <div><h1>Detail Permintaan Reset</h1><p class="subtitle" style="margin-bottom:0">Verifikasi identitas Intern sebelum mengambil keputusan.</p></div>
        <a href="{{ route('admin.intern-password-reset-requests.index') }}">Kembali ke daftar</a>
    </div>

    <section class="card" style="margin-bottom:18px">
        <h2>Identitas Intern</h2>
        <dl class="detail">
            <dt>Nama</dt><dd>{{ $intern->name }}</dd>
            <dt>Email akun</dt><dd>{{ $intern->email }}</dd>
            <dt>Nomor identitas</dt><dd>{{ $applicant?->student_number ?: '—' }}</dd>
            <dt>Institusi asal</dt><dd>{{ $internshipRequest?->institution?->name ?? '—' }}</dd>
            <dt>Pengajuan magang</dt><dd>{{ $internshipRequest?->title ?? '—' }}</dd>
            @if ($internshipRequest)
                <dt>Periode</dt><dd>{{ $internshipRequest->start_date?->format('d/m/Y') ?? '—' }} – {{ $internshipRequest->end_date?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Status pengajuan</dt><dd>{{ ucfirst($internshipRequest->status) }}</dd>
            @endif
        </dl>
    </section>

    <section class="card" style="margin-bottom:18px">
        <h2>Permintaan</h2>
        <dl class="detail">
            <dt>Status</dt><dd>{{ ucfirst($resetRequest->status) }}</dd>
            <dt>Diajukan</dt><dd>{{ $resetRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            <dt>Diproses oleh</dt><dd>{{ $resetRequest->processor?->name ?? '—' }}</dd>
            <dt>Waktu proses</dt><dd>{{ $resetRequest->processed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        </dl>
    </section>

    @if ($resetRequest->status === 'pending')
        <div style="display:flex;gap:12px">
            <form method="POST" action="{{ route('admin.intern-password-reset-requests.approve', $resetRequest) }}">
                @csrf<button class="btn-primary" type="submit" style="width:auto">Setujui dan buat tautan sekali pakai</button>
            </form>
            <form method="POST" action="{{ route('admin.intern-password-reset-requests.reject', $resetRequest) }}">
                @csrf<button class="btn-secondary" type="submit" style="width:auto">Tolak permintaan</button>
            </form>
        </div>
    @else
        <p class="notice">Permintaan telah diproses. Token reset tidak dapat ditampilkan kembali.</p>
    @endif
@endsection
