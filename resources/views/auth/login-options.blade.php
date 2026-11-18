@extends('layouts.guest')

@section('title', 'Pilih Login')
@section('heading', 'Pilih Jenis Akun')
@section('subheading', 'Masuk ke halaman akun yang sesuai.')

@section('content')
    <p class="auth-links"><a href="{{ route('admin.login') }}">Login Admin</a></p>
    <p class="auth-links"><a href="{{ route('operator.login') }}">Login Operator</a></p>
    <p class="auth-links"><a href="{{ route('intern.login') }}">Login Peserta Magang</a></p>
    <p class="auth-links"><a href="{{ route('register') }}">Daftar sebagai operator</a></p>
    <p class="auth-links"><a href="{{ route('intern.register') }}">Daftar akun peserta dengan token</a></p>
@endsection
