@extends('layouts.guest')

@section('title', 'Masuk '.$accountLabel)
@section('heading', 'Masuk sebagai '.$accountLabel)

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

    @php
        $loginRoute = match ($accountType) {
            'admin' => 'admin.login.store',
            'operator' => 'operator.login.store',
            default => 'intern.login.store',
        };
        $forgotRoute = $accountType === 'intern' ? 'intern.password.request' : 'password.request';
    @endphp

    <form method="POST" action="{{ route($loginRoute) }}">
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
        <a href="{{ route($forgotRoute) }}">Lupa password?</a>
    </p>

    <p class="auth-links">
        <a href="{{ route('login') }}">Pilih jenis akun lain</a>
        @if ($accountType === 'operator')
            <br>Belum punya akun? <a href="{{ route('register') }}">Daftar sebagai operator</a>
        @elseif ($accountType === 'intern')
            <br>Sudah mendapat token? <a href="{{ route('intern.register') }}">Daftar akun peserta</a>
        @endif
    </p>
@endsection
