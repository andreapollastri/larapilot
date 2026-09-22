@extends('larapilot::dashboard.layout')

@section('title', 'Design')

@push('styles')
<style>
    body .shell:has(.design-page) {
        max-width: none;
        padding-left: max(20px, 3vw);
        padding-right: max(20px, 3vw);
    }

    .design-page { display: flex; flex-direction: column; gap: 18px; }

    .design-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }
    .design-top h2 { margin: 0 0 6px; font-size: 1.15rem; }
    .design-top .sub {
        margin: 0;
        color: var(--muted);
        font-size: 0.875rem;
        max-width: 80ch;
        line-height: 1.5;
    }
    .design-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 999px;
        border: 1px solid var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        font-family: inherit;
    }
    .btn:hover { text-decoration: none; }
    .btn.ghost {
        border-color: var(--border);
        background: var(--surface);
        color: var(--text);
    }
    .btn.is-disabled,
    .btn[disabled] {
        opacity: 0.45;
        pointer-events: none;
        border-color: var(--border);
        background: var(--surface);
        color: var(--muted);
    }

    .design-stage {
        display: grid;
        grid-template-columns: 280px minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }
    @media (max-width: 1100px) {
        .design-stage { grid-template-columns: minmax(0, 1fr); }
        /* stacked above the viewer: keep the index scrollable so it never
           pushes the screen itself below the fold */
        .design-toc { position: static !important; max-height: 46vh !important; }
    }

    /* index: one list, ordered, with the entry screen of each flow marked */
    .design-toc {
        padding: 14px 12px 18px;
        overflow: auto;
        max-height: calc(100vh - 140px);
        position: sticky;
        top: 16px;
    }
    .design-toc h3 {
        margin: 0 0 4px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        padding: 0 8px;
    }
    .design-toc .toc-lead {
        margin: 0 8px 12px;
        color: var(--muted);
        font-size: 0.75rem;
        line-height: 1.45;
    }
    .toc-list { list-style: none; margin: 0; padding: 0; }
    .toc-item,
    .toc-flow {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        text-align: left;
        padding: 8px 10px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font: inherit;
        font-size: 0.85rem;
        cursor: pointer;
    }
    .toc-item.is-overview {
        font-weight: 700;
        border: 1px solid var(--border);
        margin-bottom: 10px;
    }
    .toc-flow {
        font-weight: 600;
        font-size: 0.82rem;
        gap: 10px;
    }
    .toc-flow .toc-code {
        font-variant-numeric: tabular-nums;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--muted);
        flex: none;
    }
    .toc-flow.is-active .toc-code { color: inherit; }
    .toc-flow .toc-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }
    .toc-flow .toc-count {
        margin-left: auto;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--muted);
        flex: none;
    }
    .toc-screens { list-style: none; margin: 0 0 4px; padding: 0 0 0 10px; border-left: 1px solid var(--border); }
    .toc-screen { padding-left: 12px; font-size: 0.82rem; }
    .toc-item:hover,
    .toc-flow:hover,
    .toc-item.is-active,
    .toc-flow.is-active {
        background: var(--accent-soft);
        color: var(--accent);
    }
    .toc-badge {
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 1px 6px;
        border-radius: 999px;
        border: 1px solid var(--status-done);
        color: var(--status-done);
        white-space: nowrap;
        flex: none;
    }

    .design-viewer { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

    .design-frame-wrap { display: flex; flex-direction: column; overflow: hidden; }
    .design-frame-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        font-size: 0.82rem;
        flex-wrap: wrap;
    }
    .design-crumb { display: flex; flex-direction: column; min-width: 0; }
    .design-crumb .flow {
        color: var(--muted);
        font-size: 0.72rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .design-crumb .screen { font-weight: 600; }
    .design-nav { display: flex; align-items: center; gap: 8px; }
    .design-nav .counter { color: var(--muted); font-variant-numeric: tabular-nums; font-size: 0.78rem; }
    .design-nav .step {
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text);
        border-radius: 8px;
        width: 30px;
        height: 28px;
        font-size: 0.9rem;
        cursor: pointer;
        line-height: 1;
    }
    .design-nav .step:disabled { opacity: 0.4; cursor: default; }
    .design-frame {
        width: 100%;
        height: clamp(420px, 76vh, 900px);
        border: 0;
        background: #fff;
    }

    /* screen galleries: live previews, as many per row as the width allows */
    .screen-section { padding: 16px 18px 20px; }
    .screen-section > header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 10px 14px;
        flex-wrap: wrap;
        margin-bottom: 2px;
    }
    .screen-section h3 { margin: 0; font-size: 0.95rem; }
    .screen-section .hint { margin: 4px 0 14px; color: var(--muted); font-size: 0.8rem; }

    .screen-grid {
        --thumb-zoom: 0.32;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
        gap: 14px;
    }
    @media (min-width: 1600px) {
        .screen-grid { --thumb-zoom: 0.28; }
    }

    .screen-card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 0;
        margin: 0;
        text-align: left;
        font: inherit;
        color: inherit;
        text-decoration: none;
        cursor: pointer;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }
    .screen-card:hover {
        text-decoration: none;
        border-color: var(--accent);
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }
    .screen-card:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }
    .screen-card.is-active {
        border-color: var(--accent);
        box-shadow: 0 0 0 2px var(--accent-soft);
    }

    .screen-thumb {
        position: relative;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        background: #fff;
        border-bottom: 1px solid var(--border);
    }
    .screen-thumb iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: calc(100% / var(--thumb-zoom));
        height: calc(100% / var(--thumb-zoom));
        border: 0;
        pointer-events: none;
        transform: scale(var(--thumb-zoom));
        transform-origin: 0 0;
    }
    /* keeps the preview from swallowing clicks meant for the card */
    .screen-thumb::after {
        content: '';
        position: absolute;
        inset: 0;
    }

    .screen-meta {
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 10px 12px 12px;
        min-width: 0;
    }
    .screen-meta .row {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }
    .screen-code {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: var(--accent);
        flex: none;
    }
    .screen-name {
        font-size: 0.85rem;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .screen-flow {
        font-size: 0.75rem;
        color: var(--muted);
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .empty-card { padding: 40px 28px; text-align: center; }
    .empty-card h2 { margin: 0 0 8px; }
    .empty-card p { margin: 0 auto 12px; max-width: 62ch; color: var(--muted); }

    .design-flash {
        padding: 10px 14px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 4px;
    }
    .design-flash--success {
        border: 1px solid color-mix(in srgb, var(--status-done) 45%, var(--border));
        background: color-mix(in srgb, var(--status-done) 10%, var(--surface));
        color: #047857;
    }
    .design-flash--error {
        border: 1px solid color-mix(in srgb, #ef4444 45%, var(--border));
        background: color-mix(in srgb, #ef4444 8%, var(--surface));
        color: #b91c1c;
    }

    .style-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 10px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        background: color-mix(in srgb, var(--accent) 4%, var(--surface));
    }
    .style-bar[hidden] { display: none !important; }
    .style-bar .lead {
        font-size: 0.75rem;
        font-weight: 650;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--muted);
        margin-right: 4px;
    }
    .style-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text);
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
    }
    .style-chip:hover { border-color: var(--accent); color: var(--accent); }
    .style-chip.is-active {
        border-color: var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
    }
    .style-chip.is-chosen::after {
        content: '✓';
        font-size: 0.72rem;
        color: var(--status-done);
    }
    .style-bar .compare-toggle {
        margin-left: auto;
        padding: 5px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface);
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
    }
    .style-bar .compare-toggle[aria-pressed="true"] {
        border-color: var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
    }
    .style-choose {
        display: inline;
        margin: 0;
    }
    .style-choose .btn-mini {
        padding: 5px 10px;
        border-radius: 999px;
        border: 1px solid var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
        font-size: 0.72rem;
        font-weight: 650;
        cursor: pointer;
        font-family: inherit;
    }

    .compare-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
        gap: 12px;
        padding: 14px;
        border-top: 1px solid var(--border);
        background: color-mix(in srgb, var(--border) 25%, var(--surface));
    }
    .compare-grid[hidden] { display: none !important; }
    .compare-card {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: var(--surface);
    }
    .compare-card.is-chosen { border-color: var(--status-done); box-shadow: 0 0 0 1px color-mix(in srgb, var(--status-done) 35%, transparent); }
    .compare-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 10px;
        border-bottom: 1px solid var(--border);
        font-size: 0.78rem;
        font-weight: 650;
    }
    .compare-head .tag {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--status-done);
    }
    .compare-frame {
        width: 100%;
        height: 220px;
        border: 0;
        background: #fff;
    }
</style>
@endpush

@section('content')
    @php
        $catalog = is_array($catalog ?? null) ? $catalog : ['available' => false, 'items' => [], 'screen_count' => 0, 'spec_count' => 0];
        $items = is_array($catalog['items'] ?? null) ? $catalog['items'] : [];
        $available = (bool) ($catalog['available'] ?? false);
        $presentationUrl = $presentation_url ?? '';
        $packageUrl = $package_url ?? '';
        $projectTitle = $project_title ?? 'Larapilot';

        // Normalize the catalog once: every flow keeps its screens in walk
        // order, entry screen first.
        $flows = [];

        $normalizeScreens = static function (array $screens, ?string $entry): array {
            usort($screens, static function (array $a, array $b) use ($entry): int {
                $aEntry = ($a['file'] ?? null) === $entry ? 0 : 1;
                $bEntry = ($b['file'] ?? null) === $entry ? 0 : 1;

                return $aEntry <=> $bEntry;
            });

            $normalized = [];

            foreach ($screens as $screen) {
                if (empty($screen['url'])) {
                    continue;
                }

                $file = (string) ($screen['file'] ?? '');

                $normalized[] = [
                    'url' => (string) $screen['url'],
                    'label' => (string) ($screen['label'] ?? $file),
                    'file' => $file,
                    'slug' => pathinfo($file, PATHINFO_FILENAME),
                    'entry' => $file !== '' && $file === $entry,
                ];
            }

            return $normalized;
        };

        foreach ($items as $item) {
            $entry = $item['entry'] ?? null;
            $screens = is_array($item['screens'] ?? null) ? $item['screens'] : [];
            $normalized = $normalizeScreens($screens, is_string($entry) ? $entry : null);

            if ($normalized === []) {
                continue;
            }

            $code = (string) ($item['code'] ?? '');
            $title = (string) ($item['title'] ?? $code);
            $rawStyles = is_array($item['styles'] ?? null) ? $item['styles'] : [];
            $styleOptions = [];

            foreach ($rawStyles as $style) {
                if (! is_array($style) || empty($style['id']) || ($style['id'] ?? '') === 'current') {
                    continue;
                }

                $styleEntry = $style['entry'] ?? null;
                $styleScreens = $normalizeScreens(
                    is_array($style['screens'] ?? null) ? $style['screens'] : [],
                    is_string($styleEntry) ? $styleEntry : null
                );

                if ($styleScreens === []) {
                    continue;
                }

                $styleOptions[] = [
                    'id' => (string) $style['id'],
                    'label' => (string) ($style['label'] ?? $style['id']),
                    'chosen' => (bool) ($style['chosen'] ?? false),
                    'entry_url' => (string) ($style['entry_url'] ?? $styleScreens[0]['url']),
                    'screens' => $styleScreens,
                ];
            }

            $flows[] = [
                'code' => $code,
                'title' => $title,
                'label' => $code.' — '.$title,
                'entry_url' => (string) ($item['entry_url'] ?? $normalized[0]['url']),
                'screens' => $normalized,
                'styles' => $styleOptions,
                'has_variants' => count($styleOptions) > 1,
                'chosen_style' => $item['chosen_style'] ?? null,
            ];
        }

        // One ordered walk through the whole package: the index first, then
        // every flow, entry screen leading. Prev / next follow this order.
        $stops = [];

        if ($presentationUrl !== '') {
            $stops[] = [
                'url' => $presentationUrl,
                'flow' => 'Overview',
                'label' => 'Presentation index',
                'entry' => true,
            ];
        }

        $allScreens = [];

        foreach ($flows as $flow) {
            foreach ($flow['screens'] as $screen) {
                $stops[] = [
                    'url' => $screen['url'],
                    'flow' => $flow['label'],
                    'flow_code' => $flow['code'],
                    'label' => $screen['label'],
                    'slug' => $screen['slug'],
                    'entry' => $screen['entry'],
                ];

                $allScreens[] = $screen + ['code' => $flow['code'], 'flow_title' => $flow['title']];
            }
        }

        $flowStyles = [];

        foreach ($flows as $flow) {
            if (($flow['has_variants'] ?? false) && ($flow['styles'] ?? []) !== []) {
                $flowStyles[$flow['code']] = $flow['styles'];
            }
        }

        // Open on the first real mockup rather than the cover sheet — the
        // index stays reachable as stop one of the walk.
        $start = 0;

        foreach ($stops as $position => $stop) {
            if ($stop['flow'] !== 'Overview') {
                $start = $position;
                break;
            }
        }

        $startStop = $stops[$start] ?? null;
    @endphp

    <div class="design-page">
        @if (session('larapilot_success'))
            <p class="design-flash design-flash--success">{{ session('larapilot_success') }}</p>
        @endif
        @if (session('larapilot_error'))
            <p class="design-flash design-flash--error">{{ session('larapilot_error') }}</p>
        @endif

        <div class="design-top">
            <div>
                <h2>Design</h2>
                <p class="sub">
                    Every mockup screen for <strong>{{ $projectTitle }}</strong>, flow by flow
                    @if ($available)
                        — {{ $catalog['spec_count'] }} flow{{ $catalog['spec_count'] === 1 ? '' : 's' }},
                        {{ $catalog['screen_count'] }} screen{{ $catalog['screen_count'] === 1 ? '' : 's' }}
                    @endif
                    . Pick one from the gallery, step through them with the arrows, or compare style variants side by side and lock the one to implement.
                    Files live in <code>{{ $catalog['path'] ?? '.larapilot/mockups/' }}</code>.
                </p>
            </div>
            <div class="design-actions">
                @if ($presentationUrl && $available)
                    <a class="btn ghost" href="{{ $presentationUrl }}" target="_blank" rel="noopener noreferrer">Open index in new tab</a>
                @endif
                @if ($packageUrl && $available)
                    <a class="btn" href="{{ $packageUrl }}">Download zip</a>
                @else
                    <span class="btn is-disabled">Download zip</span>
                @endif
            </div>
        </div>

        @if (! $available)
            <section class="card empty-card">
                <h2>No designs yet</h2>
                <p>Run <code>/larapilot-design</code> to produce HTML mockups. They appear here as one navigable index: a presentation cover, then every screen of every flow in order.</p>
            </section>
        @else
            <div class="design-stage">
                <aside class="card design-toc" aria-label="Design index">
                    <h3>Index</h3>
                    <p class="toc-lead">Click a flow to open its first screen, or step through everything with the arrows in the viewer.</p>

                    @if ($presentationUrl)
                        <button type="button" class="toc-item is-overview" data-src="{{ $presentationUrl }}">
                            Presentation index
                            <span class="toc-badge">start</span>
                        </button>
                    @endif

                    <ul class="toc-list">
                        @foreach ($flows as $flow)
                            <li>
                                <button type="button" class="toc-flow" data-src="{{ $flow['entry_url'] }}" title="{{ $flow['label'] }}">
                                    <span class="toc-code">{{ $flow['code'] }}</span>
                                    <span class="toc-name">{{ $flow['title'] }}</span>
                                    @if (count($flow['screens']) > 1)
                                        <span class="toc-count">{{ count($flow['screens']) }}</span>
                                    @endif
                                </button>
                                @if (count($flow['screens']) > 1)
                                    <ul class="toc-screens">
                                        @foreach ($flow['screens'] as $screen)
                                            <li>
                                                <button type="button" class="toc-item toc-screen" data-src="{{ $screen['url'] }}">
                                                    {{ $screen['label'] }}
                                                    @if ($screen['entry'])
                                                        <span class="toc-badge">entry</span>
                                                    @endif
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </aside>

                <div class="design-viewer">
                    <section class="card design-frame-wrap">
                        <div class="design-frame-bar">
                            <div class="design-crumb">
                                <span class="flow" id="design-flow">{{ $startStop['flow'] ?? 'Overview' }}</span>
                                <span class="screen" id="design-caption">{{ $startStop['label'] ?? 'Presentation index' }}</span>
                            </div>
                            <div class="design-nav">
                                <span class="counter" id="design-counter"></span>
                                <button type="button" class="step" id="design-prev" title="Previous screen" aria-label="Previous screen">←</button>
                                <button type="button" class="step" id="design-next" title="Next screen" aria-label="Next screen">→</button>
                                <a class="btn ghost" id="design-open" href="{{ $startStop['url'] ?? $presentationUrl }}" target="_blank" rel="noopener noreferrer">Open</a>
                            </div>
                        </div>
                        <div class="style-bar" id="style-bar" hidden>
                            <span class="lead">Style</span>
                            <div id="style-chips"></div>
                            <button type="button" class="compare-toggle" id="compare-toggle" aria-pressed="false">Compare styles</button>
                        </div>
                        <iframe id="design-frame" class="design-frame" src="{{ $startStop['url'] ?? $presentationUrl }}" title="Design viewer"></iframe>
                        <div class="compare-grid" id="compare-grid" hidden></div>
                    </section>

                    <section class="card screen-section" id="flow-gallery" hidden>
                        <header>
                            <h3 id="flow-gallery-title"></h3>
                        </header>
                        <p class="hint">Screens in this flow. Click one to open it in the viewer above.</p>
                        <div class="screen-grid" id="flow-gallery-grid"></div>
                    </section>
                </div>
            </div>

            <section class="card screen-section all-screens">
                <header>
                    <h3>All {{ $catalog['screen_count'] }} screens, flow by flow</h3>
                    <span class="hint" style="margin: 0;">Live previews — click one to open it in the viewer above.</span>
                </header>
                <div class="screen-grid">
                    @foreach ($allScreens as $screen)
                        <a class="screen-card" href="{{ $screen['url'] }}" data-src="{{ $screen['url'] }}">
                            <span class="screen-thumb">
                                <iframe src="{{ $screen['url'] }}" loading="lazy" title="{{ $screen['code'] }} — {{ $screen['label'] }}" tabindex="-1" aria-hidden="true" scrolling="no"></iframe>
                            </span>
                            <span class="screen-meta">
                                <span class="row">
                                    <span class="screen-code">{{ $screen['code'] }}</span>
                                    <span class="screen-name">{{ $screen['label'] }}</span>
                                    @if ($screen['entry'])
                                        <span class="toc-badge" style="margin-left: auto;">entry</span>
                                    @endif
                                </span>
                                <span class="screen-flow">{{ $screen['flow_title'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const frame = document.getElementById('design-frame');
        if (!frame) return;

        const stops = @json(array_values($stops));
        const flowStyles = @json($flowStyles);
        const styleChooseUrl = @json(route('larapilot.dashboard.design.style', ['code' => '__CODE__']));
        const csrf = @json(csrf_token());
        const start = @json($start);
        const caption = document.getElementById('design-caption');
        const flowLabel = document.getElementById('design-flow');
        const counter = document.getElementById('design-counter');
        const openLink = document.getElementById('design-open');
        const prev = document.getElementById('design-prev');
        const next = document.getElementById('design-next');
        const gallery = document.getElementById('flow-gallery');
        const galleryTitle = document.getElementById('flow-gallery-title');
        const galleryGrid = document.getElementById('flow-gallery-grid');
        const styleBar = document.getElementById('style-bar');
        const styleChips = document.getElementById('style-chips');
        const compareToggle = document.getElementById('compare-toggle');
        const compareGrid = document.getElementById('compare-grid');
        let current = start;
        let compareOpen = false;
        let activeStyle = {};

        const indexOf = (src) => stops.findIndex((stop) => stop.url === src);

        const card = (item, active) => {
            const node = document.createElement('a');
            node.className = 'screen-card' + (active ? ' is-active' : '');
            node.href = item.url;
            node.dataset.src = item.url;

            const thumb = document.createElement('span');
            thumb.className = 'screen-thumb';
            const preview = document.createElement('iframe');
            preview.src = item.url;
            preview.loading = 'lazy';
            preview.tabIndex = -1;
            preview.setAttribute('aria-hidden', 'true');
            preview.setAttribute('scrolling', 'no');
            preview.title = item.label;
            thumb.appendChild(preview);

            const meta = document.createElement('span');
            meta.className = 'screen-meta';
            const row = document.createElement('span');
            row.className = 'row';
            const name = document.createElement('span');
            name.className = 'screen-name';
            name.textContent = item.label;
            row.appendChild(name);

            if (item.entry) {
                const badge = document.createElement('span');
                badge.className = 'toc-badge';
                badge.style.marginLeft = 'auto';
                badge.textContent = 'entry';
                row.appendChild(badge);
            }

            meta.appendChild(row);
            node.appendChild(thumb);
            node.appendChild(meta);

            return node;
        };

        const urlForStyle = (styles, slug, fallback) => {
            for (const style of styles) {
                const match = (style.screens || []).find((screen) => screen.slug === slug);
                if (match) return match.url;
            }
            return fallback;
        };

        const renderStyleBar = (stop) => {
            if (!styleBar || !styleChips) return;

            const styles = flowStyles[stop.flow_code] || [];

            if (styles.length < 2 || stop.flow === 'Overview') {
                styleBar.hidden = true;
                styleChips.innerHTML = '';
                if (compareGrid) compareGrid.hidden = true;
                return;
            }

            styleBar.hidden = false;
            const styleId = activeStyle[stop.flow_code] || styles.find((s) => s.chosen)?.id || styles[0].id;
            activeStyle[stop.flow_code] = styleId;
            styleChips.innerHTML = '';

            styles.forEach((style) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'style-chip' + (style.id === styleId ? ' is-active' : '') + (style.chosen ? ' is-chosen' : '');
                chip.textContent = style.label;
                chip.addEventListener('click', () => {
                    activeStyle[stop.flow_code] = style.id;
                    const url = urlForStyle([style], stop.slug, style.entry_url);
                    frame.src = url;
                    renderStyleBar(stop);
                    if (compareOpen) renderCompare(stop);
                });
                styleChips.appendChild(chip);
            });

            if (compareOpen) renderCompare(stop);
        };

        const renderCompare = (stop) => {
            if (!compareGrid) return;

            const styles = flowStyles[stop.flow_code] || [];
            compareGrid.innerHTML = '';

            if (styles.length < 2) {
                compareGrid.hidden = true;
                return;
            }

            compareGrid.hidden = false;

            styles.forEach((style) => {
                const url = urlForStyle([style], stop.slug, style.entry_url);
                const card = document.createElement('article');
                card.className = 'compare-card' + (style.chosen ? ' is-chosen' : '');

                const head = document.createElement('div');
                head.className = 'compare-head';
                head.innerHTML = '<span>' + style.label + '</span>';
                if (style.chosen) {
                    const tag = document.createElement('span');
                    tag.className = 'tag';
                    tag.textContent = 'Chosen';
                    head.appendChild(tag);
                } else {
                    const form = document.createElement('form');
                    form.className = 'style-choose';
                    form.method = 'post';
                    form.action = styleChooseUrl.replace('__CODE__', stop.flow_code);
                    form.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">'
                        + '<input type="hidden" name="style" value="' + style.id + '">'
                        + '<button type="submit" class="btn-mini">Use this style</button>';
                    head.appendChild(form);
                }

                const preview = document.createElement('iframe');
                preview.className = 'compare-frame';
                preview.src = url;
                preview.loading = 'lazy';
                preview.title = style.label;

                card.appendChild(head);
                card.appendChild(preview);
                compareGrid.appendChild(card);
            });
        };

        if (compareToggle) {
            compareToggle.addEventListener('click', () => {
                compareOpen = !compareOpen;
                compareToggle.setAttribute('aria-pressed', compareOpen ? 'true' : 'false');
                const stop = stops[current];
                if (compareOpen && stop) renderCompare(stop);
                else if (compareGrid) compareGrid.hidden = true;
            });
        }

        const renderGallery = (stop) => {
            if (!gallery || !galleryGrid) return;

            const siblings = stops.filter((item) => item.flow === stop.flow && item.flow !== 'Overview');

            if (stop.flow === 'Overview' || siblings.length < 2) {
                gallery.hidden = true;
                galleryGrid.innerHTML = '';
                return;
            }

            if (galleryTitle) galleryTitle.textContent = stop.flow;
            galleryGrid.innerHTML = '';
            siblings.forEach((item) => galleryGrid.appendChild(card(item, item.url === stop.url)));
            gallery.hidden = false;
        };

        const paint = (position) => {
            const stop = stops[position];
            if (!stop) return;

            current = position;
            if (caption) caption.textContent = stop.label;
            if (flowLabel) flowLabel.textContent = stop.flow;
            if (counter) counter.textContent = (position + 1) + ' / ' + stops.length;
            if (openLink) openLink.href = stop.url;
            if (prev) prev.disabled = position === 0;
            if (next) next.disabled = position === stops.length - 1;

            document.querySelectorAll('.toc-item, .toc-flow, .all-screens .screen-card').forEach((el) => {
                el.classList.toggle('is-active', el.dataset.src === stop.url);
            });

            renderGallery(stop);
            renderStyleBar(stop);
        };

        const go = (position) => {
            if (position < 0 || position >= stops.length) return;
            frame.src = stops[position].url;
            paint(position);
            frame.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };

        // One delegated handler covers the index, the full gallery, and the
        // flow gallery cards built on the fly.
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target.closest('[data-src]') : null;
            if (!target) return;

            const position = indexOf(target.dataset.src);
            if (position < 0) return;

            event.preventDefault();
            go(position);
        });

        if (prev) prev.addEventListener('click', () => go(current - 1));
        if (next) next.addEventListener('click', () => go(current + 1));

        document.addEventListener('keydown', (event) => {
            if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return;
            if (event.key === 'ArrowLeft') go(current - 1);
            if (event.key === 'ArrowRight') go(current + 1);
        });

        // Links inside the index (and inside the mockups) navigate the iframe
        // itself — keep the breadcrumb, index, and arrows in step with it.
        frame.addEventListener('load', () => {
            let path = null;

            try {
                path = frame.contentWindow.location.pathname + frame.contentWindow.location.search;
            } catch (error) {
                return;
            }

            const position = stops.findIndex((stop) => stop.url === path || stop.url === decodeURIComponent(path));
            if (position >= 0 && position !== current) paint(position);
        });

        paint(start);
    })();
</script>
@endpush
