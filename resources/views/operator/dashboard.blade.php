@extends('layouts.app')

@section('title', 'Dashboard Operator')

@section('content')
    <h1>Dashboard Operator</h1>
    <p class="subtitle">Halaman sementara. Fitur pengelolaan akan ditambahkan pada tahap berikutnya.</p>
    <p><a href="{{ route('operator.internship-requests.index') }}">Lihat pengajuan permohonan magang</a></p>

    <div class="grid">
        <section class="card">
            <h2>Informasi Akun</h2>
            <dl class="detail">
                <dt>Nama</dt>
                <dd>{{ $user->name }}</dd>

                <dt>Email</dt>
                <dd>{{ $user->email }}</dd>

                <dt>Role</dt>
                <dd>Operator</dd>

            </dl>
        </section>

        <section class="card">
            <h2>Informasi Institusi</h2>

            @if ($institution)
                @php
                    $typeLabel = match ($institution->type) {
                        'smk' => 'SMK',
                        'sma' => 'SMA',
                        'university' => 'Perguruan Tinggi',
                        default => 'Lainnya',
                    };
                @endphp

                <dl class="detail">
                    <dt>Nama</dt>
                    <dd>{{ $institution->name }}</dd>

                    <dt>Jenis</dt>
                    <dd>{{ $typeLabel }}</dd>

                    <dt>Alamat</dt>
                    <dd>{{ $institution->address ?: 'Belum diisi' }}</dd>

                    <dt>Telepon</dt>
                    <dd>{{ $institution->phone ?: 'Belum diisi' }}</dd>

                    <dt>Email</dt>
                    <dd>{{ $institution->email ?: 'Belum diisi' }}</dd>
                </dl>
            @else
                <p class="notice">Akun operator ini belum terhubung ke institusi mana pun.</p>
            @endif
        </section>
    </div>
@endsection
