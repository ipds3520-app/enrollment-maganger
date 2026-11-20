@extends('layouts.app')

@section('title', 'Data Pribadi Peserta')

@section('content')
    <h1>Data Pribadi Peserta</h1>
    @if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
    <section class="card">
        <dl class="detail">
            <dt>Nama lengkap</dt><dd>{{ $intern->name }}</dd>
            <dt>Email</dt><dd>{{ $intern->email }}</dd>
            <dt>NIK</dt><dd>{{ $intern->applicant->nik }}</dd>
            <dt>NIM/NIS</dt><dd>{{ $intern->applicant->student_number }}</dd>
            <dt>Asal kampus/sekolah</dt><dd>{{ $intern->applicant->school_name }}</dd>
            <dt>Program studi/jurusan</dt><dd>{{ $intern->applicant->school_major }}</dd>
            <dt>Nomor telepon</dt><dd>{{ $intern->applicant->phone }}</dd>
            <dt>Alamat</dt><dd>{{ $intern->applicant->address }}</dd>
            <dt>Tanggal lahir</dt><dd>{{ $intern->applicant->birth_date?->format('d/m/Y') }}</dd>
            <dt>Jenis kelamin</dt><dd>{{ $intern->applicant->gender }}</dd>
        </dl>
    </section>
    <p><a href="{{ route('intern.dashboard') }}">Kembali ke dashboard</a></p>
@endsection
