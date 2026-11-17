<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terlalu Banyak Permintaan &mdash; {{ config('app.name', 'Enrollment Magang') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6fb;
            --surface: #ffffff;
            --border: #dde3ee;
            --text: #1f2937;
            --muted: #6b7280;
            --warning: #92400e;
            --warning-soft: #fef3c7;
            --primary: #2563eb;
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

        .error-card {
            width: 100%;
            max-width: 460px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 30px 28px;
            text-align: center;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .error-code {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            background: var(--warning-soft);
            color: var(--warning);
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.04em;
        }

        h1 { font-size: 1.35rem; margin: 16px 0 8px; }

        p { margin: 0 0 22px; color: var(--muted); }

        .back-link {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 10px;
            background: var(--primary);
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
        }

        .back-link:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="error-code">429</span>
        <h1>Terlalu Banyak Permintaan</h1>
        <p>Anda telah mencoba terlalu sering. Silakan tunggu sebentar, lalu coba lagi.</p>
        <a class="back-link" href="{{ url('/') }}">Kembali ke Halaman Utama</a>
    </div>
</body>
</html>
