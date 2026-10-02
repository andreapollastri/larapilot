@php
    $navigation = [
        'Workflow' => [
            ['route' => 'larapilot.dashboard.index', 'active' => ['larapilot.dashboard.index', 'larapilot.dashboard.spec'], 'label' => 'Board', 'icon' => 'board'],
            ['route' => 'larapilot.dashboard.prd', 'active' => ['larapilot.dashboard.prd'], 'label' => 'PRD', 'icon' => 'prd'],
            ['route' => 'larapilot.dashboard.inception', 'active' => ['larapilot.dashboard.inception'], 'label' => 'Inception', 'icon' => 'inception'],
            ['route' => 'larapilot.dashboard.plan', 'active' => ['larapilot.dashboard.plan'], 'label' => 'Plan', 'icon' => 'plan'],
            ['route' => 'larapilot.dashboard.design', 'active' => ['larapilot.dashboard.design*'], 'label' => 'Design', 'icon' => 'design'],
        ],
        'Workspace' => [
            ['route' => 'larapilot.dashboard.about', 'active' => ['larapilot.dashboard.about'], 'label' => 'About', 'icon' => 'about'],
            ['route' => 'larapilot.dashboard.settings', 'active' => ['larapilot.dashboard.settings'], 'label' => 'Settings', 'icon' => 'settings'],
            ['route' => 'larapilot.dashboard.skills', 'active' => ['larapilot.dashboard.skill*'], 'label' => 'Skills', 'icon' => 'skills'],
            ['route' => 'larapilot.dashboard.files', 'active' => ['larapilot.dashboard.files*'], 'label' => 'File manager', 'icon' => 'files', 'when' => app(\Larapilot\Services\ConfigService::class)->fileManagerBrowsable()],
            ['route' => 'larapilot.dashboard.database', 'active' => ['larapilot.dashboard.database*'], 'label' => 'Database', 'icon' => 'database', 'when' => app(\Larapilot\Services\ConfigService::class)->databaseViewerBrowsable()],
            ['route' => 'larapilot.dashboard.logs', 'active' => ['larapilot.dashboard.logs*'], 'label' => 'Logs', 'icon' => 'logs', 'when' => app(\Larapilot\Services\ConfigService::class)->logViewerBrowsable()],
            ['route' => 'larapilot.dashboard.laravel', 'active' => ['larapilot.dashboard.laravel*'], 'label' => 'Laravel', 'icon' => 'laravel', 'when' => app(\Larapilot\Services\ConfigService::class)->laravelViewerBrowsable()],
            ['route' => 'larapilot.dashboard.git', 'active' => ['larapilot.dashboard.git'], 'label' => 'Git', 'icon' => 'git'],
        ],
        'Insights' => [
            ['route' => 'larapilot.dashboard.usage', 'active' => ['larapilot.dashboard.usage'], 'label' => 'Usage', 'icon' => 'usage'],
            ['route' => 'larapilot.dashboard.economics', 'active' => ['larapilot.dashboard.economics*'], 'label' => 'Economics', 'icon' => 'economics'],
            ['route' => 'larapilot.dashboard.security', 'active' => ['larapilot.dashboard.security*'], 'label' => 'Security', 'icon' => 'shield'],
            ['route' => 'larapilot.dashboard.sbom', 'active' => ['larapilot.dashboard.sbom*'], 'label' => 'SBOM', 'icon' => 'package'],
            ['route' => 'larapilot.dashboard.errors', 'active' => ['larapilot.dashboard.errors*'], 'label' => 'Errors', 'icon' => 'bug'],
        ],
        'Reference' => [
            ['route' => 'larapilot.api.docs', 'active' => ['larapilot.api.*'], 'label' => 'API', 'icon' => 'api'],
            ['route' => 'larapilot.dashboard.docs', 'active' => ['larapilot.dashboard.docs'], 'label' => 'Docs', 'icon' => 'docs'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f6f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f1419" media="(prefers-color-scheme: dark)">
    <title>@yield('title', 'Larapilot') — Workflow Dashboard</title>
    <script>
        // Before the first paint, so a saved theme never flashes the other one.
        (function () {
            try {
                var theme = localStorage.getItem('larapilot-theme');

                if (theme === 'light' || theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
            } catch (error) {}
        })();
    </script>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6f7;
            --surface: #ffffff;
            --surface-2: #f8fafb;
            --surface-3: #edf1f4;
            --border: #e2e7eb;
            --border-strong: #cdd5dc;
            --text: #17212b;
            --text-2: #3b4957;
            --muted: #586776;
            --accent: #2e6f8e;
            --accent-strong: #235972;
            --accent-soft: #e3eef3;
            --accent-contrast: #ffffff;
            --ok: #1f7356;
            --ok-fill: #35a07d;
            --warn: #8a590e;
            --warn-fill: #d9952f;
            --danger: #ab3430;
            --danger-fill: #d9574f;
            --danger-solid: #ab3430;
            --violet: #6d569c;
            --violet-fill: #8e76bf;
            --sky-fill: #3f97c2;
            --status-todo: #8594a3;
            --status-planned: #4a7fb0;
            --status-progress: #d9952f;
            --status-review: #8e76bf;
            --status-done: #35a07d;
            --heat-0: #e6ebef;
            --heat-1: #b9dccd;
            --heat-2: #7cc0a4;
            --heat-3: #3f9e7c;
            --heat-4: #1f6f55;
            --shadow: 0 1px 2px rgba(23, 33, 43, 0.04);
            --shadow-lg: 0 16px 40px rgba(23, 33, 43, 0.12);
            --select-chevron: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23586776' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            --radius: 14px;
            --radius-sm: 10px;
            --radius-xs: 7px;
            --sidebar: 252px;
            --gutter: 16px;
            --font: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Monaco, Consolas, monospace;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                color-scheme: dark;
                --bg: #0f1419;
                --surface: #161c23;
                --surface-2: #1a222a;
                --surface-3: #222c36;
                --border: #26303a;
                --border-strong: #36434f;
                --text: #e7ecf0;
                --text-2: #c4cdd6;
                --muted: #8f9dab;
                --accent: #7fbad6;
                --accent-strong: #a3cfe4;
                --accent-soft: #1a3140;
                --accent-contrast: #0f1419;
                --ok: #63c4a0;
                --ok-fill: #4fb08c;
                --warn: #e2ad5c;
                --warn-fill: #d9952f;
                --danger: #ee8680;
                --danger-fill: #d9574f;
                --danger-solid: #ab3430;
                --violet: #b09ade;
                --violet-fill: #8e76bf;
                --sky-fill: #5cb0da;
                --status-todo: #8f9dab;
                --status-planned: #78a9d8;
                --status-progress: #e2ad5c;
                --status-review: #b09ade;
                --status-done: #63c4a0;
                --heat-0: #1f2831;
                --heat-1: #1d4537;
                --heat-2: #24694f;
                --heat-3: #329070;
                --heat-4: #63c4a0;
                --shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
                --shadow-lg: 0 16px 40px rgba(0, 0, 0, 0.45);
                --select-chevron: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238f9dab' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            }
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --bg: #0f1419;
            --surface: #161c23;
            --surface-2: #1a222a;
            --surface-3: #222c36;
            --border: #26303a;
            --border-strong: #36434f;
            --text: #e7ecf0;
            --text-2: #c4cdd6;
            --muted: #8f9dab;
            --accent: #7fbad6;
            --accent-strong: #a3cfe4;
            --accent-soft: #1a3140;
            --accent-contrast: #0f1419;
            --ok: #63c4a0;
            --ok-fill: #4fb08c;
            --warn: #e2ad5c;
            --warn-fill: #d9952f;
            --danger: #ee8680;
            --danger-fill: #d9574f;
            --danger-solid: #ab3430;
            --violet: #b09ade;
            --violet-fill: #8e76bf;
            --sky-fill: #5cb0da;
            --status-todo: #8f9dab;
            --status-planned: #78a9d8;
            --status-progress: #e2ad5c;
            --status-review: #b09ade;
            --status-done: #63c4a0;
            --heat-0: #1f2831;
            --heat-1: #1d4537;
            --heat-2: #24694f;
            --heat-3: #329070;
            --heat-4: #63c4a0;
            --shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 16px 40px rgba(0, 0, 0, 0.45);
            --select-chevron: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238f9dab' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        }

        *, *::before, *::after { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; scroll-padding-top: 76px; }

        body {
            margin: 0;
            font-family: var(--font);
            font-size: 0.9375rem;
            line-height: 1.55;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        h1, h2, h3, h4 { letter-spacing: -0.012em; line-height: 1.25; font-weight: 650; }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; text-underline-offset: 3px; }

        code, pre, kbd { font-family: var(--mono); }

        code {
            font-size: 0.86em;
            padding: 0.1em 0.4em;
            border-radius: 5px;
            background: var(--surface-3);
            color: var(--text-2);
        }

        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 4px;
        }

        [hidden] { display: none !important; }

        .icon { width: 18px; height: 18px; flex: none; display: block; }

        .skip {
            position: absolute;
            left: 12px;
            top: -60px;
            z-index: 60;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            background: var(--accent);
            color: var(--accent-contrast);
            font-weight: 600;
        }

        .skip:focus { top: 12px; }

        /* ---- shell ---- */

        .mobilebar {
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            align-items: center;
            gap: 10px;
            height: 58px;
            padding: 0 var(--gutter);
            padding-top: env(safe-area-inset-top);
            background: color-mix(in srgb, var(--bg) 88%, transparent);
            -webkit-backdrop-filter: saturate(1.4) blur(12px);
            backdrop-filter: saturate(1.4) blur(12px);
            border-bottom: 1px solid var(--border);
        }

        .icon-btn {
            display: inline-grid;
            place-items: center;
            width: 40px;
            height: 40px;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text-2);
            cursor: pointer;
            font: inherit;
        }

        .icon-btn:hover { border-color: var(--border-strong); color: var(--text); }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
            color: var(--text);
            text-decoration: none;
        }

        .brand:hover { text-decoration: none; }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex: none;
            border-radius: 10px;
            background: var(--accent);
            color: var(--accent-contrast);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .brand-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
        .brand-name { font-size: 0.98rem; font-weight: 650; letter-spacing: -0.01em; }
        .brand-sub { color: var(--muted); font-size: 0.76rem; }

        .mobilebar .brand { flex: 1; }
        .mobilebar .brand-sub { display: none; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 50;
            display: flex;
            flex-direction: column;
            width: min(86vw, 300px);
            padding: 18px 14px calc(14px + env(safe-area-inset-bottom));
            background: var(--surface);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            overscroll-behavior: contain;
            transform: translateX(-102%);
            visibility: hidden;
            transition: transform 0.22s ease, visibility 0s linear 0.22s;
        }

        :root[data-nav="open"] .sidebar {
            transform: none;
            visibility: visible;
            transition: transform 0.22s ease;
            box-shadow: var(--shadow-lg);
        }

        .sidebar-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 0 6px 16px;
        }

        .scrim {
            position: fixed;
            inset: 0;
            z-index: 40;
            border: 0;
            padding: 0;
            background: rgba(10, 16, 22, 0.46);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.22s ease;
        }

        :root[data-nav="open"] .scrim { opacity: 1; pointer-events: auto; }
        :root[data-nav="open"] body { overflow: hidden; }

        .nav { display: flex; flex-direction: column; gap: 18px; flex: 1; }
        .nav-group { display: flex; flex-direction: column; gap: 2px; }

        .nav-label {
            padding: 0 10px 6px;
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 650;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 42px;
            padding: 0 10px;
            border-radius: var(--radius-sm);
            color: var(--text-2);
            font-size: 0.92rem;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.14s ease, color 0.14s ease;
        }

        .nav a:hover { background: var(--surface-3); color: var(--text); text-decoration: none; }

        .nav a.active {
            background: var(--accent-soft);
            color: var(--accent-strong);
            font-weight: 600;
        }

        .nav-ico { display: grid; place-items: center; color: var(--muted); }
        .nav a:hover .nav-ico { color: var(--text-2); }
        .nav a.active .nav-ico { color: var(--accent); }

        .sidebar-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 18px;
            padding: 14px 6px 0;
            border-top: 1px solid var(--border);
        }

        .version { color: var(--muted); font-size: 0.74rem; font-variant-numeric: tabular-nums; }

        .theme-switch {
            display: inline-flex;
            padding: 3px;
            gap: 2px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--surface-2);
        }

        .theme-switch button {
            display: grid;
            place-items: center;
            width: 32px;
            height: 28px;
            padding: 0;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
        }

        .theme-switch button .icon { width: 16px; height: 16px; }
        .theme-switch button:hover { color: var(--text); }

        .theme-switch button[aria-pressed="true"] {
            background: var(--surface);
            color: var(--accent);
            box-shadow: var(--shadow), 0 0 0 1px var(--border);
        }

        .main {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 22px var(--gutter) 56px;
            min-width: 0;
        }

        .main.is-wide { max-width: none; }

        @media (min-width: 640px) {
            :root { --gutter: 24px; }
        }

        @media (min-width: 1024px) {
            :root { --gutter: 36px; }

            html { scroll-padding-top: 24px; }

            .mobilebar, .scrim, .sidebar-close { display: none; }

            .sidebar {
                width: var(--sidebar);
                transform: none;
                visibility: visible;
                transition: none;
                background: var(--surface);
                box-shadow: none !important;
            }

            .app { padding-left: var(--sidebar); }
            .main { padding-top: 34px; }
            .nav a { min-height: 38px; font-size: 0.89rem; }
        }

        /* ---- page scaffolding ---- */

        .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px 20px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .page-head > :first-child { min-width: 0; flex: 1 1 320px; }

        .page-head h2 {
            margin: 0 0 6px;
            font-size: 1.5rem;
            letter-spacing: -0.022em;
        }

        .page-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .sub, .hint { color: var(--muted); }

        .sub {
            margin: 0;
            font-size: 0.92rem;
            line-height: 1.55;
            max-width: 74ch;
        }

        .page-head .sub + .sub { margin-top: 8px; }

        .hint { font-size: 0.82rem; line-height: 1.5; }

        .eyebrow {
            color: var(--muted);
            font-size: 0.7rem;
            font-weight: 650;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .stack { display: flex; flex-direction: column; gap: 18px; }

        /* ---- components ---- */

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .panel { padding: 18px; }

        @media (min-width: 640px) {
            .panel { padding: 22px 24px; }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 15px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--accent);
            background: var(--accent);
            color: var(--accent-contrast);
            font: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color 0.14s ease, border-color 0.14s ease, color 0.14s ease;
        }

        .btn:hover { background: var(--accent-strong); border-color: var(--accent-strong); text-decoration: none; }
        .btn .icon { width: 16px; height: 16px; }

        .btn.ghost {
            border-color: var(--border-strong);
            background: var(--surface);
            color: var(--text);
        }

        .btn.ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--surface); }

        .btn.danger { border-color: var(--danger-solid); background: var(--danger-solid); color: #fff; }
        .btn.danger:hover { border-color: var(--danger-solid); background: var(--danger-solid); filter: brightness(0.92); }

        .btn.small { min-height: 32px; padding: 0 11px; font-size: 0.8rem; border-radius: var(--radius-xs); }

        .btn.is-disabled,
        .btn[disabled] {
            opacity: 0.5;
            pointer-events: none;
            border-color: var(--border);
            background: var(--surface-3);
            color: var(--muted);
        }

        @media (min-width: 1024px) {
            .btn { min-height: 36px; }
        }

        .field {
            display: grid;
            gap: 5px;
            min-width: 0;
            color: var(--muted);
            font-size: 0.7rem;
            font-weight: 650;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .control,
        .field input[type="search"],
        .field input[type="text"],
        .field select,
        .field textarea {
            width: 100%;
            min-height: 40px;
            padding: 8px 11px;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
            font: inherit;
            font-size: 0.9rem;
            font-weight: 400;
            letter-spacing: normal;
            text-transform: none;
            transition: border-color 0.14s ease, box-shadow 0.14s ease;
        }

        .control:focus,
        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
        }

        @media (min-width: 1024px) {
            .control,
            .field input[type="search"],
            .field input[type="text"],
            .field select { min-height: 36px; padding: 6px 11px; font-size: 0.875rem; }
        }

        /* the native arrow sits flush against a custom border: draw our own chevron instead */
        select:not([multiple]):not([size]) {
            -webkit-appearance: none;
            appearance: none;
            padding-right: 34px;
            background-image: var(--select-chevron);
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px 16px;
            text-overflow: ellipsis;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 12px;
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }

        @media (min-width: 720px) {
            .metrics { grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
        }

        .metric { padding: 13px 15px; min-width: 0; }

        @media (min-width: 720px) {
            .metric { padding: 16px 18px; }
        }

        .metric-label {
            color: var(--muted);
            font-size: 0.7rem;
            font-weight: 650;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .metric-value {
            margin-top: 6px;
            font-size: 1.4rem;
            font-weight: 650;
            line-height: 1.1;
            letter-spacing: -0.025em;
            font-variant-numeric: tabular-nums;
            overflow-wrap: anywhere;
        }

        @media (min-width: 720px) {
            .metric-value { margin-top: 8px; font-size: 1.65rem; }
        }

        .metric-note { margin-top: 6px; color: var(--muted); font-size: 0.76rem; line-height: 1.4; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 3px 10px 3px 9px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 650;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            white-space: nowrap;
            --tone: var(--status-todo);
            background: color-mix(in srgb, var(--tone) 15%, transparent);
            color: color-mix(in srgb, var(--tone) 54%, var(--text));
        }

        .badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: var(--tone);
        }

        .badge-todo { --tone: var(--status-todo); }
        .badge-planned { --tone: var(--status-planned); }
        .badge-in-progress { --tone: var(--status-progress); }
        .badge-review { --tone: var(--status-review); }
        .badge-done { --tone: var(--status-done); }

        .priority {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.66rem;
            font-weight: 650;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            --tone: var(--warn-fill);
            background: color-mix(in srgb, var(--tone) 15%, transparent);
            color: color-mix(in srgb, var(--tone) 54%, var(--text));
        }

        .priority-critical, .priority-high { --tone: var(--danger-fill); }
        .priority-medium { --tone: var(--warn-fill); }
        .priority-low { --tone: var(--ok-fill); }

        .points {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent-strong);
            font-size: 0.66rem;
            font-weight: 650;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-variant-numeric: tabular-nums;
        }

        .chips { display: flex; flex-wrap: wrap; gap: 6px; }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 11px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--surface-2);
            color: var(--muted);
            font-size: 0.78rem;
        }

        .chip.current {
            border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
            background: var(--accent-soft);
            color: var(--accent-strong);
            font-weight: 600;
        }

        .chip.live {
            border-color: color-mix(in srgb, var(--ok-fill) 45%, var(--border));
            background: color-mix(in srgb, var(--ok-fill) 13%, transparent);
            color: var(--ok);
            font-weight: 600;
        }

        .chip.stale {
            border-color: color-mix(in srgb, var(--warn-fill) 50%, var(--border));
            background: color-mix(in srgb, var(--warn-fill) 14%, transparent);
            color: var(--warn);
            font-weight: 600;
        }

        .flash {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-size: 0.9rem;
            --tone: var(--accent);
            border-color: color-mix(in srgb, var(--tone) 40%, var(--border));
            background: color-mix(in srgb, var(--tone) 9%, var(--surface));
        }

        .flash--success { --tone: var(--ok-fill); color: var(--ok); }
        .flash--warn { --tone: var(--warn-fill); color: var(--warn); }
        .flash--error { --tone: var(--danger-fill); color: var(--danger); }
        .flash strong { font-weight: 600; }

        .flash ul {
            margin: 0;
            padding-left: 1.1rem;
            color: var(--text-2);
            font-size: 0.82rem;
            word-break: break-word;
        }

        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        .bar-track {
            height: 8px;
            border-radius: 999px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            border-radius: inherit;
            background: var(--accent);
        }

        .empty {
            padding: 44px 24px;
            text-align: center;
            color: var(--muted);
        }

        .empty p { margin: 0 auto; max-width: 60ch; }

        .markdown { font-size: 0.95rem; line-height: 1.65; overflow-wrap: anywhere; }
        .markdown > :first-child { margin-top: 0; }
        .markdown > :last-child { margin-bottom: 0; }

        .markdown h1, .markdown h2, .markdown h3, .markdown h4 { scroll-margin-top: 80px; }
        .markdown h1 { margin: 0 0 1rem; font-size: 1.6rem; letter-spacing: -0.025em; }

        .markdown h2 {
            margin: 2.2rem 0 0.8rem;
            padding-bottom: 0.45rem;
            border-bottom: 1px solid var(--border);
            font-size: 1.22rem;
        }

        .markdown h3 { margin: 1.6rem 0 0.5rem; font-size: 1.04rem; }
        .markdown h4 { margin: 1.3rem 0 0.4rem; font-size: 0.95rem; }
        .markdown p { margin: 0.75rem 0; }
        .markdown ul, .markdown ol { margin: 0.75rem 0; padding-left: 1.3rem; }
        .markdown li { margin: 0.3rem 0; }
        .markdown hr { border: 0; border-top: 1px solid var(--border); margin: 1.8rem 0; }
        .markdown img { max-width: 100%; height: auto; border-radius: var(--radius-xs); }

        .markdown blockquote {
            margin: 1rem 0;
            padding: 2px 0 2px 16px;
            border-left: 3px solid var(--border-strong);
            color: var(--text-2);
        }

        .markdown pre {
            margin: 1rem 0;
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: var(--surface-2);
            overflow-x: auto;
            font-size: 0.84rem;
            line-height: 1.55;
        }

        .markdown pre code { padding: 0; background: transparent; font-size: inherit; }

        .markdown table {
            display: block;
            width: 100%;
            margin: 1rem 0;
            border-collapse: collapse;
            overflow-x: auto;
            font-size: 0.88rem;
        }

        .markdown th, .markdown td {
            padding: 8px 12px;
            border: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }

        .markdown th { background: var(--surface-2); font-weight: 600; }

        /* a table scrolls inside its own frame before a word is cut in two */
        .markdown th, .markdown td { overflow-wrap: normal; word-break: normal; }
        .markdown td { min-width: 6.5rem; }
        .markdown :is(th, td) code { white-space: nowrap; }

        .markdown .checklist { list-style: none; padding-left: 0; }
        .markdown .checklist label { display: flex; align-items: flex-start; gap: 8px; }
        .markdown .checklist input { margin-top: 0.35em; accent-color: var(--accent); }

        /* task lists: the box stands in for the bullet */
        .markdown ul:has(> li > input[type="checkbox"]) { list-style: none; padding-left: 0.15rem; }
        .markdown li > input[type="checkbox"] { margin: 0 8px 0 0; vertical-align: -1px; accent-color: var(--accent); }

        .footer-note {
            margin: 40px 0 0;
            text-align: center;
            color: var(--muted);
            font-size: 0.78rem;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        @media print {
            .mobilebar, .sidebar, .scrim, .footer-note { display: none !important; }
            .app { padding-left: 0 !important; }
            .card { box-shadow: none; }
        }
    </style>
    <noscript>
        <style>
            /* Without scripts the drawer cannot open: lay the menu out in the page. */
            .mobilebar .icon-btn { display: none; }
            .sidebar { position: static; width: auto; transform: none; visibility: visible; border-right: 0; border-bottom: 1px solid var(--border); }
            .sidebar-head, .theme-switch { display: none; }
            @media (min-width: 1024px) {
                .sidebar { position: fixed; width: var(--sidebar); border-right: 1px solid var(--border); border-bottom: 0; }
                .sidebar-head { display: flex; }
            }
        </style>
    </noscript>
    @stack('styles')
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>

    <div class="app">
        <header class="mobilebar">
            <button type="button" class="icon-btn" data-nav-open aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
                @include('larapilot::dashboard.partials.icon', ['name' => 'menu'])
            </button>
            <a class="brand" href="{{ route('larapilot.dashboard.index') }}">
                <span class="brand-mark" aria-hidden="true">LP</span>
                <span class="brand-text">
                    <span class="brand-name">Larapilot</span>
                </span>
            </a>
            <button type="button" class="icon-btn" data-theme-toggle aria-label="Switch between light and dark">
                <span data-theme-icon="light">@include('larapilot::dashboard.partials.icon', ['name' => 'moon'])</span>
                <span data-theme-icon="dark" hidden>@include('larapilot::dashboard.partials.icon', ['name' => 'sun'])</span>
            </button>
        </header>

        <button type="button" class="scrim" data-nav-close tabindex="-1" aria-label="Close menu"></button>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-head">
                <a class="brand" href="{{ route('larapilot.dashboard.index') }}">
                    <span class="brand-mark" aria-hidden="true">LP</span>
                    <span class="brand-text">
                        <span class="brand-name">Larapilot</span>
                        <span class="brand-sub">Workflow dashboard</span>
                    </span>
                </a>
                <button type="button" class="icon-btn sidebar-close" data-nav-close aria-label="Close menu">
                    @include('larapilot::dashboard.partials.icon', ['name' => 'close'])
                </button>
            </div>

            <nav class="nav" aria-label="Dashboard">
                @foreach ($navigation as $group => $items)
                    @php
                        $items = array_values(array_filter(
                            $items,
                            static fn (array $item): bool => ($item['when'] ?? true) && Route::has($item['route'])
                        ));
                    @endphp
                    @continue($items === [])
                    <div class="nav-group">
                        <div class="nav-label">{{ $group }}</div>
                        @foreach ($items as $item)
                            @php $isActive = request()->routeIs(...$item['active']); @endphp
                            <a href="{{ route($item['route']) }}" @class(['active' => $isActive]) @if ($isActive) aria-current="page" @endif><span class="nav-ico">@include('larapilot::dashboard.partials.icon', ['name' => $item['icon']])</span>{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="sidebar-foot">
                <div class="theme-switch" role="group" aria-label="Theme">
                    <button type="button" data-theme-set="auto" aria-pressed="true" title="Match the system" aria-label="Match the system">
                        @include('larapilot::dashboard.partials.icon', ['name' => 'auto'])
                    </button>
                    <button type="button" data-theme-set="light" aria-pressed="false" title="Light" aria-label="Light theme">
                        @include('larapilot::dashboard.partials.icon', ['name' => 'sun'])
                    </button>
                    <button type="button" data-theme-set="dark" aria-pressed="false" title="Dark" aria-label="Dark theme">
                        @include('larapilot::dashboard.partials.icon', ['name' => 'moon'])
                    </button>
                </div>
                <span class="version">v{{ \Larapilot\LarapilotServiceProvider::VERSION }}</span>
            </div>
        </aside>

        <main class="main @yield('main-class')" id="main">
            @foreach (['larapilot_success' => 'success', 'larapilot_error' => 'error'] as $flashKey => $flashTone)
                @if (session($flashKey))
                    <div class="flash flash--{{ $flashTone }}" role="{{ $flashTone === 'error' ? 'alert' : 'status' }}">
                        <strong>{{ session($flashKey) }}</strong>
                        @if (is_array(session('larapilot_details')) && session('larapilot_details') !== [])
                            <ul>
                                @foreach (session('larapilot_details') as $flashDetail)
                                    <li>{{ $flashDetail }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            @endforeach

            @yield('content')

            <p class="footer-note">Local view of <code>.larapilot/</code> artifacts. Disabled in production.</p>
        </main>
    </div>

    <script>
        (function () {
            var root = document.documentElement;
            var KEY = 'larapilot-theme';
            var dark = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

            function saved() {
                try {
                    var value = localStorage.getItem(KEY);

                    return value === 'light' || value === 'dark' ? value : 'auto';
                } catch (error) {
                    return 'auto';
                }
            }

            function shown(mode) {
                return mode === 'auto' ? (dark && dark.matches ? 'dark' : 'light') : mode;
            }

            function paint(mode) {
                document.querySelectorAll('[data-theme-set]').forEach(function (button) {
                    button.setAttribute('aria-pressed', button.getAttribute('data-theme-set') === mode ? 'true' : 'false');
                });

                document.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
                    icon.hidden = icon.getAttribute('data-theme-icon') !== shown(mode);
                });
            }

            function apply(mode) {
                if (mode === 'auto') {
                    root.removeAttribute('data-theme');
                } else {
                    root.setAttribute('data-theme', mode);
                }

                try {
                    if (mode === 'auto') {
                        localStorage.removeItem(KEY);
                    } else {
                        localStorage.setItem(KEY, mode);
                    }
                } catch (error) {}

                paint(mode);
            }

            document.querySelectorAll('[data-theme-set]').forEach(function (button) {
                button.addEventListener('click', function () {
                    apply(button.getAttribute('data-theme-set'));
                });
            });

            document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    apply(shown(saved()) === 'dark' ? 'light' : 'dark');
                });
            });

            if (dark && dark.addEventListener) {
                dark.addEventListener('change', function () {
                    paint(saved());
                });
            }

            paint(saved());

            var opener = document.querySelector('[data-nav-open]');
            var sidebar = document.getElementById('sidebar');

            function setNav(open) {
                if (open) {
                    root.setAttribute('data-nav', 'open');
                } else {
                    root.removeAttribute('data-nav');
                }

                if (opener) {
                    opener.setAttribute('aria-expanded', open ? 'true' : 'false');
                }

                if (open && sidebar) {
                    var first = sidebar.querySelector('.nav a.active') || sidebar.querySelector('.nav a');

                    if (first) {
                        first.focus({ preventScroll: true });
                    }
                } else if (opener && opener.offsetParent !== null) {
                    opener.focus({ preventScroll: true });
                }
            }

            if (opener) {
                opener.addEventListener('click', function () {
                    setNav(true);
                });
            }

            document.querySelectorAll('[data-nav-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    setNav(false);
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && root.getAttribute('data-nav') === 'open') {
                    setNav(false);
                }
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
