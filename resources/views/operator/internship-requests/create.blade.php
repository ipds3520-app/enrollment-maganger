@extends('layouts.app')

@section('title', 'Buat Pengajuan Magang')

@section('content')
    <h1>Buat Pengajuan Permohonan Magang</h1>
    <p class="subtitle">Lengkapi informasi pengajuan dan jumlah peserta yang dibutuhkan.</p>

    @if ($errors->any())
        <section class="card" role="alert" style="margin-bottom:18px;border-color:#f3c5c5">
            <strong>Periksa kembali data berikut:</strong>
            <ul style="margin-bottom:0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="POST" action="{{ route('operator.internship-requests.store') }}" enctype="multipart/form-data">
        @csrf
        <section class="card" style="margin-bottom:18px">
            <h2>Informasi Permohonan</h2>
            <div style="display:grid;gap:14px">
                <label>Judul Pengajuan
                    <input name="title" value="{{ old('title') }}" maxlength="150" required style="display:block;width:100%;padding:10px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
                    @error('title') <span style="color:#b91c1c">{{ $message }}</span> @enderror
                </label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
                    <label>Tanggal Mulai
                        <input type="date" name="start_date" value="{{ old('start_date') }}" required style="display:block;width:100%;padding:10px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
                        @error('start_date') <span style="color:#b91c1c">{{ $message }}</span> @enderror
                    </label>
                    <label>Tanggal Selesai
                        <input type="date" name="end_date" value="{{ old('end_date') }}" required style="display:block;width:100%;padding:10px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
                        @error('end_date') <span style="color:#b91c1c">{{ $message }}</span> @enderror
                    </label>
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom:18px">
            <h2>Proposal Magang</h2>
            <p class="muted">Unggah satu proposal untuk seluruh peserta dalam pengajuan ini. Format PDF, maksimal 10 MB.</p>
            <label for="proposal">File proposal PDF
                <input id="proposal" name="proposal" type="file" accept=".pdf,application/pdf" required style="display:block;width:100%;max-width:520px;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
            </label>
            @error('proposal') <span style="color:#b91c1c">{{ $message }}</span> @enderror
        </section>

        <section class="card" style="margin-bottom:18px">
            <h2>Jumlah Peserta</h2>
            <p class="muted">Peserta akan mendaftar secara mandiri menggunakan token setelah permohonan disetujui.</p>
            <label for="participant_count">Jumlah peserta yang diajukan
                <input id="participant_count" name="participant_count" type="number" min="1" step="1" value="{{ old('participant_count') }}" required style="display:block;width:100%;max-width:260px;padding:10px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
            </label>
            @error('participant_count') <span style="color:#b91c1c">{{ $message }}</span> @enderror
        </section>

        <div style="display:flex;flex-wrap:wrap;gap:12px">
            <button type="submit" style="padding:11px 17px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer">Kirim Pengajuan</button>
            <a href="{{ route('operator.internship-requests.index') }}" style="padding:11px 14px">Batal</a>
        </div>
    </form>

@endsection
