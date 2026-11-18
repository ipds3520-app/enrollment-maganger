@extends('layouts.app')

@section('title', 'Dashboard Peserta')

@section('content')
    <h1>Dashboard Peserta Magang</h1>
    <p class="subtitle">Informasi akun peserta magang.</p>

    <section class="card">
        <dl class="detail">
            <dt>Nama</dt>
            <dd>{{ $intern->name }}</dd>
            <dt>Email</dt>
            <dd>{{ $intern->email }}</dd>
        </dl>
    </section>
@endsection
