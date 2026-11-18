@extends('layouts.guest')

@section('title', 'Reset Password Peserta')
@section('heading', 'Reset Password Peserta')
@section('subheading', 'Buat password baru untuk akun peserta.')

@section('content')
    @if ($errors->any())
        <div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if ($errors->has('token'))
        <p class="auth-links"><a href="{{ route('intern.password.request') }}">Ajukan permintaan reset baru</a></p>
    @endif
    <noscript><div class="alert alert-error">Aktifkan JavaScript untuk memproses tautan reset dengan aman.</div></noscript>
    <form method="POST" action="{{ route('intern.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="form-group">
            <label for="password">Password Baru</label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Konfirmasi Password Baru</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn-primary">Simpan Password Baru</button>
    </form>
    <p class="auth-links"><a href="{{ route('intern.login') }}">Kembali ke login peserta</a></p>
    <script>
        (() => {
            const token = window.location.hash.slice(1);
            const tokenInput = document.querySelector('input[name="token"]');
            if (tokenInput && /^[a-f0-9]{64}$/i.test(token)) {
                tokenInput.value = token;
                window.history.replaceState(null, document.title, window.location.pathname + window.location.search);
            }
        })();
    </script>
@endsection
