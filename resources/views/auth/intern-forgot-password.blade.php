@extends('layouts.guest')

@section('title', 'Reset Password Peserta')
@section('heading', 'Lupa Password Peserta')
@section('subheading', 'Ajukan permintaan reset password. Admin akan memeriksa permintaan Anda.')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('intern.password.email') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
        </div>
        <button type="submit" class="btn-primary">Ajukan Permintaan Reset</button>
    </form>
    <p class="auth-links"><a href="{{ route('intern.login') }}">Kembali ke login peserta</a></p>
@endsection
