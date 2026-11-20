@extends('layouts.app')

@section('title', 'Lengkapi Data Pribadi')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Lengkapi Data Pribadi</h1>
            <p class="page-description">Profil wajib disimpan lengkap sebelum kuota peserta pada pengajuan ini digunakan.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">
            @include('layouts.partials.icon', ['name' => 'check-circle'])
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            @include('layouts.partials.icon', ['name' => 'alert-circle'])
            <div>
                <strong>Periksa kembali data berikut:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('intern.profile.update') }}">
        @csrf
        @method('PUT')

        <div class="page-stack">
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Identitas Akun</h2>
                        <p class="card-description">Nama dan email mengikuti akun yang terdaftar.</p>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <p class="detail-label">Nama lengkap</p>
                        <p class="detail-value">{{ $intern->name }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Email</p>
                        <p class="detail-value">{{ $intern->email }}</p>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Data Pribadi</h2>
                        <p class="card-description">Isi seluruh data dengan benar sesuai dokumen identitas.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="nik">NIK <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="nik"
                            name="nik"
                            value="{{ old('nik') }}"
                            inputmode="numeric"
                            maxlength="16"
                            required
                            @error('nik') aria-invalid="true" @enderror
                        >
                        @error('nik') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="student_number">NIM/NIS <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="student_number"
                            name="student_number"
                            value="{{ old('student_number') }}"
                            maxlength="30"
                            required
                            @error('student_number') aria-invalid="true" @enderror
                        >
                        @error('student_number') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="school_name">Asal kampus/sekolah <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="school_name"
                            name="school_name"
                            value="{{ old('school_name') }}"
                            maxlength="150"
                            required
                            @error('school_name') aria-invalid="true" @enderror
                        >
                        @error('school_name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="school_major">Program studi/jurusan <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="school_major"
                            name="school_major"
                            value="{{ old('school_major') }}"
                            maxlength="100"
                            required
                            @error('school_major') aria-invalid="true" @enderror
                        >
                        @error('school_major') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="phone">Nomor telepon <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="phone"
                            name="phone"
                            value="{{ old('phone') }}"
                            maxlength="20"
                            required
                            @error('phone') aria-invalid="true" @enderror
                        >
                        @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="birth_date">Tanggal lahir <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="birth_date"
                            type="date"
                            name="birth_date"
                            value="{{ old('birth_date') }}"
                            required
                            @error('birth_date') aria-invalid="true" @enderror
                        >
                        @error('birth_date') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="gender">Jenis kelamin <span class="req" aria-hidden="true">*</span></label>
                        <select id="gender" name="gender" required @error('gender') aria-invalid="true" @enderror>
                            <option value="">Pilih jenis kelamin</option>
                            <option value="Laki-laki" @selected(old('gender') === 'Laki-laki')>Laki-laki</option>
                            <option value="Perempuan" @selected(old('gender') === 'Perempuan')>Perempuan</option>
                        </select>
                        @error('gender') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field-full">
                        <label for="address">Alamat <span class="req" aria-hidden="true">*</span></label>
                        <textarea
                            id="address"
                            name="address"
                            rows="3"
                            maxlength="2000"
                            required
                            @error('address') aria-invalid="true" @enderror
                        >{{ old('address') }}</textarea>
                        @error('address') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Simpan data pribadi</button>
                </div>
            </section>
        </div>
    </form>
@endsection
