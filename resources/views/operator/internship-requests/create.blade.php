@extends('layouts.app')

@section('title', 'Buat Pengajuan Magang')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Buat Pengajuan Permohonan Magang</h1>
            <p class="page-description">Lengkapi informasi pengajuan dan jumlah peserta yang dibutuhkan.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('operator.internship-requests.index') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Daftar pengajuan</span>
            </a>
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

    <form method="POST" action="{{ route('operator.internship-requests.store') }}">
        @csrf

        <div class="page-stack">
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Informasi Permohonan</h2>
                        <p class="card-description">Judul dan periode pelaksanaan magang.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field field-full">
                        <label for="title">Judul pengajuan <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="title"
                            name="title"
                            value="{{ old('title') }}"
                            placeholder="Contoh: Permohonan Magang Tahun Ajaran 202X/202X"
                            maxlength="150"
                            required
                            @error('title') aria-invalid="true" @enderror
                        >
                        @error('title')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="start_date">Tanggal mulai <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="start_date"
                            type="date"
                            class="date-input-polished"
                            name="start_date"
                            value="{{ old('start_date') }}"
                            min="{{ now()->toDateString() }}"
                            required
                            @error('start_date') aria-invalid="true" @enderror
                        >
                        @error('start_date')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="end_date">Tanggal selesai <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="end_date"
                            type="date"
                            class="date-input-polished"
                            name="end_date"
                            value="{{ old('end_date') }}"
                            min="{{ now()->toDateString() }}"
                            required
                            @error('end_date') aria-invalid="true" @enderror
                        >
                        @error('end_date')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Proposal Magang</h2>
                        <p class="card-description">Unggah proposal ke Google Drive, atur aksesnya, lalu tempelkan link dokumen.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field field-full">
                        <label for="proposal_url">Link proposal Google Drive <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="proposal_url"
                            name="proposal_url"
                            type="url"
                            inputmode="url"
                            value="{{ old('proposal_url') }}"
                            placeholder="https://drive.google.com/file/d/..."
                            required
                            autocomplete="url"
                            @error('proposal_url') aria-invalid="true" @enderror
                        >
                        <span class="field-hint">Atur akses dokumen ke “Anyone with the link – Viewer”. Siapa pun yang memiliki link dapat membuka dokumen.</span>
                        @error('proposal_url')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Jumlah Peserta</h2>
                        <p class="card-description">Peserta mendaftar mandiri menggunakan token setelah permohonan disetujui.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="participant_count">Jumlah peserta yang diajukan <span class="req" aria-hidden="true">*</span></label>
                        <input
                            id="participant_count"
                            name="participant_count"
                            type="number"
                            min="1"
                            step="1"
                            value="{{ old('participant_count') }}"
                            required
                            @error('participant_count') aria-invalid="true" @enderror
                        >
                        @error('participant_count')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </section>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Kirim pengajuan</button>
            <a href="{{ route('operator.internship-requests.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
@endsection
