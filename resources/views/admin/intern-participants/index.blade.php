@extends('layouts.app')

@section('title', 'Kelola Peserta Magang')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Kelola Peserta Magang</h1>
            <p class="page-description">Lihat dan perbarui data peserta yang sudah memiliki akun magang.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('admin.intern-participants.archived.index') }}">Peserta Terarsip</a>
            <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">
            @include('layouts.partials.icon', ['name' => 'check-circle'])
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @php
        $bulkErrors = collect($errors->getMessages())
            ->filter(fn ($messages, $field) => str_starts_with($field, 'user_intern_ids'))
            ->flatten();
    @endphp
    @if ($bulkErrors->isNotEmpty())
        <div class="alert alert-danger" role="alert">
            <div>@foreach ($bulkErrors as $error)<p>{{ $error }}</p>@endforeach</div>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.intern-participants.index') }}" class="filter-bar">
        <div class="field">
            <label for="q">Cari peserta</label>
            <input id="q" name="q" value="{{ $search }}" maxlength="100" placeholder="Nama, NIS/NIM, email, atau institusi">
        </div>
        <div class="field">
            <label for="status">Status peserta</label>
            <select id="status" name="status">
                <option value="" @selected($status === '')>Semua status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="completed" @selected($status === 'completed')>Selesai</option>
                <option value="unset" @selected($status === 'unset')>Belum Ditetapkan</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            @if ($search !== '' || $status !== '')
                <a class="btn btn-secondary" href="{{ route('admin.intern-participants.index') }}">Reset</a>
            @endif
        </div>
    </form>

    @if ($participants->isEmpty())
        <div class="table-wrap">
            <div class="empty-state">
                @include('layouts.partials.icon', ['name' => 'inbox'])
                <h2 class="empty-state-title">Tidak ada peserta yang cocok</h2>
                <p class="empty-state-text">Ubah kata kunci atau filter untuk melihat peserta lainnya.</p>
                @if ($search !== '' || $status !== '')
                    <a class="btn btn-secondary" href="{{ route('admin.intern-participants.index') }}">Hapus filter</a>
                @endif
            </div>
        </div>
    @else
        @php
            $bulkQuery = array_filter([
                'q' => $search,
                'status' => $status,
                'page' => $participants->currentPage(),
            ], fn ($value) => $value !== '' && $value !== null);
        @endphp
        <form id="bulk-archive-form" class="bulk-selection" method="POST" action="{{ route('admin.intern-participants.bulk-archive', $bulkQuery) }}" data-bulk-selection>
            @csrf
            <div class="bulk-toolbar">
                <span id="bulk-selection-count" aria-live="polite">0 peserta dipilih</span>
                <button class="btn btn-secondary" type="submit" data-bulk-submit disabled>Arsipkan pilihan</button>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col"><input id="bulk-select-all" class="bulk-checkbox" type="checkbox" aria-label="Pilih semua peserta pada halaman ini"></th>
                            <th scope="col">Nomor</th>
                            <th scope="col">Nama peserta</th>
                            <th scope="col">NIS/NIM</th>
                            <th scope="col">Asal institusi</th>
                            <th scope="col">Email</th>
                            <th scope="col">Nomor HP</th>
                            <th scope="col">Periode magang</th>
                            <th scope="col">Status</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($participants as $intern)
                            @php
                                $applicant = $intern->applicant;
                                $internshipRequest = $applicant->internshipRequest;
                                [$statusLabel, $statusClass] = match ($applicant->participant_status) {
                                    'active' => ['Aktif', 'badge-success'],
                                    'completed' => ['Selesai', 'badge-neutral'],
                                    default => ['Belum Ditetapkan', 'badge-warning'],
                                };
                            @endphp
                            <tr>
                                <td><input class="bulk-checkbox" type="checkbox" name="user_intern_ids[]" value="{{ $intern->getKey() }}" data-select-item aria-label="Pilih {{ $intern->name }}" @checked(in_array($intern->getKey(), (array) old('user_intern_ids', [])))></td>
                                <td class="cell-muted">{{ $participants->firstItem() + $loop->index }}</td>
                                <td><strong>{{ $intern->name }}</strong></td>
                                <td class="cell-muted">{{ $applicant->student_number ?: '—' }}</td>
                                <td>{{ $internshipRequest->institution->name }}</td>
                                <td class="cell-muted">{{ $intern->email }}</td>
                                <td class="cell-muted">{{ $intern->phone ?: '—' }}</td>
                                <td class="cell-muted nowrap">
                                    {{ $internshipRequest->start_date->format('d/m/Y') }} &ndash; {{ $internshipRequest->end_date->format('d/m/Y') }}
                                </td>
                                <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td>
                                    <a class="btn btn-secondary btn-sm" href="{{ route('admin.intern-participants.show', $intern->getKey()) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        <div class="pagination-summary">
            Menampilkan {{ $participants->firstItem() }}–{{ $participants->lastItem() }} dari {{ $participants->total() }} peserta
        </div>
        {{ $participants->links() }}
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-bulk-selection]');
            if (!form) return;

            const items = [...form.querySelectorAll('[data-select-item]')];
            const selectAll = form.querySelector('#bulk-select-all');
            const submit = form.querySelector('[data-bulk-submit]');
            const count = document.querySelector('#bulk-selection-count');

            const updateSelection = () => {
                const selected = items.filter((item) => item.checked).length;
                submit.disabled = selected === 0;
                count.textContent = `${selected} peserta dipilih`;
                selectAll.checked = items.length > 0 && selected === items.length;
                selectAll.indeterminate = selected > 0 && selected < items.length;
            };

            selectAll.addEventListener('change', () => {
                items.forEach((item) => { item.checked = selectAll.checked; });
                updateSelection();
            });
            items.forEach((item) => item.addEventListener('change', updateSelection));
            form.addEventListener('submit', (event) => {
                const selected = items.filter((item) => item.checked).length;
                if (selected === 0 || !confirm(`Arsipkan ${selected} peserta yang dipilih?`)) {
                    event.preventDefault();
                }
            });
            updateSelection();
        })();
    </script>
@endpush
