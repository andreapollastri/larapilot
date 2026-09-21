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
        grid-template-columns: 290px 1fr;
        gap: 16px;
        align-items: start;
    }
    @media (max-width: 1000px) {
        .design-stage { grid-template-columns: 1fr; }
        .design-toc { position: static !important; max-height: none !important; }
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
        margin-top: 6px;
    }
    .toc-flow .toc-count {
        margin-left: auto;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--muted);
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
    .design-crumb .flow { color: var(--muted); font-size: 0.72rem; }
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
        height: min(76vh, 900px);
        border: 0;
        background: #fff;
    }

    .flow-gallery { padding: 16px 18px 20px; }
    .flow-gallery header { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .flow-gallery h3 { margin: 0; font-size: 0.95rem; }
    .flow-gallery .hint { margin: 4px 0 14px; color: var(--muted); font-size: 0.8rem; }
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 12px;
    }
    .gallery-card {
        overflow: hidden;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
    }
    .gallery-card:hover { text-decoration: none; border-color: var(--accent); }
    .gallery-card.is-active { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-soft); }
    .gallery-thumb {
        height: 170px;
        overflow: hidden;
        background: #fff;
        border-bottom: 1px solid var(--border);
    }
    .gallery-thumb iframe {
        display: block;
        width: 200%;
        height: 340px;
        border: 0;
        pointer-events: none;
        transform: scale(0.5);
        transform-origin: 0 0;
    }
    .gallery-card-label {
        padding: 9px 12px 12px;
        font-size: 0.82rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    details.all-screens summary {
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 14px 18px;
    }
    details.all-screens .all-body { padding: 0 18px 18px; }
    .all-flow { margin-top: 18px; }
    .all-flow:first-child { margin-top: 6px; }
    .all-flow h4 { margin: 0 0 8px; font-size: 0.86rem; }

    .empty-card { padding: 40px 28px; text-align: center; }
    .empty-card h2 { margin: 0 0 8px; }
    .empty-card p { margin: 0 auto 12px; max-width: 62ch; color: var(--muted); }
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

        foreach ($items as $item) {
            $flow = ($item['code'] ?? '').' — '.($item['title'] ?? '');
            $entry = $item['entry'] ?? null;
            $screens = is_array($item['screens'] ?? null) ? $item['screens'] : [];

            usort($screens, static function (array $a, array $b) use ($entry): int {
                $aEntry = ($a['file'] ?? null) === $entry ? 0 : 1;
                $bEntry = ($b['file'] ?? null) === $entry ? 0 : 1;

                return $aEntry <=> $bEntry;
            });

            foreach ($screens as $screen) {
                if (empty($screen['url'])) {
                    continue;
                }

                $stops[] = [
                    'url' => $screen['url'],
                    'flow' => $flow,
                    'label' => $screen['label'] ?? $screen['file'],
                    'entry' => ($screen['file'] ?? null) === $entry,
                ];
            }
        }
    @endphp

    <div class="design-page">
        <div class="design-top">
            <div>
                <h2>Design</h2>
                <p class="sub">
                    Start at the presentation index and walk the mockups for <strong>{{ $projectTitle }}</strong> in order
                    @if ($available)
                        — {{ $catalog['spec_count'] }} flow{{ $catalog['spec_count'] === 1 ? '' : 's' }},
                        {{ $catalog['screen_count'] }} screen{{ $catalog['screen_count'] === 1 ? '' : 's' }}
                    @endif
                    . Files live in <code>{{ $catalog['path'] ?? '.larapilot/mockups/' }}</code>.
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
                        <button type="button" class="toc-item is-overview is-active" data-src="{{ $presentationUrl }}">
                            Presentation index
                            <span class="toc-badge">start</span>
                        </button>
                    @endif

                    <ul class="toc-list">
                        @foreach ($items as $item)
                            @php
                                $entryUrl = $item['entry_url'] ?? null;
                                $screens = is_array($item['screens'] ?? null) ? $item['screens'] : [];
                            @endphp
                            <li>
                                <button type="button" class="toc-flow" @if ($entryUrl) data-src="{{ $entryUrl }}" @endif>
                                    <span>{{ $item['code'] }} — {{ $item['title'] }}</span>
                                    <span class="toc-count">{{ count($screens) }}</span>
                                </button>
                                <ul class="toc-screens">
                                    @foreach ($screens as $screen)
                                        <li>
                                            <button type="button" class="toc-item toc-screen" data-src="{{ $screen['url'] ?? '' }}">
                                                {{ $screen['label'] ?? $screen['file'] }}
                                                @if (($screen['file'] ?? null) === ($item['entry'] ?? null))
                                                    <span class="toc-badge">entry</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                </aside>

                <div class="design-viewer">
                    <section class="card design-frame-wrap">
                        <div class="design-frame-bar">
                            <div class="design-crumb">
                                <span class="flow" id="design-flow">Overview</span>
                                <span class="screen" id="design-caption">Presentation index</span>
                            </div>
                            <div class="design-nav">
                                <span class="counter" id="design-counter"></span>
                                <button type="button" class="step" id="design-prev" title="Previous screen" aria-label="Previous screen">←</button>
                                <button type="button" class="step" id="design-next" title="Next screen" aria-label="Next screen">→</button>
                                <a class="btn ghost" id="design-open" href="{{ $presentationUrl }}" target="_blank" rel="noopener noreferrer">Open</a>
                            </div>
                        </div>
                        <iframe id="design-frame" class="design-frame" src="{{ $presentationUrl }}" title="Design viewer"></iframe>
                    </section>

                    <section class="card flow-gallery" id="flow-gallery" hidden>
                        <header>
                            <h3 id="flow-gallery-title"></h3>
                        </header>
                        <p class="hint">Screens in this flow. Click one to open it in the viewer above.</p>
                        <div class="gallery-grid" id="flow-gallery-grid"></div>
                    </section>
                </div>
            </div>

            <details class="card all-screens">
                <summary>All {{ $catalog['screen_count'] }} screens, flow by flow</summary>
                <div class="all-body">
                    @foreach ($items as $item)
                        <div class="all-flow">
                            <h4>{{ $item['code'] }} — {{ $item['title'] }}</h4>
                            <div class="gallery-grid">
                                @foreach ($item['screens'] ?? [] as $screen)
                                    <a class="gallery-card" href="{{ $screen['url'] ?? '#' }}" data-src="{{ $screen['url'] ?? '' }}">
                                        @if (! empty($screen['url']))
                                            <div class="gallery-thumb">
                                                <iframe src="{{ $screen['url'] }}" loading="lazy" title="{{ $screen['label'] ?? $screen['file'] }}" tabindex="-1"></iframe>
                                            </div>
                                        @endif
                                        <div class="gallery-card-label">
                                            {{ $screen['label'] ?? $screen['file'] }}
                                            @if (($screen['file'] ?? null) === ($item['entry'] ?? null))
                                                <span class="toc-badge">entry</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const frame = document.getElementById('design-frame');
        if (!frame) return;

        const stops = @json(array_values($stops));
        const caption = document.getElementById('design-caption');
        const flowLabel = document.getElementById('design-flow');
        const counter = document.getElementById('design-counter');
        const openLink = document.getElementById('design-open');
        const prev = document.getElementById('design-prev');
        const next = document.getElementById('design-next');
        const gallery = document.getElementById('flow-gallery');
        const galleryTitle = document.getElementById('flow-gallery-title');
        const galleryGrid = document.getElementById('flow-gallery-grid');
        let current = 0;

        const indexOf = (src) => stops.findIndex((stop) => stop.url === src);

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

            siblings.forEach((item) => {
                const card = document.createElement('a');
                card.className = 'gallery-card' + (item.url === stop.url ? ' is-active' : '');
                card.href = item.url;
                card.dataset.src = item.url;

                const thumb = document.createElement('div');
                thumb.className = 'gallery-thumb';
                const preview = document.createElement('iframe');
                preview.src = item.url;
                preview.loading = 'lazy';
                preview.tabIndex = -1;
                preview.title = item.label;
                thumb.appendChild(preview);

                const label = document.createElement('div');
                label.className = 'gallery-card-label';
                label.textContent = item.label;

                if (item.entry) {
                    const badge = document.createElement('span');
                    badge.className = 'toc-badge';
                    badge.textContent = 'entry';
                    label.appendChild(badge);
                }

                card.appendChild(thumb);
                card.appendChild(label);
                card.addEventListener('click', (event) => {
                    event.preventDefault();
                    go(indexOf(item.url));
                });

                galleryGrid.appendChild(card);
            });

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

            document.querySelectorAll('.toc-item, .toc-flow').forEach((el) => {
                el.classList.toggle('is-active', el.dataset.src === stop.url);
            });

            renderGallery(stop);
        };

        const go = (position) => {
            if (position < 0 || position >= stops.length) return;
            frame.src = stops[position].url;
            paint(position);
            frame.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };

        document.querySelectorAll('.toc-item, .toc-flow, .all-screens .gallery-card').forEach((el) => {
            el.addEventListener('click', (event) => {
                const src = el.dataset.src;
                if (!src) return;
                event.preventDefault();
                const position = indexOf(src);
                if (position >= 0) go(position);
            });
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

        paint(0);
    })();
</script>
@endpush
