@extends('layouts.app')

@section('title', 'Detail Pengajuan Magang')

@section('content')
    @php
        $statusLabel = match ($internshipRequest->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu diproses',
        };
    @endphp

    @if (session('status'))
        <p class="notice" role="status" style="margin-bottom:16px">{{ session('status') }}</p>
    @endif

    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
        <div>
            <h1>Detail Pengajuan</h1>
            <p class="subtitle" style="margin-bottom:0">Informasi permohonan dan calon peserta.</p>
        </div>
        <span class="badge" style="text-transform:none;letter-spacing:0">{{ $statusLabel }}</span>
    </div>

    <section class="card" style="margin-bottom:18px">
        <h2>Informasi Permohonan</h2>
        <dl class="detail">
            <dt>Judul</dt><dd>{{ $internshipRequest->title }}</dd>
            <dt>Deskripsi</dt><dd>{{ $internshipRequest->description ?: '—' }}</dd>
            <dt>Tanggal Mulai</dt><dd>{{ $internshipRequest->start_date->format('d/m/Y') }}</dd>
            <dt>Tanggal Selesai</dt><dd>{{ $internshipRequest->end_date->format('d/m/Y') }}</dd>
            <dt>Diajukan</dt><dd>{{ $internshipRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            <dt>Status</dt><dd>{{ $statusLabel }}</dd>
        </dl>
    </section>

    <section class="card">
        <h2>Calon Peserta ({{ $internshipRequest->applicants->count() }})</h2>
        <div style="display:grid;gap:12px">
            @foreach ($internshipRequest->applicants as $applicant)
                <article style="padding:14px;border:1px solid #dde3ee;border-radius:10px">
                    <h3 style="font-size:1rem;margin:0 0 10px">{{ $applicant->name }}</h3>
                    <dl class="detail">
                        <dt>Email</dt><dd>{{ $applicant->email }}</dd>
                        <dt>Telepon</dt><dd>{{ $applicant->phone ?: '—' }}</dd>
                        <dt>Nomor Induk</dt><dd>{{ $applicant->student_number ?: '—' }}</dd>
                        <dt>Jurusan</dt><dd>{{ $applicant->school_major ?: '—' }}</dd>
                    </dl>
                </article>
            @endforeach
        </div>
    </section>

    <p style="margin-top:18px"><a href="{{ route('operator.internship-requests.index') }}">Kembali ke daftar pengajuan</a></p>
@endsection
