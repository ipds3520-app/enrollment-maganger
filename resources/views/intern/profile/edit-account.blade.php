@extends('layouts.app')

@section('title', 'Edit Data Pribadi')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Edit Data Pribadi</h1>
            <p class="page-description">Perbarui data pribadi Anda. Email, NIK, dan asal institusi tidak dapat diubah.</p>
        </div>
    </div>

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

    <form method="POST" action="{{ route('intern.profile.update-details') }}">
        @csrf
        @method('PUT')

        <div class="page-stack">
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Informasi tetap</h2>
                        <p class="card-description">Email, NIK, dan institusi terhubung dengan akun serta pengajuan Anda.</p>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <p class="detail-label">Email</p>
                        <p class="detail-value">{{ $intern->email }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">NIK</p>
                        <p class="detail-value">{{ $applicant->nik ?: '—' }}</p>
                    </div>
                    <div class="detail-item">
                        <p class="detail-label">Asal kampus/sekolah</p>
                        <p class="detail-value">{{ $applicant->school_name ?: '—' }}</p>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Data yang dapat diperbarui</h2>
                        <p class="card-description">Perubahan nama dan nomor telepon juga akan diterapkan pada akun Anda.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="name">Nama lengkap <span class="req" aria-hidden="true">*</span></label>
                        <input id="name" name="name" value="{{ old('name', $intern->name) }}" maxlength="100" required @error('name') aria-invalid="true" @enderror>
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="student_number">NIM/NIS <span class="req" aria-hidden="true">*</span></label>
                        <input id="student_number" name="student_number" value="{{ old('student_number', $applicant->student_number) }}" maxlength="30" required @error('student_number') aria-invalid="true" @enderror>
                        @error('student_number') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="school_major">Program studi/jurusan <span class="req" aria-hidden="true">*</span></label>
                        <input id="school_major" name="school_major" value="{{ old('school_major', $applicant->school_major) }}" maxlength="100" required @error('school_major') aria-invalid="true" @enderror>
                        @error('school_major') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="phone">Nomor telepon <span class="req" aria-hidden="true">*</span></label>
                        <input id="phone" name="phone" value="{{ old('phone', $applicant->phone) }}" maxlength="20" required @error('phone') aria-invalid="true" @enderror>
                        @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="birth_date">Tanggal lahir <span class="req" aria-hidden="true">*</span></label>
                        <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date', $applicant->birth_date?->format('Y-m-d')) }}" required @error('birth_date') aria-invalid="true" @enderror>
                        @error('birth_date') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="gender">Jenis kelamin <span class="req" aria-hidden="true">*</span></label>
                        <select id="gender" name="gender" required @error('gender') aria-invalid="true" @enderror>
                            <option value="">Pilih jenis kelamin</option>
                            <option value="Laki-laki" @selected(old('gender', $applicant->gender) === 'Laki-laki')>Laki-laki</option>
                            <option value="Perempuan" @selected(old('gender', $applicant->gender) === 'Perempuan')>Perempuan</option>
                        </select>
                        @error('gender') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field-full">
                        <label for="address">Alamat <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="address" name="address" rows="3" maxlength="2000" required @error('address') aria-invalid="true" @enderror>{{ old('address', $applicant->address) }}</textarea>
                        @error('address') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-actions">
                    <a class="btn btn-secondary" href="{{ route('intern.profile.show') }}">Batal</a>
                    <button class="btn btn-primary" type="submit">Simpan perubahan</button>
                </div>
            </section>
        </div>
    </form>
@endsection
