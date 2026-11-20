@extends('layouts.app')

@section('title', 'Lengkapi Data Pribadi')

@section('content')
    <h1>Lengkapi Data Pribadi</h1>
    <p class="subtitle">Profil wajib disimpan lengkap sebelum kuota peserta pada pengajuan ini digunakan.</p>

    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <section class="card" role="alert" style="margin-bottom:18px;border-color:#f3c5c5">
            <ul style="margin:0;padding-left:20px">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </section>
    @endif

    <form method="POST" action="{{ route('intern.profile.update') }}" class="card" style="display:grid;gap:14px">
        @csrf
        @method('PUT')
        <dl class="detail">
            <dt>Nama lengkap</dt><dd>{{ $intern->name }}</dd>
            <dt>Email</dt><dd>{{ $intern->email }}</dd>
        </dl>
        <label>NIK
            <input name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" required>
            @error('nik')<span>{{ $message }}</span>@enderror
        </label>
        <label>NIM/NIS
            <input name="student_number" value="{{ old('student_number') }}" maxlength="30" required>
            @error('student_number')<span>{{ $message }}</span>@enderror
        </label>
        <label>Asal kampus/sekolah
            <input name="school_name" value="{{ old('school_name') }}" maxlength="150" required>
            @error('school_name')<span>{{ $message }}</span>@enderror
        </label>
        <label>Program studi/jurusan
            <input name="school_major" value="{{ old('school_major') }}" maxlength="100" required>
            @error('school_major')<span>{{ $message }}</span>@enderror
        </label>
        <label>Nomor telepon
            <input name="phone" value="{{ old('phone') }}" maxlength="20" required>
            @error('phone')<span>{{ $message }}</span>@enderror
        </label>
        <label>Alamat
            <textarea name="address" rows="3" maxlength="2000" required>{{ old('address') }}</textarea>
            @error('address')<span>{{ $message }}</span>@enderror
        </label>
        <label>Tanggal lahir
            <input type="date" name="birth_date" value="{{ old('birth_date') }}" required>
            @error('birth_date')<span>{{ $message }}</span>@enderror
        </label>
        <label>Jenis kelamin
            <select name="gender" required>
                <option value="">Pilih jenis kelamin</option>
                <option value="Laki-laki" @selected(old('gender') === 'Laki-laki')>Laki-laki</option>
                <option value="Perempuan" @selected(old('gender') === 'Perempuan')>Perempuan</option>
            </select>
            @error('gender')<span>{{ $message }}</span>@enderror
        </label>
        <button class="btn-primary" type="submit">Simpan Data Pribadi</button>
    </form>
@endsection
