@extends('larapilot::dashboard.layout')

@section('title', 'Design')

@section('main-class', 'is-wide')

@push('styles')
<style>
    .design-page { display: flex; flex-direction: column; gap: 18px; }
    .design-page .page-head { margin-bottom: 0; }

    /* two views of the same page: every screen, or one of them opened */
    .design-page[data-view="viewer"] .design-gallery,
    .design-page[data-view="viewer"] .page-head,
    .design-page[data-view="gallery"] .design-viewer { display: none; }

    .design-gallery { display: flex; flex-direction: column; gap: 18px; }

    .gallery-tools {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 10px 16px;
        flex-wrap: wrap;
    }

    .gallery-tools .field { flex: 1 1 260px; max-width: 420px; }
    .gallery-count { margin: 0; color: var(--muted); font-size: 0.84rem; font-variant-numeric: tabular-nums; }

    .screen-section { padding: 18px 16px 20px; }

    @media (min-width: 640px) {
        .screen-section { padding: 20px 22px 24px; }
    }

    .screen-section[hidden] { display: none; }

    .screen-section > header {
        display: flex;
        align-items: center;
        gap: 8px 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .screen-section h3 { margin: 0; font-size: 1.02rem; overflow-wrap: anywhere; }
    .screen-section .hint { margin: 0; }
    .screen-section > header .hint { margin-left: auto; }

    .flow-code {
        flex: none;
        padding: 2px 9px;
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent-strong);
        font-family: var(--mono);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .screen-grid {
        --thumb-zoom: 0.27;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 290px), 1fr));
        gap: 16px;
    }

    .screen-card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 0;
        margin: 0;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        color: inherit;
        font: inherit;
        text-align: left;
        text-decoration: none;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }

    .screen-card[hidden] { display: none; }

    .screen-card:hover {
        text-decoration: none;
        border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
        box-shadow: 0 8px 22px color-mix(in srgb, var(--text) 9%, transparent);
        transform: translateY(-2px);
    }

    .screen-card.is-active {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
    }

    .screen-thumb {
        position: relative;
        aspect-ratio: 16 / 10;
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

    .screen-open {
        position: absolute;
        right: 10px;
        bottom: 10px;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 11px;
        border-radius: 999px;
        background: var(--accent);
        color: var(--accent-contrast);
        font-size: 0.74rem;
        font-weight: 600;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity 0.15s ease, transform 0.15s ease;
    }

    .screen-open .icon { width: 13px; height: 13px; }

    .screen-card:hover .screen-open,
    .screen-card:focus-visible .screen-open { opacity: 1; transform: none; }

    .screen-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 11px 13px 12px;
        min-width: 0;
    }

    .screen-name {
        min-width: 0;
        font-size: 0.88rem;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .screen-file {
        flex: none;
        margin-left: auto;
        color: var(--muted);
        font-family: var(--mono);
        font-size: 0.7rem;
    }

    .toc-badge {
        flex: none;
        padding: 1px 7px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--ok-fill) 14%, transparent);
        color: var(--ok);
        font-size: 0.62rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .gallery-empty { padding: 36px 20px; text-align: center; color: var(--muted); }
    .gallery-empty[hidden] { display: none; }

    .empty-card { padding: 48px 24px; text-align: center; }
    .empty-card h2 { margin: 0 0 8px; font-size: 1.2rem; }
    .empty-card p { margin: 0 auto; max-width: 62ch; color: var(--muted); }

    /* ---- one screen, opened: the mockup itself, as wide as the window ---- */
    .design-viewer { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .design-frame-wrap { display: flex; flex-direction: column; overflow: hidden; }

    .design-frame-bar {
        display: flex;
        align-items: center;
        gap: 10px 14px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        font-size: 0.84rem;
        flex-wrap: wrap;
    }

    .design-crumb { display: flex; flex-direction: column; min-width: 0; flex: 1 1 180px; }

    .design-crumb .flow {
        color: var(--muted);
        font-size: 0.72rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .design-crumb .screen { font-weight: 600; overflow-wrap: anywhere; }

    .design-nav { display: flex; align-items: center; gap: 6px; }

    .design-nav .counter {
        margin-right: 4px;
        color: var(--muted);
        font-size: 0.78rem;
        font-variant-numeric: tabular-nums;
    }

    .design-nav .step {
        display: inline-grid;
        place-items: center;
        width: 36px;
        height: 36px;
        padding: 0;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-sm);
        background: var(--surface);
        color: var(--text);
        cursor: pointer;
    }

    .design-nav .step .icon { width: 16px; height: 16px; }
    .design-nav .step:hover { border-color: var(--accent); color: var(--accent); }
    .design-nav .step:disabled { opacity: 0.4; cursor: default; border-color: var(--border); color: var(--muted); }

    .design-frame {
        width: 100%;
        height: max(480px, calc(100vh - 190px));
        border: 0;
        background: #fff;
    }

    .style-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 10px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        background: var(--surface-2);
    }

    .style-bar[hidden] { display: none !important; }

    .style-bar .lead {
        margin-right: 4px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    #style-chips { display: flex; flex-wrap: wrap; gap: 6px; }

    .style-chip,
    .style-bar .compare-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 32px;
        padding: 0 13px;
        border: 1px solid var(--border-strong);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text);
        font-family: inherit;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
    }

    .style-chip:hover,
    .style-bar .compare-toggle:hover { border-color: var(--accent); color: var(--accent); }

    .style-chip.is-active,
    .style-bar .compare-toggle[aria-pressed="true"] {
        border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
        background: var(--accent-soft);
        color: var(--accent-strong);
    }

    .style-chip.is-chosen::after {
        content: '✓';
        color: var(--ok);
        font-size: 0.74rem;
    }

    .style-bar .compare-toggle { margin-left: auto; }

    .style-choose {
        display: inline;
        margin: 0;
    }

    .style-choose .btn-mini {
        min-height: 28px;
        padding: 0 11px;
        border: 1px solid var(--accent);
        border-radius: var(--radius-xs);
        background: var(--accent);
        color: var(--accent-contrast);
        font-family: inherit;
        font-size: 0.74rem;
        font-weight: 600;
        cursor: pointer;
    }

    .style-choose .btn-mini:hover { background: var(--accent-strong); border-color: var(--accent-strong); }

    .compare-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
        gap: 12px;
        padding: 14px;
        border-top: 1px solid var(--border);
        background: var(--surface-2);
    }

    .compare-grid[hidden] { display: none !important; }

    .compare-card {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        overflow: hidden;
        background: var(--surface);
    }

    .compare-card.is-chosen {
        border-color: var(--ok-fill);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--ok-fill) 20%, transparent);
    }

    .compare-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        min-height: 44px;
        padding: 6px 12px;
        border-bottom: 1px solid var(--border);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .compare-head .tag {
        color: var(--ok);
        font-size: 0.66rem;
        font-weight: 650;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .compare-frame {
        width: 100%;
        height: 240px;
        border: 0;
        background: #fff;
    }

    #flow-gallery .screen-grid {
        --thumb-zoom: 0.2;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 210px), 1fr));
        gap: 12px;
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
            $title = trim((string) ($item['title'] ?? ''));
            // A folder that is not a spec has no title of its own: say its name once.
            $title = $title === $code ? '' : $title;
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
                'label' => $title !== '' ? $code.' — '.$title : $code,
                'entry_url' => (string) ($item['entry_url'] ?? $normalized[0]['url']),
                'screens' => $normalized,
                'styles' => $styleOptions,
                'has_variants' => count($styleOptions) > 1,
                'chosen_style' => $item['chosen_style'] ?? null,
            ];
        }

        // One ordered walk through every screen, flow by flow, entry screen
        // leading. The arrows of the viewer follow this order.
        $stops = [];

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
            }
        }

        $flowStyles = [];

        foreach ($flows as $flow) {
            if (($flow['has_variants'] ?? false) && ($flow['styles'] ?? []) !== []) {
                $flowStyles[$flow['code']] = $flow['styles'];
            }
        }

        $screenTotal = count($stops);
        $plural = static fn (int $count, string $word): string => $count.' '.$word.($count === 1 ? '' : 's');
    @endphp

    <div class="design-page" id="design-page" data-view="gallery">
        <header class="page-head">
            <div>
                <h2>Design</h2>
                <p class="sub">
                    Every mockup screen for <strong>{{ $projectTitle }}</strong>@if ($available) — {{ $plural((int) $catalog['spec_count'], 'flow') }}, {{ $plural((int) $catalog['screen_count'], 'screen') }}@endif.
                    Click a screen to open it as a site you can browse, then come back here.
                    Files live in <code>{{ $catalog['path'] ?? '.larapilot/mockups/' }}</code>.
                </p>
            </div>
            <div class="page-actions design-actions">
                @if ($presentationUrl && $available)
                    <a class="btn ghost" href="{{ $presentationUrl }}" target="_blank" rel="noopener noreferrer" title="Presentation index — the cover page that ships in the zip">@include('larapilot::dashboard.partials.icon', ['name' => 'external'])Open index in new tab</a>
                @endif
                @if ($packageUrl && $available)
                    <a class="btn" href="{{ $packageUrl }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download zip</a>
                @else
                    <span class="btn is-disabled">Download zip</span>
                @endif
            </div>
        </header>

        @if (! $available)
            <section class="card empty-card">
                <h2>No designs yet</h2>
                <p>Run <code>/larapilot-design</code> to produce HTML mockups. Every screen appears here as a preview you can click to browse the mockup like a site.</p>
            </section>
        @else
            {{-- FIRST: every screen, clickable --}}
            <div class="design-gallery" id="design-gallery">
                <div class="gallery-tools">
                    <label class="field">
                        Find a screen
                        <input type="search" id="gallery-filter" placeholder="Screen or flow name…" autocomplete="off">
                    </label>
                    <p class="gallery-count" id="gallery-count" data-total="{{ $screenTotal }}">All {{ $plural($screenTotal, 'screen') }}, flow by flow</p>
                </div>

                @foreach ($flows as $flow)
                    <section class="card screen-section" data-flow="{{ $flow['code'] }}" aria-label="{{ $flow['label'] }}">
                        <header>
                            <span class="flow-code">{{ $flow['code'] }}</span>
                            @if ($flow['title'] !== '')
                                <h3>{{ $flow['title'] }}</h3>
                            @endif
                            @if ($flow['has_variants'])
                                <span class="chip">{{ count($flow['styles']) }} styles</span>
                            @endif
                            <span class="hint">{{ $plural(count($flow['screens']), 'screen') }}</span>
                        </header>
                        <div class="screen-grid">
                            @foreach ($flow['screens'] as $screen)
                                <a
                                    class="screen-card"
                                    href="{{ $screen['url'] }}"
                                    data-src="{{ $screen['url'] }}"
                                    data-find="{{ strtolower($flow['label'].' '.$screen['label'].' '.$screen['file']) }}"
                                    title="Open {{ $screen['label'] }}"
                                >
                                    <span class="screen-thumb">
                                        <iframe src="{{ $screen['url'] }}" loading="lazy" title="{{ $flow['code'] }} — {{ $screen['label'] }}" tabindex="-1" aria-hidden="true" scrolling="no"></iframe>
                                        <span class="screen-open">Open @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</span>
                                    </span>
                                    <span class="screen-meta">
                                        <span class="screen-name">{{ $screen['label'] }}</span>
                                        @if ($screen['entry'])
                                            <span class="toc-badge">entry</span>
                                        @endif
                                        <span class="screen-file">{{ $screen['file'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <p class="card gallery-empty" id="gallery-empty" hidden>No screen matches. Try part of its name, or the code of its flow.</p>
            </div>

            {{-- THEN: the screen that was clicked, as a site to browse --}}
            <div class="design-viewer" id="design-viewer">
                <section class="card design-frame-wrap">
                    <div class="design-frame-bar">
                        <button type="button" class="btn ghost" id="design-back">@include('larapilot::dashboard.partials.icon', ['name' => 'back'])All screens</button>
                        <div class="design-crumb">
                            <span class="flow" id="design-flow"></span>
                            <span class="screen" id="design-caption"></span>
                        </div>
                        <div class="design-nav">
                            <span class="counter" id="design-counter"></span>
                            <button type="button" class="step" id="design-prev" title="Previous screen (←)" aria-label="Previous screen">@include('larapilot::dashboard.partials.icon', ['name' => 'chevron-left'])</button>
                            <button type="button" class="step" id="design-next" title="Next screen (→)" aria-label="Next screen">@include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</button>
                            <a class="btn ghost small" id="design-open" href="#" target="_blank" rel="noopener noreferrer">@include('larapilot::dashboard.partials.icon', ['name' => 'external'])New tab</a>
                        </div>
                    </div>
                    <div class="style-bar" id="style-bar" hidden>
                        <span class="lead">Style</span>
                        <div id="style-chips"></div>
                        <button type="button" class="compare-toggle" id="compare-toggle" aria-pressed="false">Compare styles</button>
                    </div>
                    <iframe id="design-frame" class="design-frame" title="Design viewer"></iframe>
                    <div class="compare-grid" id="compare-grid" hidden></div>
                </section>

                <section class="card screen-section" id="flow-gallery" hidden>
                    <header>
                        <h3 id="flow-gallery-title"></h3>
                        <span class="hint">The other screens of this flow</span>
                    </header>
                    <div class="screen-grid" id="flow-gallery-grid"></div>
                </section>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const page = document.getElementById('design-page');
        const frame = document.getElementById('design-frame');
        if (!page || !frame) return;

        const stops = @json(array_values($stops));
        const flowStyles = @json($flowStyles);
        const styleChooseUrl = @json(route('larapilot.dashboard.design.style', ['code' => '__CODE__']));
        const csrf = @json(csrf_token());
        const caption = document.getElementById('design-caption');
        const flowLabel = document.getElementById('design-flow');
        const counter = document.getElementById('design-counter');
        const openLink = document.getElementById('design-open');
        const back = document.getElementById('design-back');
        const prev = document.getElementById('design-prev');
        const next = document.getElementById('design-next');
        const gallery = document.getElementById('flow-gallery');
        const galleryTitle = document.getElementById('flow-gallery-title');
        const galleryGrid = document.getElementById('flow-gallery-grid');
        const styleBar = document.getElementById('style-bar');
        const styleChips = document.getElementById('style-chips');
        const compareToggle = document.getElementById('compare-toggle');
        const compareGrid = document.getElementById('compare-grid');
        const filter = document.getElementById('gallery-filter');
        const count = document.getElementById('gallery-count');
        const noMatch = document.getElementById('gallery-empty');
        const PARAM = 'screen';
        let current = -1;
        let compareOpen = false;
        let activeStyle = {};
        let galleryScroll = 0;
        // True once this page pushed a history entry: the back button can then
        // simply go back. Landing straight on a screen has nothing behind it.
        let pushed = false;

        const indexOf = (src) => stops.findIndex((stop) => stop.url === src);
        const viewing = () => page.dataset.view === 'viewer';

        const card = (item, active) => {
            const node = document.createElement('a');
            node.className = 'screen-card' + (active ? ' is-active' : '');
            node.href = item.url;
            node.dataset.src = item.url;
            node.title = 'Open ' + item.label;

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
            const name = document.createElement('span');
            name.className = 'screen-name';
            name.textContent = item.label;
            meta.appendChild(name);

            if (item.entry) {
                const badge = document.createElement('span');
                badge.className = 'toc-badge';
                badge.textContent = 'entry';
                meta.appendChild(badge);
            }

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

            if (styles.length < 2) {
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
                const label = document.createElement('span');
                label.textContent = style.label;
                head.appendChild(label);

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

        const renderFlowGallery = (stop) => {
            if (!gallery || !galleryGrid) return;

            const siblings = stops.filter((item) => item.flow_code === stop.flow_code);

            if (siblings.length < 2) {
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

            renderFlowGallery(stop);
            renderStyleBar(stop);
        };

        const address = (position) => {
            const url = new URL(window.location.href);

            if (position >= 0 && stops[position]) {
                url.searchParams.set(PARAM, stops[position].url);
            } else {
                url.searchParams.delete(PARAM);
            }

            return url.pathname + url.search;
        };

        const showViewer = (position) => {
            if (!viewing()) galleryScroll = window.scrollY;

            page.dataset.view = 'viewer';
            frame.src = stops[position].url;
            paint(position);
            window.scrollTo(0, 0);
        };

        const showGallery = () => {
            page.dataset.view = 'gallery';
            current = -1;
            // Drop the mockup so nothing keeps running behind the gallery.
            frame.removeAttribute('src');
            window.scrollTo(0, galleryScroll);
        };

        // A click opens the screen and leaves a step in the history, so the
        // browser's own back button returns to the gallery too.
        const open = (position) => {
            if (position < 0 || position >= stops.length) return;

            if (viewing()) {
                window.history.replaceState({ design: position }, '', address(position));
            } else {
                window.history.pushState({ design: position }, '', address(position));
                pushed = true;
            }

            showViewer(position);
        };

        const close = () => {
            if (pushed) {
                window.history.back();
                return;
            }

            window.history.replaceState(null, '', address(-1));
            showGallery();
        };

        window.addEventListener('popstate', () => {
            const position = indexOf(new URL(window.location.href).searchParams.get(PARAM) || '');

            if (position >= 0) {
                showViewer(position);
            } else {
                pushed = false;
                showGallery();
            }
        });

        // One delegated handler covers the gallery and the cards of the flow
        // built on the fly under the viewer.
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target.closest('[data-src]') : null;
            if (!target) return;

            const position = indexOf(target.dataset.src);
            if (position < 0) return;

            // Leave modified clicks alone: a new tab is a fair way to open one.
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1) return;

            event.preventDefault();
            open(position);
        });

        if (back) back.addEventListener('click', close);
        if (prev) prev.addEventListener('click', () => open(current - 1));
        if (next) next.addEventListener('click', () => open(current + 1));

        document.addEventListener('keydown', (event) => {
            if (!viewing()) return;
            if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return;
            if (event.key === 'ArrowLeft') open(current - 1);
            if (event.key === 'ArrowRight') open(current + 1);
            if (event.key === 'Escape') close();
        });

        // Links inside the mockup navigate the frame itself — keep the
        // caption, the counter and the address in step with it.
        frame.addEventListener('load', () => {
            if (!viewing()) return;

            let path = null;

            try {
                path = frame.contentWindow.location.pathname + frame.contentWindow.location.search;
            } catch (error) {
                return;
            }

            const position = stops.findIndex((stop) => stop.url === path || stop.url === decodeURIComponent(path));

            if (position >= 0 && position !== current) {
                window.history.replaceState({ design: position }, '', address(position));
                paint(position);
            }
        });

        // Narrow the gallery by name; a flow with nothing left steps aside.
        if (filter) {
            const cards = [...document.querySelectorAll('#design-gallery .screen-card')];
            const sections = [...document.querySelectorAll('#design-gallery .screen-section')];
            const total = Number(count?.dataset.total || cards.length);
            const word = (value) => value + ' ' + (value === 1 ? 'screen' : 'screens');

            filter.addEventListener('input', () => {
                const needle = filter.value.trim().toLowerCase();
                let shown = 0;

                cards.forEach((item) => {
                    const ok = needle === '' || (item.dataset.find || '').includes(needle);
                    item.hidden = !ok;
                    if (ok) shown += 1;
                });

                sections.forEach((section) => {
                    section.hidden = section.querySelector('.screen-card:not([hidden])') === null;
                });

                if (count) {
                    count.textContent = needle === ''
                        ? 'All ' + word(total) + ', flow by flow'
                        : 'Showing ' + shown + ' of ' + word(total);
                }

                if (noMatch) noMatch.hidden = shown > 0;
            });
        }

        // A shared address opens straight on its screen.
        const asked = indexOf(new URL(window.location.href).searchParams.get(PARAM) || '');

        if (asked >= 0) {
            window.history.replaceState({ design: asked }, '', address(asked));
            showViewer(asked);
        }
    })();
</script>
@endpush
