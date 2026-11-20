<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Masuk') &mdash; {{ config('app.name', 'Enrollment Magang') }}</title>

    @include('layouts.partials.theme-init')

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <button type="button" class="icon-btn guest-theme theme-toggle" data-theme-toggle aria-label="Ganti tema" title="Ganti tema">
        @include('layouts.partials.icon', ['name' => 'sun', 'class' => 'icon icon-sun'])
        @include('layouts.partials.icon', ['name' => 'moon', 'class' => 'icon icon-moon'])
    </button>

    <main class="guest-page">
        <div class="guest-wrapper @yield('wrapper_class')">
            <div class="guest-brand">
                <div class="guest-brand-row">
                    <span class="brand-mark">
                        @include('layouts.partials.icon', ['name' => 'graduation-cap'])
                    </span>
                    <span class="guest-brand-name">{{ config('app.name', 'Enrollment Magang') }}</span>
                </div>
                <span class="guest-brand-text">Sistem Pendaftaran Magang</span>
            </div>

            <div class="guest-card">
                <h1>@yield('heading', 'Selamat Datang')</h1>
                <p class="guest-subheading">@yield('subheading', 'Sistem pendaftaran magang')</p>

                @yield('content')
            </div>

            <p class="guest-footer">&copy; {{ date('Y') }} {{ config('app.name', 'Enrollment Magang') }}</p>
        </div>
    </main>

    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
