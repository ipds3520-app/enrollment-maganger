@extends('layouts.app')

@section('title', 'Buat Pengajuan Magang')

@section('content')
    <h1>Buat Pengajuan Permohonan Magang</h1>
    <p class="subtitle">Lengkapi informasi pengajuan dan tambahkan minimal satu calon peserta.</p>

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
                <label>Deskripsi <span class="muted">(opsional)</span>
                    <textarea name="description" rows="4" style="display:block;width:100%;padding:10px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">{{ old('description') }}</textarea>
                    @error('description') <span style="color:#b91c1c">{{ $message }}</span> @enderror
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
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px">
                <div>
                    <h2 style="margin-bottom:4px">Calon Peserta</h2>
                    <p class="muted" style="margin:0">Nama dan email wajib diisi. Data lainnya opsional.</p>
                </div>
                <button type="button" id="add-applicant" style="padding:9px 13px;border:1px solid #2563eb;border-radius:8px;background:#fff;color:#2563eb;font-weight:600;cursor:pointer">Tambah Calon</button>
            </div>
            @error('applicants') <p style="color:#b91c1c">{{ $message }}</p> @enderror

            @php
                $oldApplicants = old('applicants', [[]]);
                $oldApplicants = is_array($oldApplicants) && count($oldApplicants) ? $oldApplicants : [[]];
            @endphp
            <div id="applicants">
                @foreach ($oldApplicants as $index => $applicant)
                    @php $applicant = is_array($applicant) ? $applicant : []; @endphp
                    <fieldset class="applicant-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin:0 0 14px;padding:14px;border:1px solid #dde3ee;border-radius:10px">
                        <legend>Calon Peserta</legend>
                        @foreach ([['name', 'Nama', true], ['email', 'Email', true], ['phone', 'Telepon', false], ['student_number', 'Nomor Induk', false], ['school_major', 'Jurusan', false]] as [$field, $label, $required])
                            <label>{{ $label }}
                                <input name="applicants[{{ $index }}][{{ $field }}]" value="{{ $applicant[$field] ?? '' }}" @if ($required) required @endif style="display:block;width:100%;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px">
                                @error("applicants.$index.$field") <span style="color:#b91c1c">{{ $message }}</span> @enderror
                            </label>
                        @endforeach
                        <button type="button" class="remove-applicant" style="align-self:end;padding:9px 12px;border:1px solid #b91c1c;border-radius:8px;background:#fff;color:#b91c1c;cursor:pointer">Hapus Baris</button>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <div style="display:flex;flex-wrap:wrap;gap:12px">
            <button type="submit" style="padding:11px 17px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer">Kirim Pengajuan</button>
            <a href="{{ route('operator.internship-requests.index') }}" style="padding:11px 14px">Batal</a>
        </div>
    </form>

    <template id="applicant-template">
        <fieldset class="applicant-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin:0 0 14px;padding:14px;border:1px solid #dde3ee;border-radius:10px">
            <legend>Calon Peserta</legend>
            <label>Nama<input name="applicants[__INDEX__][name]" maxlength="100" required style="display:block;width:100%;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px"></label>
            <label>Email<input type="email" name="applicants[__INDEX__][email]" maxlength="150" required style="display:block;width:100%;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px"></label>
            <label>Telepon<input name="applicants[__INDEX__][phone]" maxlength="20" style="display:block;width:100%;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px"></label>
            <label>Nomor Induk<input name="applicants[__INDEX__][student_number]" maxlength="30" style="display:block;width:100%;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px"></label>
            <label>Jurusan<input name="applicants[__INDEX__][school_major]" maxlength="100" style="display:block;width:100%;padding:9px;margin-top:5px;border:1px solid #cbd5e1;border-radius:8px"></label>
            <button type="button" class="remove-applicant" style="align-self:end;padding:9px 12px;border:1px solid #b91c1c;border-radius:8px;background:#fff;color:#b91c1c;cursor:pointer">Hapus Baris</button>
        </fieldset>
    </template>

    <script>
        (() => {
            const container = document.getElementById('applicants');
            const template = document.getElementById('applicant-template');
            let nextIndex = {{ count($oldApplicants) }};

            const updateRemoveButtons = () => {
                const rows = container.querySelectorAll('.applicant-row');
                rows.forEach((row) => {
                    row.querySelector('.remove-applicant').disabled = rows.length === 1;
                });
            };

            document.getElementById('add-applicant').addEventListener('click', () => {
                const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
                container.insertAdjacentHTML('beforeend', html);
                updateRemoveButtons();
            });

            container.addEventListener('click', (event) => {
                if (event.target.matches('.remove-applicant') && container.querySelectorAll('.applicant-row').length > 1) {
                    event.target.closest('.applicant-row').remove();
                    updateRemoveButtons();
                }
            });

            updateRemoveButtons();
        })();
    </script>
@endsection
