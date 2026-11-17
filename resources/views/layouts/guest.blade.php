<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') &mdash; {{ config('app.name', 'Enrollment Magang') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6fb;
            --surface: #ffffff;
            --border: #dde3ee;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #2563eb;
            --primary-soft: #e8efff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.55;
        }

        .guest-wrapper { width: 100%; max-width: 420px; }

        .guest-wrapper--wide { max-width: 560px; }

        .guest-brand {
            text-align: center;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 18px;
        }

        .guest-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 26px 26px 30px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        h1 { font-size: 1.3rem; margin: 0 0 6px; }

        .muted { color: var(--muted); font-size: 0.92rem; }

        .notice {
            margin: 16px 0;
            padding: 12px 16px;
            border-radius: 10px;
            background: var(--primary-soft);
            color: #1e3a8a;
            font-size: 0.92rem;
        }

        a { color: var(--primary); }

        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #ffffff;
            color: var(--text);
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group textarea { min-height: 72px; resize: vertical; }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: 2px solid var(--primary-soft);
            border-color: var(--primary);
        }

        .form-group input[aria-invalid="true"],
        .form-group select[aria-invalid="true"],
        .form-group textarea[aria-invalid="true"] { border-color: #b91c1c; }

        fieldset {
            border: 0;
            padding: 0;
            margin: 0 0 22px;
        }

        fieldset legend {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 12px;
            padding: 0;
        }

        .auth-links {
            margin: 16px 0 0;
            text-align: center;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .auth-links + .auth-links { margin-top: 6px; }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 4px 0 18px;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .btn-primary {
            width: 100%;
            padding: 11px 16px;
            border: 0;
            border-radius: 10px;
            background: var(--primary);
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }

        .btn-primary:hover { opacity: 0.92; }

        .btn-secondary {
            width: 100%;
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            color: var(--text);
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }

        .btn-secondary:hover { background: #f4f6fb; }

        .logout-form { margin: 10px 0 0; }

        .alert {
            margin: 0 0 18px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .alert ul { margin: 0; padding-left: 18px; }

        .alert-error { background: #fdecec; color: #b91c1c; }

        .alert-success { background: #e8f7ee; color: #15803d; }
    </style>
</head>
<body>
    <div class="guest-wrapper @yield('wrapper_class')">
        <div class="guest-brand">{{ config('app.name', 'Enrollment Magang') }}</div>

        <div class="guest-card">
            <h1>@yield('heading', 'Selamat Datang')</h1>
            <p class="muted">@yield('subheading', 'Sistem pendaftaran magang')</p>

            @yield('content')
        </div>
    </div>
</body>
</html>
