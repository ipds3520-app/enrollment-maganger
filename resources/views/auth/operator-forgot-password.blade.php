@extends('layouts.guest')

@section('title', 'Lupa Password Operator')
@section('heading', 'Lupa Password Operator')
@section('subheading', 'Masukkan email akun Operator untuk melanjutkan pemulihan password')

@section('content')
    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('operator.password.email') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email Operator</label>
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

        <button type="submit" class="btn-primary">Lanjutkan</button>
    </form>

    <p class="auth-links"><a href="{{ route('operator.login') }}">Kembali ke login Operator</a></p>
@endsection
