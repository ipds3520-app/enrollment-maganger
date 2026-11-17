@extends('layouts.guest')

@section('title', 'Masuk')
@section('heading', 'Masuk')

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

    <form method="POST" action="{{ route('login.store') }}">
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

        <div class="form-group">
            <label for="password">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                @error('password') aria-invalid="true" @enderror
            >
        </div>

        <label class="checkbox">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>Ingat saya</span>
        </label>

        <button type="submit" class="btn-primary">Masuk</button>
    </form>

    <p class="auth-links">
        <a href="{{ route('password.request') }}">Lupa password?</a>
    </p>

    <p class="auth-links">
        Belum punya akun? <a href="{{ route('register') }}">Daftar sebagai operator</a>
    </p>
@endsection
