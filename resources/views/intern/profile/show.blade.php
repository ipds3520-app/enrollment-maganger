@extends('layouts.app')

@section('title', 'Data Pribadi Peserta')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Data Pribadi Peserta</h1>
            <p class="page-description">Data identitas yang tersimpan pada akun peserta magang.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary" href="{{ route('intern.profile.edit') }}">
                @include('layouts.partials.icon', ['name' => 'user'])
                <span>Edit Data Pribadi</span>
            </a>
            <a class="btn btn-secondary" href="{{ route('intern.dashboard') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">
            @include('layouts.partials.icon', ['name' => 'check-circle'])
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="page-stack">
        <section class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Identitas</h2>
                    <p class="card-description">Nama dan kontak akun peserta.</p>
                </div>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <p class="detail-label">Nama lengkap</p>
                    <p class="detail-value">{{ $intern->name }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Email</p>
                    <p class="detail-value">{{ $intern->email }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Nomor telepon</p>
                    <p class="detail-value">{{ $intern->applicant->phone ?: '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">NIK</p>
                    <p class="detail-value">{{ $intern->applicant->nik ?: '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Tanggal lahir</p>
                    <p class="detail-value">{{ $intern->applicant->birth_date?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Jenis kelamin</p>
                    <p class="detail-value">{{ $intern->applicant->gender ?: '—' }}</p>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Pendidikan</h2>
                    <p class="card-description">Data sekolah atau kampus asal.</p>
                </div>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <p class="detail-label">NIM/NIS</p>
                    <p class="detail-value">{{ $intern->applicant->student_number ?: '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Asal kampus/sekolah</p>
                    <p class="detail-value">{{ $intern->applicant->school_name ?: '—' }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Program studi/jurusan</p>
                    <p class="detail-value">{{ $intern->applicant->school_major ?: '—' }}</p>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Alamat Domisili</h2>
                    <p class="card-description">Alamat tempat tinggal peserta.</p>
                </div>
            </div>
            <p class="detail-value">{{ $intern->applicant->address ?: '—' }}</p>
        </section>
    </div>
@endsection
