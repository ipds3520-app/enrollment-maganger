@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
    <h1>Dashboard Admin</h1>
    <p class="subtitle">Kelola akun admin dan tinjau permohonan magang.</p>
    <p style="margin:0 0 20px"><a href="{{ route('admin.internship-requests.index') }}">Tinjau pengajuan magang</a></p>

    <div class="grid">
        <section class="card">
            <h2>Informasi Akun</h2>
            <dl class="detail">
                <dt>Nama</dt>
                <dd>{{ $user->name }}</dd>

                <dt>Email</dt>
                <dd>{{ $user->email }}</dd>

                <dt>Role</dt>
                <dd>Admin</dd>

            </dl>
        </section>

        <section class="card">
            <h2>Institusi</h2>
            <p class="notice">Akun admin tidak terikat pada institusi mana pun.</p>
        </section>
    </div>
@endsection
