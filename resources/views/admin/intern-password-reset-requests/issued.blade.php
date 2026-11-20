@extends('layouts.app')

@section('title', 'Tautan Reset Intern')

@section('content')
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title">Tautan Reset Password</h1>
            <p class="page-description">Salin tautan ini sekarang dan kirimkan secara manual kepada peserta. Tautan hanya ditampilkan pada respons ini.</p>
        </div>
    </div>

    <div class="page-stack">
        <section class="card">
            <div class="alert alert-warning" role="alert">
                @include('layouts.partials.icon', ['name' => 'alert-circle'])
                <span>Jangan bagikan tautan ini selain kepada pemilik akun. Halaman ini tidak menyimpan tautan dalam session.</span>
            </div>

            <div class="detail-grid detail-grid-spaced">
                <div class="detail-item">
                    <p class="detail-label">Peserta</p>
                    <p class="detail-value">{{ $resetRequest->intern->name }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Email</p>
                    <p class="detail-value">{{ $resetRequest->intern->email }}</p>
                </div>
                <div class="detail-item">
                    <p class="detail-label">Berlaku sampai</p>
                    <p class="detail-value">{{ $resetRequest->token_expires_at->format('d/m/Y H:i:s') }}</p>
                </div>
            </div>

            <div class="field">
                <label for="reset-url">Tautan reset</label>
                <textarea id="reset-url" class="input mono" rows="3" readonly>{{ $resetUrl }}</textarea>
            </div>

            <div class="form-actions">
                <button
                    type="button"
                    class="btn btn-primary"
                    data-copy-target="#reset-url"
                    data-copy-done="Tautan tersalin"
                >
                    @include('layouts.partials.icon', ['name' => 'copy'])
                    <span data-copy-label aria-live="polite">Salin tautan</span>
                </button>
                <a class="btn btn-secondary" href="{{ route('admin.intern-password-reset-requests.show', $resetRequest) }}">
                    @include('layouts.partials.icon', ['name' => 'arrow-left'])
                    <span>Kembali ke detail</span>
                </a>
            </div>
        </section>
    </div>

    <script>
        window.addEventListener('pagehide', () => {
            const link = document.getElementById('reset-url');
            if (link) {
                link.value = '';
                link.textContent = '';
            }
        });
    </script>
@endsection
