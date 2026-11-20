@extends('layouts.app')

@section('title', 'Shared Registration Token Diterbitkan')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Pengajuan Disetujui</h1>
            <p class="page-description">{{ $internshipRequest->title }} &middot; {{ $internshipRequest->institution->name }}</p>
        </div>
    </div>

    <div class="page-stack">
        <section class="card">
            <div class="alert alert-warning" role="alert">
                @include('layouts.partials.icon', ['name' => 'alert-circle'])
                <span>Token plaintext hanya ditampilkan pada hasil penerbitan ini. Salin dan bagikan kepada Operator secara aman. Token tidak dapat dilihat kembali dari halaman detail.</span>
            </div>

            <div class="detail-grid detail-grid-spaced">
                <div class="detail-item">
                    <p class="detail-label">Kuota awal</p>
                    <p class="detail-value">{{ $sharedToken->quota }} peserta</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Jumlah terpakai</p>
                    <p class="detail-value">{{ $sharedToken->used_count }}</p>
                </div>
            </div>

            <div class="field">
                <label for="shared-registration-token">Shared registration token</label>
                <div class="token-box">
                    <input
                        id="shared-registration-token"
                        class="input mono"
                        type="text"
                        readonly
                        value="{{ $plaintextToken }}"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-copy-target="#shared-registration-token"
                        data-copy-done="Token tersalin"
                    >
                        @include('layouts.partials.icon', ['name' => 'copy'])
                        <span data-copy-label aria-live="polite">Salin token</span>
                    </button>
                </div>
                <span class="field-hint">Setelah diteruskan kepada Operator, token yang sama dapat dibagikan kepada peserta sesuai kuota.</span>
            </div>

            <div class="form-actions">
                <a class="btn btn-secondary" href="{{ route('admin.internship-requests.show', $internshipRequest) }}">
                    @include('layouts.partials.icon', ['name' => 'arrow-left'])
                    <span>Kembali ke detail pengajuan</span>
                </a>
            </div>
        </section>
    </div>
@endsection
