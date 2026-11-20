@extends('layouts.app')

@section('title', 'Registration Token Diterbitkan')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">{{ ($replacement ?? false) ? 'Token Peserta Diterbitkan' : 'Pengajuan Disetujui' }}</h1>
            <p class="page-description">{{ $internshipRequest->title }} &middot; {{ $internshipRequest->institution->name }}</p>
        </div>
    </div>

    <div class="page-stack">
        <section class="card">
            <div class="alert alert-warning" role="alert">
                @include('layouts.partials.icon', ['name' => 'alert-circle'])
                <span>Token asli hanya ditampilkan pada respons ini. Salin dan distribusikan dengan aman; token tidak dapat dilihat kembali.</span>
            </div>

            <div class="list-stack">
                @foreach ($issuedTokens as $index => $item)
                    <article class="list-item">
                        <div class="list-item-header">
                            <div>
                                <h2 class="list-item-title">{{ $item['applicant']->name }}</h2>
                                <p class="list-item-subtitle">{{ $item['applicant']->email }}</p>
                            </div>
                        </div>

                        <div class="detail-grid">
                            <div class="detail-item">
                                <p class="detail-label">Nomor induk</p>
                                <p class="detail-value">{{ $item['applicant']->student_number ?: 'â€”' }}</p>
                            </div>
                            <div class="detail-item">
                                <p class="detail-label">Email</p>
                                <p class="detail-value">{{ $item['applicant']->email }}</p>
                            </div>
                            <div class="detail-item">
                                <p class="detail-label">Kedaluwarsa</p>
                                <p class="detail-value">{{ $item['expires_at']->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>

                        <div class="field field-spaced">
                            <label for="registration-token-{{ $index }}">Registration token</label>
                            <div class="token-box">
                                <input
                                    id="registration-token-{{ $index }}"
                                    class="input mono"
                                    type="text"
                                    readonly
                                    value="{{ $item['token'] }}"
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-copy-target="#registration-token-{{ $index }}"
                                    data-copy-done="Token tersalin"
                                >
                                    @include('layouts.partials.icon', ['name' => 'copy'])
                                    <span data-copy-label aria-live="polite">Salin token</span>
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
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
