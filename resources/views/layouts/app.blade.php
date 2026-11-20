<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Dashboard') &mdash; {{ config('app.name', 'Enrollment Magang') }}</title>

    @include('layouts.partials.theme-init')

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
    @php
        $currentUser = request()->user();
        $dashboardArea = request()->routeIs('admin.*') ? 'admin' : (request()->routeIs('operator.*') ? 'operator' : 'intern');
        $logoutRoute = $dashboardArea.'.logout';

        $roleLabel = match ($dashboardArea) {
            'admin' => 'Admin',
            'operator' => 'Operator',
            default => 'Peserta Magang',
        };

        $navGroups = match ($dashboardArea) {
            'admin' => [
                [
                    'label' => 'Menu utama',
                    'items' => [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'active' => 'admin.dashboard'],
                    ],
                ],
                [
                    'label' => 'Manajemen',
                    'items' => [
                        ['route' => 'admin.internship-requests.index', 'label' => 'Pengajuan magang', 'icon' => 'file-text', 'active' => 'admin.internship-requests.*'],
                        ['route' => 'admin.intern-password-reset-requests.index', 'label' => 'Reset password peserta', 'icon' => 'key', 'active' => 'admin.intern-password-reset-requests.*'],
                    ],
                ],
            ],
            'operator' => [
                [
                    'label' => 'Menu utama',
                    'items' => [
                        ['route' => 'operator.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'active' => 'operator.dashboard'],
                    ],
                ],
                [
                    'label' => 'Pengajuan',
                    'items' => [
                        ['route' => 'operator.internship-requests.index', 'label' => 'Daftar pengajuan', 'icon' => 'file-text', 'active' => ['operator.internship-requests.index', 'operator.internship-requests.show']],
                        ['route' => 'operator.internship-requests.create', 'label' => 'Buat pengajuan', 'icon' => 'plus', 'active' => 'operator.internship-requests.create'],
                    ],
                ],
            ],
            default => [
                [
                    'label' => 'Menu utama',
                    'items' => [
                        ['route' => 'intern.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'active' => 'intern.dashboard'],
                    ],
                ],
                [
                    'label' => 'Akun',
                    'items' => [
                        ['route' => 'intern.profile.show', 'label' => 'Data pribadi', 'icon' => 'user', 'active' => 'intern.profile.*'],
                    ],
                ],
            ],
        };

        $initials = collect(preg_split('/\s+/', trim((string) $currentUser?->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $initials = $initials !== '' ? $initials : '?';
    @endphp

    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
            <div class="sidebar-header">
                <a class="brand" href="{{ route($dashboardArea.'.dashboard') }}">
                    <span class="brand-mark">
                        @include('layouts.partials.icon', ['name' => 'graduation-cap'])
                    </span>
                    <span class="brand-text">
                        <span class="brand-name">{{ config('app.name', 'Enrollment Magang') }}</span>
                        <span class="brand-sub">Sistem Administrasi</span>
                    </span>
                </a>
                <button type="button" class="icon-btn sidebar-close" data-sidebar-close aria-label="Tutup menu">
                    @include('layouts.partials.icon', ['name' => 'x'])
                </button>
            </div>

            <nav class="sidebar-nav">
                @foreach ($navGroups as $group)
                    <div class="nav-group">
                        <p class="nav-group-label">{{ $group['label'] }}</p>
                        @foreach ($group['items'] as $item)
                            @php($isActive = request()->routeIs($item['active']))
                            <a
                                class="nav-item {{ $isActive ? 'is-active' : '' }}"
                                href="{{ route($item['route']) }}"
                                @if ($isActive) aria-current="page" @endif
                            >
                                @include('layouts.partials.icon', ['name' => $item['icon']])
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-footer-meta">
                    @include('layouts.partials.icon', ['name' => 'shield-check'])
                    <span>Masuk sebagai {{ $roleLabel }}</span>
                </div>
            </div>
        </aside>

        <button type="button" class="sidebar-overlay" data-sidebar-overlay data-sidebar-close hidden aria-label="Tutup menu"></button>

        <div class="app-main">
            <header class="navbar">
                <div class="navbar-left">
                    <button
                        type="button"
                        class="icon-btn navbar-menu"
                        data-sidebar-toggle
                        aria-label="Buka menu"
                        aria-controls="sidebar"
                        aria-expanded="false"
                    >
                        @include('layouts.partials.icon', ['name' => 'menu'])
                    </button>

                    <div class="navbar-title">
                        <span class="navbar-breadcrumb">{{ $roleLabel }}<span class="sep">/</span>Ruang kerja</span>
                        <span class="navbar-heading">@yield('title', 'Dashboard')</span>
                    </div>
                </div>

                <div class="navbar-right">
                    <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Ganti tema" title="Ganti tema">
                        @include('layouts.partials.icon', ['name' => 'sun', 'class' => 'icon icon-sun'])
                        @include('layouts.partials.icon', ['name' => 'moon', 'class' => 'icon icon-moon'])
                    </button>

                    @if ($currentUser)
                        <div class="profile" data-profile>
                            <button
                                type="button"
                                class="profile-trigger"
                                data-profile-toggle
                                aria-haspopup="menu"
                                aria-expanded="false"
                                aria-controls="profile-menu"
                            >
                                <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                                <span class="profile-meta">
                                    <span class="profile-name">{{ $currentUser->name }}</span>
                                    <span class="profile-role">{{ $roleLabel }}</span>
                                </span>
                                @include('layouts.partials.icon', ['name' => 'chevron-down', 'class' => 'icon profile-chevron'])
                            </button>

                            <div class="profile-menu" id="profile-menu" data-profile-menu role="menu" hidden>
                                <div class="profile-menu-head">
                                    <strong>{{ $currentUser->name }}</strong>
                                    <span>{{ $currentUser->email }}</span>
                                </div>
                                <form method="POST" action="{{ route($logoutRoute) }}">
                                    @csrf
                                    <button type="submit" class="profile-menu-item danger" role="menuitem">
                                        @include('layouts.partials.icon', ['name' => 'log-out'])
                                        <span>Keluar</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </header>

            <main class="content" id="main-content">
                @yield('content')
            </main>
        </div>
    </div>

    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
