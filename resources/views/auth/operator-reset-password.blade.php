@extends('layouts.guest')

@section('title', 'Buat Password Baru')
@section('heading', 'Buat Password Baru')
@section('subheading', 'Buat password baru untuk akun Operator Anda')

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

    <form method="POST" action="{{ route('operator.password.update') }}">
        @csrf

        <div class="form-group">
            <label for="password">Password Baru</label>
            <input
                id="password"
                type="password"
                name="password"
                required
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
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn-primary">Simpan Password Baru</button>
    </form>

    <p class="auth-links"><a href="{{ route('operator.login') }}">Kembali ke login Operator</a></p>
@endsection
