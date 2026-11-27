<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Sistem Pendaftaran Magang BPS untuk memfasilitasi pengajuan dan pengelolaan permohonan magang.">
    <title>Sistem Pendaftaran Magang BPS</title>
    @include('layouts.partials.favicon')

    @include('layouts.partials.theme-init')

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="landing-page">
    <a class="landing-skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <header class="landing-header">
        <div class="landing-container landing-nav">
            <a class="landing-brand" href="#beranda" aria-label="Badan Pusat Statistik — Sistem Pendaftaran Magang">
                <span class="landing-brand-copy">
                    <span class="landing-brand-agency">
                        <span>Badan Pusat Statistik</span>
                        <span>Kabupaten Magetan</span>
                    </span>
                    <span class="landing-brand-name">SISTEM PENDAFTARAN MAGANG</span>
                </span>
            </a>

            <nav class="landing-desktop-nav" aria-label="Navigasi utama">
                <a href="#beranda" aria-current="page">Beranda</a>
                <a href="#tentang">Tentang Sistem</a>
                <a href="#alur">Alur Pendaftaran</a>
            </nav>

            <div class="landing-nav-actions">
                <button type="button" class="icon-btn landing-theme-toggle" data-theme-toggle aria-label="Ganti tema" title="Ganti tema">
                    @include('layouts.partials.icon', ['name' => 'sun', 'class' => 'icon icon-sun'])
                    @include('layouts.partials.icon', ['name' => 'moon', 'class' => 'icon icon-moon'])
                </button>
                <a class="btn btn-ghost landing-login-link" href="{{ route('login') }}">Masuk</a>
            </div>

            <details class="landing-mobile-menu">
                <summary class="icon-btn" aria-label="Buka navigasi">
                    @include('layouts.partials.icon', ['name' => 'menu'])
                    <span>Menu</span>
                </summary>
                <nav class="landing-mobile-links" aria-label="Navigasi seluler">
                    <a href="#beranda">Beranda</a>
                    <a href="#tentang">Tentang Sistem</a>
                    <a href="#alur">Alur Pendaftaran</a>
                    <a href="{{ route('login') }}">Masuk</a>
                    <a href="{{ route('operator.login') }}">Ajukan Permohonan Magang</a>
                    <a href="{{ route('intern.register') }}">Registrasi peserta dengan token</a>
                </nav>
            </details>
        </div>
    </header>

    <main id="konten-utama">
        <section class="landing-hero" id="beranda" aria-labelledby="landing-title">
            <div class="landing-container landing-hero-grid">
                <div class="landing-hero-copy">
                    <p class="landing-eyebrow"><span aria-hidden="true"></span> Badan Pusat Statistik</p>
                    <h1 id="landing-title">Sistem Pendaftaran <span>Magang BPS Kabupaten Magetan</span></h1>
                    <p class="landing-hero-description">Platform untuk memfasilitasi pengajuan dan pengelolaan permohonan magang di lingkungan Badan Pusat Statistik.</p>
                    <div class="landing-hero-actions">
                        <a class="btn btn-primary" href="{{ route('operator.login') }}">
                            Ajukan Permohonan
                            @include('layouts.partials.icon', ['name' => 'chevron-right'])
                        </a>
                    </div>
                    <a class="landing-token-note" href="#registrasi-peserta">
                        Peserta magang mendaftar menggunakan token dari pengajuan yang telah disetujui.
                        <span aria-hidden="true">↓</span>
                    </a>
                </div>

                <div class="landing-visual">
                    <img
                        class="landing-office-image"
                        src="{{ asset('images/kantor-bps-magetan.jpg') }}"
                        alt="Gedung Badan Pusat Statistik Kabupaten Magetan"
                        fetchpriority="high"
                        decoding="async"
                    >
                </div>
            </div>
        </section>

        <section class="landing-about" id="tentang" aria-labelledby="about-title">
            <div class="landing-container landing-about-grid">
                <div>
                    <p class="landing-section-kicker">Tentang sistem</p>
                    <h2 id="about-title">Pengelolaan permohonan magang dalam satu alur</h2>
                </div>
                <div class="landing-about-copy">
                    <p>Sistem ini memfasilitasi institusi untuk mengajukan permohonan magang dan membantu Admin mengelola pemeriksaan permohonan. Setelah permohonan disetujui, peserta dapat membuat akun menggunakan token registrasi dan melengkapi profilnya.</p>
                    <a class="landing-text-link" href="#alur">Pelajari alur pendaftaran <span aria-hidden="true">→</span></a>
                </div>
            </div>
        </section>

        <section class="landing-process" id="alur" aria-labelledby="process-title">
            <div class="landing-container">
                <div class="landing-section-heading">
                    <p class="landing-section-kicker">Alur pendaftaran</p>
                    <h2 id="process-title">Dari pengajuan hingga profil peserta</h2>
                    <p>Setiap tahap dilakukan sesuai peran institusi, Admin, dan peserta.</p>
                </div>

                <ol class="landing-steps">
                    <li>
                        <span class="landing-step-number">01</span>
                        <h3>Pendaftaran Institusi</h3>
                        <p>Institusi membuat akun Operator melalui aplikasi.</p>
                    </li>
                    <li>
                        <span class="landing-step-number">02</span>
                        <h3>Pengajuan Magang</h3>
                        <p>Operator mengajukan permohonan, mengisi jumlah peserta, dan memasukkan link proposal.</p>
                    </li>
                    <li>
                        <span class="landing-step-number">03</span>
                        <h3>Pemeriksaan Admin</h3>
                        <p>Admin meninjau permohonan dan memberikan keputusan.</p>
                    </li>
                    <li>
                        <span class="landing-step-number">04</span>
                        <h3>Penerbitan Token</h3>
                        <p>Jika disetujui, sistem menerbitkan token registrasi peserta.</p>
                    </li>
                    <li>
                        <span class="landing-step-number">05</span>
                        <h3>Registrasi Peserta</h3>
                        <p>Peserta menggunakan token untuk membuat akun dan melengkapi profil.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="landing-participant" id="registrasi-peserta" aria-labelledby="participant-title">
            <div class="landing-container landing-participant-panel">
                <div>
                    <p class="landing-section-kicker">Untuk calon peserta</p>
                    <h2 id="participant-title">Sudah memiliki token registrasi?</h2>
                    <p>Registrasi peserta hanya dapat dilanjutkan menggunakan token dari permohonan magang yang telah disetujui.</p>
                </div>
                <a class="btn btn-primary" href="{{ route('intern.register') }}">
                    Registrasi Peserta
                    @include('layouts.partials.icon', ['name' => 'chevron-right'])
                </a>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="landing-container landing-footer-content">
            <div>
                <span class="landing-brand-agency">
                        <strong>Badan Pusat Statistik</strong>
                        <strong>Kabupaten Magetan</strong>
                </span>
                <span>SISTEM PENDAFTARAN MAGANG</span>
            </div>
            <p>Sistem untuk memfasilitasi pengajuan dan pengelolaan permohonan magang.</p>
        </div>
    </footer>

    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
