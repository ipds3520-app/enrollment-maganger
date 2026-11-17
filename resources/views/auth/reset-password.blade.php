@extends('layouts.guest')

@section('title', 'Reset Password')
@section('heading', 'Reset Password')
@section('subheading', 'Buat password baru untuk akun Anda')

@section('content')
    @if ($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label for="email">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                required
                autocomplete="username"
                @error('email') aria-invalid="true" @enderror
            >
        </div>

        <div class="form-group">
            <label for="password">Password Baru</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                minlength="8"
                autocomplete="new-password"
                @error('password') aria-invalid="true" @enderror
            >
        </div>

        <div class="form-group">
            <label for="password_confirmation">Konfirmasi Password Baru</label>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                minlength="8"
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn-primary">Simpan Password Baru</button>
    </form>

    <p class="auth-links">
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </p>
@endsection
