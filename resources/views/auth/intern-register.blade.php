@extends('layouts.guest')

@section('title', 'Registrasi Peserta')
@section('heading', 'Registrasi Peserta Magang')
@section('subheading', 'Gunakan token yang diberikan oleh Admin. Token lama tetap dapat digunakan.')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
        <p class="auth-links"><a href="{{ route('intern.login') }}">Masuk sebagai peserta</a></p>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('intern.register.store') }}" autocomplete="off">
        @csrf

        <div class="form-group">
            <label for="token">Registration Token</label>
            <input id="token" name="token" type="text" value="" required maxlength="255" autocomplete="off" spellcheck="false" aria-invalid="{{ $errors->has('token') ? 'true' : 'false' }}">
        </div>

        <div class="form-group">
            <label class="label-required" for="name">Nama lengkap <span></span></label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="100" autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
        </div>

        <div class="form-group">
            <label class="label-required" for="email">Email <span></span></label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="150" autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
        </div>

        <div class="form-group">
            <label for="password_confirmation">Konfirmasi Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
        </div>

        <button class="btn-primary" type="submit">Daftar</button>
    </form>
@endsection
