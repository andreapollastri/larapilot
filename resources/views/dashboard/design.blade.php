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
        max-width: 72ch;
        line-height: 1.5;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        padding: 8px 14px;
        border-radius: 999px;
        border: 1px solid var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
    }
    .btn:hover { text-decoration: none; }
    .btn.is-disabled {
        opacity: 0.45;
        pointer-events: none;
        border-color: var(--border);
        background: var(--surface);
        color: var(--muted);
    }

    .design-stage {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 16px;
        min-height: 70vh;
        align-items: stretch;
    }
    @media (max-width: 960px) {
        .design-stage { grid-template-columns: 1fr; }
    }

    .design-toc {
        padding: 16px 14px 20px;
        overflow: auto;
        max-height: calc(100vh - 180px);
        position: sticky;
        top: 16px;
    }
    .design-toc h3 {
        margin: 0 0 10px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
    }
    .toc-list { list-style: none; margin: 0; padding: 0; }
    .toc-index,
    .toc-screen {
        display: block;
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
    .toc-index { font-weight: 700; margin-bottom: 8px; }
    .toc-group { margin: 10px 0 4px; }
    .toc-group-title {
        font-size: 0.78rem;
        font-weight: 700;
        padding: 6px 10px 2px;
        color: var(--muted);
    }
    .toc-screen { padding-left: 16px; color: var(--text); }
    .toc-index:hover,
    .toc-screen:hover,
    .toc-index.is-active,
    .toc-screen.is-active {
        background: var(--accent-soft);
        color: var(--accent);
    }

    .design-frame-wrap {
        display: flex;
        flex-direction: column;
        min-height: 70vh;
        overflow: hidden;
    }
    .design-frame-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
        font-size: 0.82rem;
    }
    .design-frame-bar span { color: var(--muted); }
    .design-frame-bar a { font-weight: 600; }
    .design-frame {
        flex: 1;
        width: 100%;
        min-height: 64vh;
        border: 0;
        background: #fff;
    }

    .gallery h3 {
        margin: 0 0 6px;
        font-size: 1rem;
    }
    .gallery .hint {
        margin: 0 0 14px;
        color: var(--muted);
        font-size: 0.82rem;
    }
    .gallery-spec { margin-bottom: 28px; }
    .gallery-spec h4 {
        margin: 0 0 10px;
        font-size: 0.95rem;
    }
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 12px;
    }
    .gallery-card {
        overflow: hidden;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .gallery-card:hover { text-decoration: none; border-color: var(--accent); }
    .gallery-card iframe {
        display: block;
        width: 100%;
        height: 220px;
        border: 0;
        pointer-events: none;
        background: #fff;
        transform: scale(1);
    }
    .gallery-card-label {
        padding: 8px 12px 12px;
        font-size: 0.82rem;
        font-weight: 600;
        border-top: 1px solid var(--border);
    }

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
    @endphp

    <div class="design-page">
        <div class="design-top">
            <div>
                <h2>Design</h2>
                <p class="sub">
                    Presentation index and every mockup for <strong>{{ $projectTitle }}</strong>
                    @if ($available)
                        · {{ $catalog['spec_count'] }} flow{{ $catalog['spec_count'] === 1 ? '' : 's' }}
                        · {{ $catalog['screen_count'] }} screen{{ $catalog['screen_count'] === 1 ? '' : 's' }}
                    @endif
                    . Artifacts live in <code>{{ $catalog['path'] ?? '.larapilot/mockups/' }}</code>.
                </p>
            </div>
            @if ($packageUrl && $available)
                <a class="btn" href="{{ $packageUrl }}">Download zip</a>
            @else
                <span class="btn is-disabled">Download zip</span>
            @endif
        </div>

        @if (! $available)
            <section class="card empty-card">
                <h2>No designs yet</h2>
                <p>Run <code>/larapilot-design</code> to produce HTML mockups. They will appear here as a presentation index plus a viewer for every screen.</p>
            </section>
        @else
            <div class="design-stage">
                <aside class="card design-toc" aria-label="Design index">
                    <h3>Index</h3>
                    <button type="button" class="toc-index is-active" data-src="{{ $presentationUrl }}" data-label="Presentation index">Presentation index</button>
                    <ul class="toc-list">
                        @foreach ($items as $item)
                            <li class="toc-group">
                                <div class="toc-group-title">{{ $item['code'] }} — {{ $item['title'] }}</div>
                                @foreach ($item['screens'] ?? [] as $screen)
                                    <button type="button" class="toc-screen" data-src="{{ $screen['url'] ?? '' }}" data-label="{{ $item['code'] }} · {{ $screen['label'] ?? $screen['file'] }}">
                                        {{ $screen['label'] ?? $screen['file'] }}
                                    </button>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                </aside>
                <section class="card design-frame-wrap">
                    <div class="design-frame-bar">
                        <span id="design-caption">Presentation index</span>
                        <a id="design-open" href="{{ $presentationUrl }}" target="_blank" rel="noopener noreferrer">Open in new tab</a>
                    </div>
                    <iframe id="design-frame" class="design-frame" src="{{ $presentationUrl }}" title="Design viewer"></iframe>
                </section>
            </div>

            <section class="card" style="padding: 18px 20px;">
                <div class="gallery">
                    <h3>All screens</h3>
                    <p class="hint">Every mockup in the package. Click a card to load it in the viewer above.</p>
                    @foreach ($items as $item)
                        <div class="gallery-spec">
                            <h4>{{ $item['code'] }} — {{ $item['title'] }}</h4>
                            <div class="gallery-grid">
                                @foreach ($item['screens'] ?? [] as $screen)
                                    <a class="card gallery-card" href="{{ $screen['url'] ?? '#' }}" data-src="{{ $screen['url'] ?? '' }}" data-label="{{ $item['code'] }} · {{ $screen['label'] ?? $screen['file'] }}">
                                        @if (! empty($screen['url']))
                                            <iframe src="{{ $screen['url'] }}" loading="lazy" title="{{ $screen['label'] ?? $screen['file'] }}" tabindex="-1"></iframe>
                                        @endif
                                        <div class="gallery-card-label">{{ $screen['label'] ?? $screen['file'] }}</div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
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
        const caption = document.getElementById('design-caption');
        const openLink = document.getElementById('design-open');
        if (!frame) return;

        const activate = (src, label, button) => {
            if (!src) return;
            frame.src = src;
            if (caption) caption.textContent = label || src;
            if (openLink) openLink.href = src;
            document.querySelectorAll('.toc-index, .toc-screen').forEach((el) => el.classList.remove('is-active'));
            if (button) button.classList.add('is-active');
            frame.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        document.querySelectorAll('.toc-index, .toc-screen').forEach((button) => {
            button.addEventListener('click', () => activate(button.getAttribute('data-src'), button.getAttribute('data-label'), button));
        });

        document.querySelectorAll('.gallery-card').forEach((card) => {
            card.addEventListener('click', (event) => {
                const src = card.getAttribute('data-src');
                if (!src) return;
                event.preventDefault();
                const match = document.querySelector('.toc-screen[data-src="' + CSS.escape(src) + '"]');
                activate(src, card.getAttribute('data-label'), match);
            });
        });
    })();
</script>
@endpush
