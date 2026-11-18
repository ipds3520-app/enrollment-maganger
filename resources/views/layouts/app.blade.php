<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') &mdash; {{ config('app.name', 'Enrollment Magang') }}</title>
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
            --danger: #b91c1c;
            --danger-soft: #fdecec;
            --success: #15803d;
            --success-soft: #e8f7ee;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.55;
        }

        a { color: var(--primary); }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 24px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .brand { font-weight: 700; font-size: 1.05rem; }

        .user-info { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .container { max-width: 960px; margin: 0 auto; padding: 28px 24px 56px; }

        h1 { font-size: 1.5rem; margin: 0 0 6px; }
        h2 { font-size: 1.05rem; margin: 0 0 14px; }

        .muted { color: var(--muted); }
        .subtitle { margin: 0 0 24px; color: var(--muted); }

        .grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        dl.detail { display: grid; grid-template-columns: 120px 1fr; gap: 8px 14px; margin: 0; }
        dl.detail dt { color: var(--muted); font-size: 0.88rem; }
        dl.detail dd { margin: 0; font-weight: 500; word-break: break-word; }

        .status { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .status-ok { background: var(--success-soft); color: var(--success); }
        .status-wait { background: var(--danger-soft); color: var(--danger); }

        .notice {
            margin: 0;
            padding: 12px 16px;
            border-radius: 10px;
            background: var(--primary-soft);
            color: #1e3a8a;
            font-size: 0.92rem;
        }

        .logout-form { margin: 0; }

        .logout-button {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 0.85rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }

        .logout-button:hover {
            background: var(--danger-soft);
            border-color: #f3c5c5;
            color: var(--danger);
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">{{ config('app.name', 'Enrollment Magang') }}</div>
        @php
            $currentUser = request()->user();
            $dashboardArea = request()->routeIs('admin.*') ? 'admin' : (request()->routeIs('operator.*') ? 'operator' : 'intern');
            $logoutRoute = $dashboardArea.'.logout';
        @endphp
        @if ($currentUser)
            <div class="user-info">
                <span>{{ $currentUser->name }}</span>
                <span class="badge">{{ ucfirst($dashboardArea) }}</span>
                <form class="logout-form" method="POST" action="{{ route($logoutRoute) }}">
                    @csrf
                    <button type="submit" class="logout-button">Keluar</button>
                </form>
            </div>
        @endif
    </header>

    <main class="container">
        @yield('content')
    </main>
</body>
</html>
