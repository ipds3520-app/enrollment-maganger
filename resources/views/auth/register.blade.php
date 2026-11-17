@extends('layouts.guest')

@section('title', 'Daftar Operator')
@section('heading', 'Daftar Akun Operator')
@section('subheading', 'Daftarkan institusi dan akun operator magang')
@section('wrapper_class', 'guest-wrapper--wide')

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

    <form method="POST" action="{{ route('register.store') }}">
        @csrf

        <fieldset>
            <legend>Data Institusi</legend>

            <div class="form-group">
                <label for="institution_name">Nama Institusi</label>
                <input
                    id="institution_name"
                    type="text"
                    name="institution_name"
                    value="{{ old('institution_name') }}"
                    required
                    autofocus
                    maxlength="150"
                    @error('institution_name') aria-invalid="true" @enderror
                >
            </div>

            <div class="form-group">
                <label for="institution_type">Jenis Institusi</label>
                <select
                    id="institution_type"
                    name="institution_type"
                    required
                    @error('institution_type') aria-invalid="true" @enderror
                >
                    <option value="">Pilih jenis institusi</option>
                    @foreach (['smk' => 'SMK', 'sma' => 'SMA', 'university' => 'Perguruan Tinggi', 'other' => 'Lainnya'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('institution_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="institution_address">Alamat Institusi (opsional)</label>
                <textarea
                    id="institution_address"
                    name="institution_address"
                    rows="2"
                    maxlength="1000"
                    @error('institution_address') aria-invalid="true" @enderror
                >{{ old('institution_address') }}</textarea>
            </div>

            <div class="form-group">
                <label for="institution_phone">Telepon Institusi (opsional)</label>
                <input
                    id="institution_phone"
                    type="text"
                    name="institution_phone"
                    value="{{ old('institution_phone') }}"
                    maxlength="20"
                    @error('institution_phone') aria-invalid="true" @enderror
                >
            </div>

            <div class="form-group">
                <label for="institution_email">Email Institusi (opsional)</label>
                <input
                    id="institution_email"
                    type="email"
                    name="institution_email"
                    value="{{ old('institution_email') }}"
                    maxlength="150"
                    @error('institution_email') aria-invalid="true" @enderror
                >
            </div>
        </fieldset>

        <fieldset>
            <legend>Data Akun Operator</legend>

            <div class="form-group">
                <label for="name">Nama Operator</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    maxlength="255"
                    @error('name') aria-invalid="true" @enderror
                >
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    maxlength="255"
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
                    minlength="8"
                    autocomplete="new-password"
                    @error('password') aria-invalid="true" @enderror
                >
            </div>

            <div class="form-group">
                <label for="password_confirmation">Konfirmasi Password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>
        </fieldset>

        <p class="notice">
            Setiap pendaftaran membuat data institusi baru. Jika institusi Anda sudah
            terdaftar, pendaftaran ini tetap membuat data institusi terpisah.
            Pendaftaran hanya untuk operator; akun admin dibuat oleh administrator.
        </p>

        <button type="submit" class="btn-primary">Daftar</button>
    </form>

    <p class="auth-links">
        Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
    </p>
@endsection
