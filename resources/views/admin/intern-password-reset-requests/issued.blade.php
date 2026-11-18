@extends('layouts.app')

@section('title', 'Tautan Reset Intern')

@section('content')
    <h1>Tautan Reset Password</h1>
    <p class="subtitle">Salin tautan ini sekarang dan kirimkan secara manual kepada Intern. Tautan hanya ditampilkan pada respons ini.</p>
    <section class="card">
        <p><strong>Intern:</strong> {{ $resetRequest->intern->name }} ({{ $resetRequest->intern->email }})</p>
        <p><strong>Berlaku sampai:</strong> {{ $resetRequest->token_expires_at->format('d/m/Y H:i:s') }}</p>
        <label for="reset-url">Tautan reset</label>
        <textarea id="reset-url" readonly rows="3" style="display:block;width:100%;padding:10px;margin-top:6px">{{ $resetUrl }}</textarea>
        <p class="muted">Jangan bagikan tautan ini selain kepada pemilik akun. Halaman ini tidak menyimpan tautan dalam session.</p>
        <a href="{{ route('admin.intern-password-reset-requests.show', $resetRequest) }}">Kembali ke detail permintaan</a>
    </section>
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
