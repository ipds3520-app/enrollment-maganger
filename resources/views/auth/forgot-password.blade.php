@extends('layouts.guest')

@section('title', 'Lupa Password')
@section('heading', 'Lupa Password')
@section('subheading', 'Masukkan email akun Anda untuk menerima tautan reset password')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                @error('email') aria-invalid="true" @enderror
            >
        </div>

        <button type="submit" class="btn-primary">Kirim Tautan Reset</button>
    </form>

    <p class="auth-links">
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </p>
@endsection
