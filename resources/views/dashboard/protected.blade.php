{{--
    Shown in place of the dashboard, or of the JSON API, when a project setting
    protects the area and the one thing that opens it is still missing: a first
    dashboard user (`dashboard_auth`) or the API token (`api_auth`).

    Stands on its own — no sidebar, no project data — because whoever reads it
    has not signed in.
--}}
@php
    $isApi = ($area ?? 'dashboard') === 'api';
    $heading = $isApi ? 'This API is protected' : 'This dashboard is protected';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#f4f6f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f1419" media="(prefers-color-scheme: dark)">
    <title>{{ $heading }} — Larapilot</title>
    <script>
        // The theme saved on the dashboard, before the first paint.
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
            --surface-3: #edf1f4;
            --border: #e2e7eb;
            --text: #17212b;
            --text-2: #3b4957;
            --muted: #586776;
            --accent: #2e6f8e;
            --accent-soft: #e3eef3;
            --accent-contrast: #ffffff;
            --shadow: 0 1px 2px rgba(23, 33, 43, 0.04);
            --font: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Monaco, Consolas, monospace;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                color-scheme: dark;
                --bg: #0f1419;
                --surface: #161c23;
                --surface-3: #222c36;
                --border: #26303a;
                --text: #e7ecf0;
                --text-2: #c4cdd6;
                --muted: #8f9dab;
                --accent: #7fbad6;
                --accent-soft: #1a3140;
                --accent-contrast: #0f1419;
                --shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            }
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --bg: #0f1419;
            --surface: #161c23;
            --surface-3: #222c36;
            --border: #26303a;
            --text: #e7ecf0;
            --text-2: #c4cdd6;
            --muted: #8f9dab;
            --accent: #7fbad6;
            --accent-soft: #1a3140;
            --accent-contrast: #0f1419;
            --shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            /* A track that may shrink: the command scrolls in its box, the page does not. */
            grid-template-columns: minmax(0, 580px);
            justify-content: center;
            align-content: center;
            padding: 24px 16px;
            font-family: var(--font);
            font-size: 0.9375rem;
            line-height: 1.6;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; text-underline-offset: 3px; }

        :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 4px; }

        code, pre { font-family: var(--mono); }

        code {
            font-size: 0.86em;
            padding: 0.1em 0.4em;
            border-radius: 5px;
            background: var(--surface-3);
            color: var(--text-2);
            overflow-wrap: anywhere;
        }

        .gate { min-width: 0; }

        .brand { display: flex; align-items: center; gap: 11px; margin-bottom: 18px; }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--accent);
            color: var(--accent-contrast);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .brand-name { font-size: 0.98rem; font-weight: 650; letter-spacing: -0.01em; }

        .card {
            padding: 22px 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
        }

        @media (min-width: 640px) {
            .card { padding: 30px 32px; }
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin: 0 0 14px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.7rem;
            font-weight: 650;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .eyebrow svg { width: 13px; height: 13px; flex: none; }

        h1 { margin: 0 0 10px; font-size: 1.45rem; font-weight: 650; letter-spacing: -0.015em; line-height: 1.25; }

        h2 { margin: 24px 0 8px; font-size: 0.98rem; font-weight: 650; letter-spacing: -0.01em; }

        p { margin: 0 0 12px; color: var(--text-2); }

        pre {
            margin: 0 0 12px;
            padding: 12px 14px;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface-3);
            font-size: 0.84rem;
            line-height: 1.5;
        }

        pre code { padding: 0; background: none; color: var(--text); font-size: inherit; overflow-wrap: normal; }

        .aside {
            margin: 22px 0 0;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 0.86rem;
        }

        .foot { margin: 16px 4px 0; color: var(--muted); font-size: 0.8rem; }
    </style>
</head>
<body>
    <main class="gate">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">LP</span>
            <span class="brand-name">Larapilot</span>
        </div>

        <section class="card">
            <p class="eyebrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                Protected area
            </p>

            <h1>{{ $heading }}</h1>

            @if ($isApi)
                <p>
                    This project asks for a token on every call to the Larapilot API — the <code>api_auth</code> setting is
                    <strong>ON</strong> — and no token has been set on this server yet. Until there is one, the API
                    stays closed rather than answer anyone.
                </p>

                <h2>Add the token to open it</h2>
                <p>Put a long random value in the <code>.env</code> of this server:</p>
                <pre><code>LARAPILOT_API_TOKEN=your-long-random-string</code></pre>
                <p>
                    Then send it on every request, as <code>Authorization: Bearer &lt;token&gt;</code> or in the
                    <code>X-Larapilot-Token</code> header. If the configuration is cached, run
                    <code>php artisan config:cache</code> again.
                </p>

                <p class="aside">
                    The token is not needed here? Lift the requirement with
                    <code>php artisan larapilot:settings-set --api-auth=NO</code>.
                </p>
            @else
                <p>
                    This project asks for a sign-in on the Larapilot dashboard — the <code>dashboard_auth</code> setting
                    is <strong>ON</strong> — and no user has been created yet. Until there is one, the dashboard stays
                    closed rather than open to anyone.
                </p>

                <h2>Create the first user to open it</h2>
                <p>Run this in the project, with the username you want:</p>
                <pre><code>php artisan larapilot:dashboard-user add &lt;username&gt;</code></pre>
                <p>
                    It asks for a password and keeps only its hash in <code>.larapilot/auth.yaml</code>, out of Git.
                    Reload this page afterwards and sign in with the new credentials.
                </p>

                <p class="aside">
                    The sign-in is not needed here? Turn it off with
                    <code>php artisan larapilot:settings-set --dashboard-auth=NO</code>.
                </p>
            @endif
        </section>

        <p class="foot">
            Nothing is broken: the area opens as soon as the step above is done.
            @if ($isApi && Route::has('larapilot.dashboard.index'))
                <a href="{{ route('larapilot.dashboard.index') }}">Back to the dashboard</a>
            @endif
        </p>
    </main>
</body>
</html>
