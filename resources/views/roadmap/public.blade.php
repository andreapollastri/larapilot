{{--
    The product roadmap for whoever holds the public link. No dashboard,
    no project internals: versions and story titles only.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f4f6f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f1419" media="(prefers-color-scheme: dark)">
    <title>{{ $product }} — Roadmap</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6f7;
            --surface: #ffffff;
            --border: #e2e7eb;
            --text: #17212b;
            --text-2: #3b4957;
            --muted: #586776;
            --accent: #2e6f8e;
            --accent-soft: #e3eef3;
            --ok: #1f7356;
            --font: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --bg: #0f1419;
                --surface: #161c23;
                --border: #26303a;
                --text: #e7ecf0;
                --text-2: #c4cdd6;
                --muted: #8f9dab;
                --accent: #7fbad6;
                --accent-soft: #1a3140;
                --ok: #63c4a0;
            }
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: var(--font);
            font-size: 1rem;
            line-height: 1.55;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        .wrap { max-width: 760px; margin: 0 auto; padding: 40px 20px 64px; }
        h1 { margin: 0 0 8px; font-size: 1.85rem; letter-spacing: -0.02em; }
        h3 { margin: 0; font-size: 1.05rem; }
        h4 { margin: 2px 0 4px; font-size: 1.05rem; }
        .lead { margin: 0 0 28px; color: var(--text-2); }
        .hint { margin: 0; color: var(--muted); font-size: 0.88rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; }
        .roadmap-lanes { display: grid; gap: 28px; }
        .roadmap-lane-head { margin-bottom: 10px; }
        .roadmap-lane-head .hint { margin-top: 2px; }
        .roadmap-empty { margin: 0; color: var(--muted); }
        .roadmap-version { padding: 16px 18px; margin-bottom: 12px; }
        .roadmap-version-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px 16px; margin-bottom: 8px; }
        .roadmap-label { margin: 0; color: var(--accent); font-size: 0.72rem; font-weight: 650; letter-spacing: 0.06em; text-transform: uppercase; }
        .roadmap-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .chip { display: inline-flex; padding: 2px 8px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); font-size: 0.72rem; font-weight: 650; }
        .chip.live { background: color-mix(in srgb, var(--ok) 16%, transparent); color: var(--ok); }
        .roadmap-group { margin: 12px 0 4px; font-size: 0.82rem; color: var(--text-2); }
        .roadmap-items { margin: 0; padding: 0; list-style: none; }
        .roadmap-items li { padding: 8px 0; border-top: 1px solid var(--border); }
        .roadmap-items li .hint { display: block; margin-top: 2px; }
        footer { margin-top: 36px; color: var(--muted); font-size: 0.82rem; }
    </style>
</head>
<body>
    <main class="wrap">
        <h1>{{ $product }}</h1>
        <p class="lead">{{ $intro ?: 'What is shipping, what is next, and what is already out.' }}</p>

        @if ($empty)
            <p class="hint">The roadmap has no versions yet.</p>
        @else
            @include('larapilot::roadmap.lanes', ['lanes' => $lanes, 'team' => false])
        @endif

        <footer>Updated {{ $updated_at }}.</footer>
    </main>
</body>
</html>
