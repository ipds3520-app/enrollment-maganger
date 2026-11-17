@extends('layouts.app')

@section('title', 'Dashboard Admin')

@section('content')
    <h1>Dashboard Admin</h1>
    <p class="subtitle">Halaman sementara. Fitur pengelolaan akan ditambahkan pada tahap berikutnya.</p>

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
