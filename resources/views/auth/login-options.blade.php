@extends('layouts.guest')

@section('title', 'Pilih Jenis Akun')
@section('heading', 'Pilih Jenis Akun')
@section('subheading', 'Masuk ke kebutuhan yang sesuai dengan peran Anda.')

@section('content')
    <div class="account-list">
        <a class="account-option" href="{{ route('admin.login') }}">
            <span class="account-option-icon">
                @include('layouts.partials.icon', ['name' => 'shield-check'])
            </span>
            <span class="account-option-body">
                <span class="account-option-title">Admin</span>
                <span class="account-option-text">Verifikasi pengajuan dan kelola akun institusi.</span>
            </span>
            @include('layouts.partials.icon', ['name' => 'chevron-right', 'class' => 'icon account-chevron'])
        </a>

        <a class="account-option" href="{{ route('operator.login') }}">
            <span class="account-option-icon">
                @include('layouts.partials.icon', ['name' => 'building'])
            </span>
            <span class="account-option-body">
                <span class="account-option-title">Operator</span>
                <span class="account-option-text">Kelola pengajuan magang institusi Anda.</span>
            </span>
            @include('layouts.partials.icon', ['name' => 'chevron-right', 'class' => 'icon account-chevron'])
        </a>

        <a class="account-option" href="{{ route('intern.login') }}">
            <span class="account-option-icon">
                @include('layouts.partials.icon', ['name' => 'user'])
            </span>
            <span class="account-option-body">
                <span class="account-option-title">Peserta Magang</span>
                <span class="account-option-text">Lengkapi profil dan pantau status magang Anda.</span>
            </span>
            @include('layouts.partials.icon', ['name' => 'chevron-right', 'class' => 'icon account-chevron'])
        </a>
    </div>

    <p class="auth-links auth-links-start">
        Belum punya akun? <a href="{{ route('register') }}">Daftar sebagai operator</a>
    </p>
    <p class="auth-links">
        Punya token dari Admin? <a href="{{ route('intern.register') }}">Registrasi akun peserta</a>
    </p>
@endsection
