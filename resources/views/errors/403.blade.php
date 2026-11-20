<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Akses Ditolak &mdash; {{ config('app.name', 'Enrollment Magang') }}</title>

    @include('layouts.partials.theme-init')

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <button type="button" class="icon-btn guest-theme theme-toggle" data-theme-toggle aria-label="Ganti tema" title="Ganti tema">
        @include('layouts.partials.icon', ['name' => 'sun', 'class' => 'icon icon-sun'])
        @include('layouts.partials.icon', ['name' => 'moon', 'class' => 'icon icon-moon'])
    </button>

    <main class="error-page">
        <div class="error-card">
            <span class="error-code error-code-danger">403</span>
            <h1>Akses Ditolak</h1>
            <p>Tautan tidak valid, sudah kedaluwarsa, atau Anda tidak memiliki akses ke halaman ini.</p>
            <a class="btn btn-primary" href="{{ url('/') }}">Kembali ke halaman utama</a>
        </div>
    </main>

    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
