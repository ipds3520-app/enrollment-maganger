@extends('layouts.app')

@section('title', 'Kelola Peserta Magang')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Kelola Peserta Magang</h1>
            <p class="page-description">Lihat dan perbarui data peserta magang yang sudah memiliki akun magang.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('admin.intern-participants.archived.index') }}">Peserta Magang Terarsip</a>
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
        <form id="bulk-archive-form" class="bulk-selection" method="POST" action="{{ route('admin.intern-participants.bulk-archive', $bulkQuery) }}" data-bulk-selection data-archive-action="{{ route('admin.intern-participants.bulk-archive', $bulkQuery) }}" data-lifecycle-action="{{ route('admin.intern-participants.bulk-lifecycle.update', $bulkQuery) }}">
            @csrf
            <div class="bulk-control-card">
                <div class="bulk-toolbar">
                    <span id="bulk-selection-count" aria-live="polite">0 peserta dipilih</span>
                    <div class="bulk-toolbar-actions">
                        <button class="btn btn-primary" type="button" data-status-panel-toggle hidden>Ubah Status</button>
                        <button class="btn btn-secondary" type="submit" data-bulk-submit="archive" data-archive-button hidden disabled>Arsipkan pilihan</button>
                    </div>
                </div>
                <div class="bulk-status-panel" data-status-panel hidden>
                    <label class="bulk-status-control" for="bulk-participant-status">
                        <span>Status baru</span>
                        <select id="bulk-participant-status" class="bulk-status-select" name="participant_status" data-bulk-status>
                            <option value="">Pilih status peserta</option>
                            <option value="active">Aktif</option>
                            <option value="completed">Selesai</option>
                        </select>
                    </label>
                    <div class="bulk-status-actions">
                        <button class="btn btn-primary" type="submit" data-bulk-submit="lifecycle" disabled>Terapkan</button>
                        <button class="btn btn-secondary" type="button" data-status-panel-cancel>Batalkan</button>
                    </div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col"><input id="bulk-select-all" class="bulk-checkbox" type="checkbox" aria-label="Pilih semua peserta pada halaman ini"></th>
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
                                <td><input class="bulk-checkbox" type="checkbox" name="user_intern_ids[]" value="{{ $intern->getKey() }}" data-select-item data-participant-status="{{ $applicant->participant_status ?? '' }}" aria-label="Pilih {{ $intern->name }}" @checked(in_array($intern->getKey(), (array) old('user_intern_ids', [])))></td>
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
            const lifecycleSubmit = form.querySelector('[data-bulk-submit="lifecycle"]');
            const archiveSubmit = form.querySelector('[data-bulk-submit="archive"]');
            const statusPanelToggle = form.querySelector('[data-status-panel-toggle]');
            const statusPanel = form.querySelector('[data-status-panel]');
            const statusPanelCancel = form.querySelector('[data-status-panel-cancel]');
            const statusSelect = form.querySelector('[data-bulk-status]');
            const count = document.querySelector('#bulk-selection-count');
            let statusPanelOpen = false;

            const updateSelection = () => {
                const selectedItems = items.filter((item) => item.checked);
                const selected = selectedItems.length;
                const allCompleted = selected > 0 && selectedItems.every((item) => item.dataset.participantStatus === 'completed');
                if (selected === 0 && statusPanelOpen) {
                    statusPanelOpen = false;
                    statusSelect.value = '';
                }
                statusPanelToggle.hidden = selected === 0 || statusPanelOpen;
                statusPanel.hidden = selected === 0 || !statusPanelOpen;
                lifecycleSubmit.disabled = selected === 0 || statusSelect.value === '';
                archiveSubmit.disabled = !allCompleted;
                archiveSubmit.hidden = !allCompleted;
                count.textContent = `${selected} peserta dipilih`;
                selectAll.checked = items.length > 0 && selected === items.length;
                selectAll.indeterminate = selected > 0 && selected < items.length;
            };

            selectAll.addEventListener('change', () => {
                items.forEach((item) => { item.checked = selectAll.checked; });
                updateSelection();
            });
            items.forEach((item) => item.addEventListener('change', updateSelection));
            statusSelect.addEventListener('change', updateSelection);
            statusPanelToggle.addEventListener('click', () => {
                statusPanelOpen = true;
                updateSelection();
                statusSelect.focus();
            });
            statusPanelCancel.addEventListener('click', () => {
                statusPanelOpen = false;
                statusSelect.value = '';
                updateSelection();
            });
            form.addEventListener('submit', (event) => {
                const selectedItems = items.filter((item) => item.checked);
                const action = event.submitter?.dataset.bulkSubmit;

                if (selectedItems.length === 0) {
                    event.preventDefault();
                    return;
                }

                if (action === 'archive') {
                    if (!selectedItems.every((item) => item.dataset.participantStatus === 'completed')
                        || !confirm(`Arsipkan ${selectedItems.length} peserta yang dipilih?`)) {
                        event.preventDefault();
                        return;
                    }
                    form.action = form.dataset.archiveAction;
                    return;
                }

                if (action === 'lifecycle') {
                    if (!statusPanelOpen || !statusSelect.value) {
                        event.preventDefault();
                        return;
                    }
                    form.action = form.dataset.lifecycleAction;
                    if (!confirm(`Ubah status ${selectedItems.length} peserta menjadi ${statusSelect.options[statusSelect.selectedIndex].text}?`)) {
                        event.preventDefault();
                    }
                }
            });
            updateSelection();
        })();
    </script>
@endpush
