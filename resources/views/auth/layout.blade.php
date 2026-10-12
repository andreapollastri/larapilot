{{--
    The door of the dashboard: the sign-in, the code of the authenticator,
    its enrolment. Stands on its own — no sidebar, no project data — because
    whoever reads it is not in yet.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#f4f6f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f1419" media="(prefers-color-scheme: dark)">
    <title>@yield('title', 'Sign in') — Larapilot</title>
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
            --accent-strong: #255b75;
            --accent-soft: #e3eef3;
            --accent-contrast: #ffffff;
            --danger: #b3261e;
            --danger-soft: #fbe9e7;
            --ok: #1f7a4d;
            --ok-soft: #e4f3ea;
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
                --accent-strong: #9accdf;
                --accent-soft: #1a3140;
                --accent-contrast: #0f1419;
                --danger: #f28b82;
                --danger-soft: #3a1f1d;
                --ok: #7dd3a0;
                --ok-soft: #1a3328;
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
            --accent-strong: #9accdf;
            --accent-soft: #1a3140;
            --accent-contrast: #0f1419;
            --danger: #f28b82;
            --danger-soft: #3a1f1d;
            --ok: #7dd3a0;
            --ok-soft: #1a3328;
            --shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-columns: minmax(0, 460px);
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

        body.wide { grid-template-columns: minmax(0, 560px); }

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

        form { display: grid; gap: 14px; margin-top: 18px; }

        .field { display: grid; gap: 6px; }

        .field > span { font-size: 0.82rem; font-weight: 600; color: var(--text-2); }

        .control {
            width: 100%;
            min-height: 44px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg);
            color: var(--text);
            font: inherit;
        }

        .control:focus { outline: 2px solid var(--accent); outline-offset: 0; border-color: transparent; }

        .control.otp {
            font-family: var(--mono);
            font-size: 1.5rem;
            letter-spacing: 0.35em;
            text-align: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid var(--accent);
            border-radius: 10px;
            background: var(--accent);
            color: var(--accent-contrast);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .btn:hover { background: var(--accent-strong); border-color: var(--accent-strong); text-decoration: none; }

        .btn.ghost { background: transparent; color: var(--text-2); border-color: var(--border); }
        .btn.ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--surface); }

        .link-btn {
            padding: 0;
            border: 0;
            background: none;
            color: var(--accent);
            font: inherit;
            cursor: pointer;
        }

        .link-btn:hover { text-decoration: underline; text-underline-offset: 3px; }

        .alert {
            margin: 0 0 4px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            color: var(--text);
        }

        .alert.error { background: var(--danger-soft); color: var(--danger); }
        .alert.ok { background: var(--ok-soft); color: var(--ok); }

        .aside {
            margin: 22px 0 0;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 0.86rem;
        }

        .aside p { color: inherit; margin: 0 0 8px; }
        .aside p:last-child { margin-bottom: 0; }

        .foot { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 16px 4px 0; color: var(--muted); font-size: 0.8rem; }
        .foot form { display: inline; margin: 0; }

        /* The enrolment of an authenticator. */
        .totp-steps { margin: 0 0 8px; padding-left: 1.2em; color: var(--text-2); }
        .totp-steps li { margin-bottom: 6px; }

        .totp-pair { display: grid; gap: 16px; align-items: start; margin: 14px 0 4px; }

        @media (min-width: 560px) { .totp-pair { grid-template-columns: auto minmax(0, 1fr); } }

        .totp-qr {
            display: grid;
            place-items: center;
            padding: 10px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            width: fit-content;
        }

        .totp-qr svg { display: block; width: 180px; height: 180px; }

        .totp-secret { min-width: 0; }
        .totp-secret p { margin: 0 0 6px; font-size: 0.86rem; }

        .totp-secret code {
            display: block;
            padding: 8px 10px;
            font-size: 0.95rem;
            letter-spacing: 0.06em;
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body class="@yield('body-class')">
    <main class="gate">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">LP</span>
            <span class="brand-name">Larapilot</span>
        </div>

        <section class="card">
            @yield('content')
        </section>

        <div class="foot">
            @yield('foot')
        </div>
    </main>
</body>
</html>
