<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Larapilot') — Workflow Dashboard</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --accent: #2563eb;
            --accent-soft: #dbeafe;
            --shadow: 0 1px 2px rgba(15, 23, 42, 0.06), 0 8px 24px rgba(15, 23, 42, 0.04);
            --radius: 12px;
            --status-todo: #94a3b8;
            --status-planned: #3b82f6;
            --status-progress: #f59e0b;
            --status-review: #8b5cf6;
            --status-done: #10b981;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1220;
                --surface: #111827;
                --border: #1f2937;
                --text: #e5e7eb;
                --muted: #9ca3af;
                --accent: #60a5fa;
                --accent-soft: #1e3a5f;
                --shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 8px 24px rgba(0, 0, 0, 0.25);
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .shell {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 20px 48px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 14px;
        }

        .brand h1 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 700;
        }

        .brand p {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 0.875rem;
        }

        .nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav a {
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
        }

        .nav a:hover,
        .nav a.active {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: var(--accent);
            text-decoration: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            border: 1px solid transparent;
        }

        .badge-todo { background: color-mix(in srgb, var(--status-todo) 18%, transparent); color: var(--status-todo); }
        .badge-planned { background: color-mix(in srgb, var(--status-planned) 18%, transparent); color: var(--status-planned); }
        .badge-in-progress { background: color-mix(in srgb, var(--status-progress) 18%, transparent); color: var(--status-progress); }
        .badge-review { background: color-mix(in srgb, var(--status-review) 18%, transparent); color: var(--status-review); }
        .badge-done { background: color-mix(in srgb, var(--status-done) 18%, transparent); color: var(--status-done); }

        .priority {
            display: inline-flex;
            align-items: center;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .priority-critical,
        .priority-high {
            color: #dc2626;
            background: color-mix(in srgb, #dc2626 18%, transparent);
        }

        .priority-medium {
            color: #ea580c;
            background: color-mix(in srgb, #ea580c 18%, transparent);
        }

        .priority-low {
            color: #16a34a;
            background: color-mix(in srgb, #16a34a 18%, transparent);
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .markdown {
            font-size: 0.95rem;
        }

        .markdown h1, .markdown h2, .markdown h3, .markdown h4 {
            scroll-margin-top: 80px;
            line-height: 1.25;
        }

        .markdown h2 {
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            font-size: 1.25rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.35rem;
        }

        .markdown h3 {
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            font-size: 1.05rem;
        }

        .markdown p { margin: 0.75rem 0; }
        .markdown ul { margin: 0.75rem 0; padding-left: 1.25rem; }
        .markdown li { margin: 0.35rem 0; }
        .markdown code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.85em;
            background: color-mix(in srgb, var(--border) 60%, transparent);
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
        }

        .markdown .checklist {
            list-style: none;
            padding-left: 0;
        }

        .markdown .checklist label {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .empty {
            padding: 48px 24px;
            text-align: center;
            color: var(--muted);
        }

        .footer-note {
            margin-top: 32px;
            text-align: center;
            color: var(--muted);
            font-size: 0.8rem;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark">LP</div>
                <div>
                    <h1>Larapilot</h1>
                    <p>Workflow dashboard</p>
                </div>
            </div>
            <nav class="nav" aria-label="Dashboard">
                <a href="{{ route('larapilot.dashboard.index') }}" @class(['active' => request()->routeIs('larapilot.dashboard.index')])>Board</a>
                <a href="{{ route('larapilot.dashboard.prd') }}" @class(['active' => request()->routeIs('larapilot.dashboard.prd')])>PRD</a>
            </nav>
        </header>

        @yield('content')

        <p class="footer-note">Read-only view of <code>.larapilot/</code> artifacts. Disabled in production.</p>
    </div>
    @stack('scripts')
</body>
</html>
