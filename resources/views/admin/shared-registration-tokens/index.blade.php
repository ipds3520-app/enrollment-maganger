@extends('layouts.app')

@section('title', 'Pemantauan Token Pendaftaran')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Pemantauan Token Pendaftaran</h1>
            <p class="page-description">Pantau token bersama, kuota, dan permohonan dari seluruh institusi.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">
                @include('layouts.partials.icon', ['name' => 'arrow-left'])
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.shared-registration-tokens.index') }}" class="filter-bar">
        <div class="field">
            <label for="q">Cari token atau permohonan</label>
            <input id="q" name="q" value="{{ $search }}" placeholder="Institusi, operator, atau judul permohonan">
        </div>
        <div class="field">
            <label for="status">Status token</label>
            <select id="status" name="status">
                <option value="">Semua status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="exhausted" @selected($status === 'exhausted')>Kuota habis</option>
                <option value="revoked" @selected($status === 'revoked')>Dicabut</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            @if ($search !== '' || $status)
                <a class="btn btn-secondary" href="{{ route('admin.shared-registration-tokens.index') }}">Reset</a>
            @endif
        </div>
    </form>

    @if ($tokens->isEmpty())
        <div class="table-wrap">
            <div class="empty-state">
                @include('layouts.partials.icon', ['name' => 'inbox'])
                <h2 class="empty-state-title">Belum ada token yang cocok</h2>
                <p class="empty-state-text">Token shared akan tampil setelah permohonan disetujui Admin.</p>
            </div>
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Referensi</th>
                        <th scope="col">Institusi / Operator</th>
                        <th scope="col">Permohonan</th>
                        <th scope="col">Kuota</th>
                        <th scope="col">Selesai</th>
                        <th scope="col">Sisa</th>
                        <th scope="col">Status</th>
                        <th scope="col">Diterbitkan</th>
                        <th scope="col">Perubahan kuota terakhir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tokens as $token)
                        @php
                            $tokenStatus = match ($token->status) {
                                'active' => 'Aktif',
                                'exhausted' => 'Kuota habis',
                                'revoked' => 'Dicabut',
                                default => ucfirst($token->status),
                            };
                            $tokenStatusClass = match ($token->status) {
                                'active' => 'badge-success',
                                'revoked' => 'badge-danger',
                                default => 'badge-neutral',
                            };
                        @endphp
                        <tr>
                            <td class="cell-muted">#{{ $token->getKey() }}</td>
                            <td>
                                <strong>{{ $token->internshipRequest->institution->name }}</strong>
                                <div class="cell-muted">{{ $token->internshipRequest->creator->name }}</div>
                            </td>
                            <td>
                                <a href="{{ route('admin.internship-requests.show', $token->internshipRequest) }}">{{ $token->internshipRequest->title }}</a>
                            </td>
                            <td class="cell-muted">{{ $token->quota }}</td>
                            <td class="cell-muted">{{ $token->used_count }}</td>
                            <td class="cell-muted">{{ max(0, $token->quota - $token->used_count) }}</td>
                            <td><span class="badge {{ $tokenStatusClass }}">{{ $tokenStatus }}</span></td>
                            <td class="cell-muted nowrap">{{ $token->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="cell-muted">
                                @if ($token->quota_updated_at)
                                    {{ $token->quotaUpdater?->name ?? 'Admin tidak tersedia' }}<br>
                                    <span class="nowrap">{{ $token->quota_updated_at->format('d/m/Y H:i') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $tokens->links() }}
    @endif
@endsection
