@extends('layouts.app')

@section('title', $archived ? 'Detail Peserta Terarsip' : 'Detail Peserta Magang')

@section('content')
    @php
        $applicant = $intern->applicant;
        $internshipRequest = $applicant->internshipRequest;
        [$statusLabel, $statusClass] = match ($applicant->participant_status) {
            'active' => ['Aktif', 'badge-success'],
            'completed' => ['Selesai', 'badge-neutral'],
            default => ['Belum Ditetapkan', 'badge-warning'],
        };
    @endphp

    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">{{ $archived ? 'Detail Peserta Terarsip' : 'Detail Peserta Magang' }}</h1>
            <p class="page-description">Informasi akun, profil, dan periode magang peserta.</p>
        </div>
        <div class="page-actions">
            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
            @if ($archived)
                <form method="POST" action="{{ route('admin.intern-participants.restore', $intern->getKey()) }}" onsubmit="return confirm('Pulihkan peserta ini ke daftar aktif?')">
                    @csrf
                    <button class="btn btn-primary" type="submit">Restore peserta</button>
                </form>
            @else
                <a class="btn btn-primary" href="{{ route('admin.intern-participants.edit', $intern->getKey()) }}">Edit data</a>
                <form method="POST" action="{{ route('admin.intern-participants.archive', $intern->getKey()) }}" onsubmit="return confirm('Arsipkan peserta ini? Data profil, absensi, token, dan riwayat akan tetap tersimpan.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-secondary" type="submit">Arsipkan</button>
                </form>
            @endif
            <a class="btn btn-secondary" href="{{ route($archived ? 'admin.intern-participants.archived.index' : 'admin.intern-participants.index') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>{{ $archived ? 'Daftar terarsip' : 'Daftar peserta' }}</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">
            @include('layouts.partials.icon', ['name' => 'check-circle'])
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="page-stack">
        @if (! $archived)
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Status Lifecycle</h2>
                        <p class="page-description">Pilih status secara eksplisit. Status belum ditetapkan tidak berubah otomatis.</p>
                    </div>
                </div>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.intern-participants.lifecycle.update', $intern->getKey()) }}" class="form-grid">
                    @csrf
                    @method('PATCH')
                    <div class="field">
                        <label for="participant_status">Status peserta</label>
                        <select id="participant_status" name="participant_status" required>
                            <option value="" disabled @selected($applicant->participant_status === null)>Pilih status</option>
                            <option value="active" @selected($applicant->participant_status === 'active')>Aktif</option>
                            <option value="completed" @selected($applicant->participant_status === 'completed')>Selesai</option>
                        </select>
                    </div>
                    <div class="form-actions field-full">
                        <button class="btn btn-primary" type="submit">Simpan status</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="card">
            <div class="card-header"><h2 class="card-title">Data Peserta</h2></div>
            <div class="detail-grid">
                <div class="detail-item"><p class="detail-label">Nama lengkap</p><p class="detail-value">{{ $intern->name }}</p></div>
                <div class="detail-item"><p class="detail-label">NIS/NIM</p><p class="detail-value">{{ $applicant->student_number ?: '—' }}</p></div>
                <div class="detail-item"><p class="detail-label">Email</p><p class="detail-value">{{ $intern->email }}</p></div>
                <div class="detail-item"><p class="detail-label">Nomor HP</p><p class="detail-value">{{ $intern->phone ?: '—' }}</p></div>
                <div class="detail-item"><p class="detail-label">Program studi / jurusan</p><p class="detail-value">{{ $applicant->school_major ?: '—' }}</p></div>
                <div class="detail-item"><p class="detail-label">Asal institusi</p><p class="detail-value">{{ $internshipRequest->institution->name }}</p></div>
                <div class="detail-item"><p class="detail-label">Alamat</p><p class="detail-value">{{ $applicant->address ?: '—' }}</p></div>
                <div class="detail-item"><p class="detail-label">Tanggal lahir</p><p class="detail-value">{{ $applicant->birth_date?->format('d/m/Y') ?? '—' }}</p></div>
                <div class="detail-item"><p class="detail-label">Jenis kelamin</p><p class="detail-value">{{ $applicant->gender ?: '—' }}</p></div>
                <div class="detail-item"><p class="detail-label">Status lifecycle</p><p class="detail-value"><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></p></div>
                @if ($applicant->participant_status === 'completed')
                    <div class="detail-item"><p class="detail-label">Tanggal penyelesaian</p><p class="detail-value">{{ $applicant->completed_at?->format('d/m/Y H:i') ?? '—' }}</p></div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Periode Magang</h2></div>
            <div class="detail-grid">
                <div class="detail-item"><p class="detail-label">Judul pengajuan</p><p class="detail-value">{{ $internshipRequest->title }}</p></div>
                <div class="detail-item"><p class="detail-label">Institusi</p><p class="detail-value">{{ $internshipRequest->institution->name }}</p></div>
                <div class="detail-item"><p class="detail-label">Mulai</p><p class="detail-value">{{ $internshipRequest->start_date->format('d/m/Y') }}</p></div>
                <div class="detail-item"><p class="detail-label">Selesai</p><p class="detail-value">{{ $internshipRequest->end_date->format('d/m/Y') }}</p></div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Riwayat Status</h2></div>
            @if ($applicant->statusHistories->isEmpty())
                <p class="empty-state-text">Belum ada perubahan status lifecycle.</p>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Status sebelumnya</th><th>Status setelah perubahan</th><th>Waktu</th><th>Admin</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($applicant->statusHistories as $history)
                                <tr>
                                    <td>{{ match ($history->old_status) { 'active' => 'Aktif', 'completed' => 'Selesai', default => 'Belum Ditetapkan' } }}</td>
                                    <td>{{ $history->new_status === 'completed' ? 'Selesai' : 'Aktif' }}</td>
                                    <td class="nowrap">{{ $history->changed_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $history->changedBy?->name ?? 'Admin tidak tersedia' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
