@extends('layouts.app')

@section('title', 'Edit Peserta Magang')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Edit Data Peserta</h1>
            <p class="page-description">Perbarui data profil peserta. Email, institusi, dan status lifecycle hanya dapat dilihat.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('admin.intern-participants.show', $intern->getKey()) }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Batal</span>
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="list-plain">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.intern-participants.update', $intern->getKey()) }}" class="card form-grid">
        @csrf
        @method('PUT')

        <div class="field">
            <label for="name">Nama lengkap <span class="required-mark" aria-hidden="true">*</span></label>
            <input id="name" name="name" value="{{ old('name', $intern->name) }}" maxlength="100" required>
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="student_number">NIS/NIM</label>
            <input id="student_number" name="student_number" value="{{ old('student_number', $applicant->student_number) }}" maxlength="30">
            @error('student_number')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="school_major">Program studi / jurusan</label>
            <input id="school_major" name="school_major" value="{{ old('school_major', $applicant->school_major) }}" maxlength="100">
            @error('school_major')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="phone">Nomor HP</label>
            <input id="phone" name="phone" value="{{ old('phone', $intern->phone) }}" maxlength="20">
            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="address">Alamat</label>
            <textarea id="address" name="address" rows="3" maxlength="2000">{{ old('address', $applicant->address) }}</textarea>
            @error('address')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="birth_date">Tanggal lahir</label>
            <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date', $applicant->birth_date?->format('Y-m-d')) }}">
            @error('birth_date')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="gender">Jenis kelamin</label>
            <select id="gender" name="gender">
                <option value="">Pilih jenis kelamin</option>
                <option value="Laki-laki" @selected(old('gender', $applicant->gender) === 'Laki-laki')>Laki-laki</option>
                <option value="Perempuan" @selected(old('gender', $applicant->gender) === 'Perempuan')>Perempuan</option>
            </select>
            @error('gender')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="email">Email (tidak dapat diubah)</label>
            <input id="email" value="{{ $intern->email }}" readonly>
        </div>
        <div class="field">
            <label for="institution">Asal institusi (tidak dapat diubah)</label>
            <input id="institution" value="{{ $applicant->internshipRequest->institution->name }}" readonly>
        </div>
        <div class="field">
            <label for="participant_status">Status lifecycle (tidak dapat diubah pada tahap ini)</label>
            <input id="participant_status" value="{{ match ($applicant->participant_status) { 'active' => 'Aktif', 'completed' => 'Selesai', default => 'Belum Ditetapkan' } }}" readonly>
        </div>

        <div class="form-actions field-full">
            <a class="btn btn-secondary" href="{{ route('admin.intern-participants.show', $intern->getKey()) }}">Batal</a>
            <button class="btn btn-primary" type="submit">Simpan perubahan</button>
        </div>
    </form>
@endsection
