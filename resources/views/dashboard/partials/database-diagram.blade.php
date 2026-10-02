{{-- The database drawn: a box for each table, a line for each foreign key. The places come from DatabaseDiagramService. --}}
@push('styles')
<style>
    .db-erd-card { overflow: hidden; }

    .db-erd-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border);
    }

    .db-erd-modes { display: inline-flex; gap: 2px; padding: 3px; border-radius: 999px; background: var(--surface-3); }

    .db-erd-modes a {
        padding: 4px 12px;
        border-radius: 999px;
        color: var(--text-2);
        font-size: 0.8rem;
        font-weight: 550;
        text-decoration: none;
    }

    .db-erd-modes a:hover { color: var(--text); }
    .db-erd-modes a[aria-current] { background: var(--surface); color: var(--accent-strong); box-shadow: var(--shadow); }

    .db-erd-actions { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 8px 14px; }
    .db-erd-zoom { display: inline-flex; align-items: center; gap: 6px; }
    .db-erd-zoom .btn { min-width: 32px; padding: 0 9px; font-variant-numeric: tabular-nums; }
    .db-erd-actions > .btn .icon { width: 14px; height: 14px; }

    .db-erd-wrap {
        overflow: auto;
        max-height: 76vh;
        background: var(--surface-2);
        cursor: grab;
    }

    .db-erd-wrap.is-dragging { cursor: grabbing; }

    .db-erd {
        display: block;
        max-width: none;
        margin: 0 auto;
        user-select: none;
        -webkit-user-select: none;
    }

    .db-erd text { fill: var(--text-2); font-family: var(--mono); font-size: 12px; }
    .db-erd-box { fill: var(--surface); stroke: var(--border-strong); }
    .db-erd-head { fill: var(--surface-3); stroke: var(--border-strong); }
    .db-erd .db-erd-title { fill: var(--text); font-weight: 700; }
    .db-erd a:hover .db-erd-title, .db-erd a:focus-visible .db-erd-title { fill: var(--accent); text-decoration: underline; }
    .db-erd .db-erd-kind, .db-erd .db-erd-type, .db-erd .db-erd-more, .db-erd .db-erd-label { fill: var(--muted); }
    .db-erd .db-erd-kind, .db-erd .db-erd-mark { font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; }
    .db-erd .db-erd-mark { fill: var(--muted); }
    .db-erd .is-pk { fill: var(--text); font-weight: 700; }
    .db-erd .is-fk, .db-erd .db-erd-mark.is-fk { fill: var(--accent); }
    .db-erd .db-erd-label { font-family: var(--font); font-size: 11px; font-weight: 650; letter-spacing: 0.08em; text-transform: uppercase; }
    .db-erd .is-view .db-erd-box, .db-erd .is-view .db-erd-head { stroke-dasharray: 5 4; }

    .db-erd-node { cursor: pointer; }
    .db-erd-node, .db-erd-edge, .db-erd-dot { transition: opacity 0.12s; }
    .db-erd-edge { fill: none; stroke: var(--muted); stroke-width: 1.4; }
    .db-erd-dot { fill: var(--surface); stroke: var(--muted); stroke-width: 1.4; }
    .db-erd-arrow { fill: var(--muted); }
    .db-erd-arrow.is-on { fill: var(--accent); }

    /* One table picked: it, its lines, and the tables at their other end stay lit. */
    .db-erd.is-focus .db-erd-node:not(.is-on) { opacity: 0.3; }
    .db-erd.is-focus .db-erd-edge:not(.is-on), .db-erd.is-focus .db-erd-dot:not(.is-on) { opacity: 0.12; }
    .db-erd-edge.is-on { stroke: var(--accent); stroke-width: 2; marker-end: url(#db-erd-arrow-on); }
    .db-erd-dot.is-on { stroke: var(--accent); }
    .db-erd-node.is-on .db-erd-box, .db-erd-node.is-on .db-erd-head { stroke: var(--accent); }

    .db-erd-foot { display: flex; flex-wrap: wrap; gap: 4px 16px; }
    .db-erd-foot b { color: var(--text-2); font-family: var(--mono); font-size: 0.72rem; }

    @media print {
        .db-erd-wrap { max-height: none; overflow: visible; }
    }
</style>
@endpush

@if ($diagram === null)
    <section class="card" aria-label="Diagram">
        <div class="empty">
            <p>The diagram is drawn for a database of up to {{ number_format($limit) }} tables and views; this one has {{ number_format(count($objects)) }}.</p>
        </div>
    </section>
@else
    @php
        $relations = count($diagram['edges']);
        $modes = ['' => 'All columns', 'keys' => 'Keys only'];
    @endphp
    <section class="card db-erd-card" aria-label="Diagram" data-db-erd>
        <div class="db-erd-bar">
            <nav class="db-erd-modes" aria-label="Columns shown">
                @foreach ($modes as $mode => $label)
                    <a href="{{ route('larapilot.dashboard.database', array_filter(['view' => 'diagram', 'columns' => $mode])) }}" @if (($mode === 'keys') === $keys_only) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="db-erd-actions">
                <div class="db-erd-zoom" data-db-erd-tools hidden>
                    <button type="button" class="btn ghost small" data-db-erd-zoom="out" aria-label="Zoom out">−</button>
                    <button type="button" class="btn ghost small" data-db-erd-zoom="reset" title="Back to 100%"><span data-db-erd-level>100%</span></button>
                    <button type="button" class="btn ghost small" data-db-erd-zoom="in" aria-label="Zoom in">+</button>
                    <button type="button" class="btn ghost small" data-db-erd-zoom="fit">Fit</button>
                </div>
                <a class="btn ghost small" href="{{ route('larapilot.dashboard.database.diagram', array_filter(['columns' => $keys_only ? 'keys' : null])) }}" title="The diagram as it is shown here, on one page.">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download PDF</a>
            </div>
        </div>

        <div class="db-erd-wrap" data-db-erd-wrap tabindex="0">
            <svg class="db-erd" xmlns="http://www.w3.org/2000/svg" width="{{ $diagram['width'] }}" height="{{ $diagram['height'] }}" viewBox="0 0 {{ $diagram['width'] }} {{ $diagram['height'] }}" role="img" aria-label="Diagram of the database: {{ count($diagram['nodes']) }} tables and views, {{ $relations }} foreign keys" data-db-erd-svg>
                <defs>
                    <marker id="db-erd-arrow" class="db-erd-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 1 9 5 0 9z"/></marker>
                    <marker id="db-erd-arrow-on" class="db-erd-arrow is-on" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M0 1 9 5 0 9z"/></marker>
                </defs>

                @foreach ($diagram['edges'] as $edge)
                    <path class="db-erd-edge" d="{{ $edge['path'] }}" marker-end="url(#db-erd-arrow)" data-from="{{ $edge['from'] }}" data-to="{{ $edge['to'] }}"><title>{{ $edge['label'] }}</title></path>
                @endforeach

                @foreach ($diagram['labels'] as $label)
                    <text class="db-erd-label" x="{{ $label['x'] }}" y="{{ $label['y'] }}">{{ $label['text'] }}</text>
                @endforeach

                @foreach ($diagram['nodes'] as $node)
                    @php
                        [$x, $y, $w, $head] = [$node['x'], $node['y'], $node['w'], $node['head']];
                        $right = $x + $w;
                    @endphp
                    <g class="db-erd-node @if ($node['kind'] === 'view') is-view @endif" data-table="{{ $node['key'] }}">
                        <rect class="db-erd-box" x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ $node['h'] }}" rx="8"/>
                        <path class="db-erd-head" d="M{{ $x }} {{ $y + $head }}V{{ $y + 8 }}Q{{ $x }} {{ $y }} {{ $x + 8 }} {{ $y }}H{{ $right - 8 }}Q{{ $right }} {{ $y }} {{ $right }} {{ $y + 8 }}V{{ $y + $head }}Z"/>
                        <a href="{{ route('larapilot.dashboard.database.table', ['table' => $node['key']]) }}">
                            <title>{{ $node['key'] }}</title>
                            <text class="db-erd-title" x="{{ $x + 12 }}" y="{{ $y + $head / 2 + 4 }}">{{ $node['label'] }}</text>
                        </a>
                        @if ($node['kind'] === 'view')
                            <text class="db-erd-kind" x="{{ $right - 12 }}" y="{{ $y + $head / 2 + 3.5 }}" text-anchor="end">VIEW</text>
                        @endif
                        @foreach ($node['rows'] as $row)
                            @if ($row['name'] === '')
                                <text class="db-erd-more" x="{{ $x + 12 }}" y="{{ $row['y'] + 4 }}">{{ $row['label'] }}</text>
                            @else
                                <g>
                                    <title>{{ $row['title'] }}</title>
                                    @if ($row['mark'] !== '')
                                        <text class="db-erd-mark @if ($row['foreign']) is-fk @endif" x="{{ $x + 12 }}" y="{{ $row['y'] + 3.5 }}">{{ $row['mark'] }}</text>
                                    @endif
                                    <text class="@if ($row['mark'] === 'PK') is-pk @endif @if ($row['foreign']) is-fk @endif" x="{{ $x + 38 }}" y="{{ $row['y'] + 4 }}">{{ $row['label'] }}</text>
                                    <text class="db-erd-type" x="{{ $right - 12 }}" y="{{ $row['y'] + 4 }}" text-anchor="end">{{ $row['type'] }}</text>
                                </g>
                            @endif
                        @endforeach
                    </g>
                @endforeach

                {{-- Above the boxes, so the end of a line is never under one. --}}
                @foreach ($diagram['edges'] as $edge)
                    <circle class="db-erd-dot" cx="{{ $edge['x'] }}" cy="{{ $edge['y'] }}" r="3" data-from="{{ $edge['from'] }}" data-to="{{ $edge['to'] }}"/>
                @endforeach
            </svg>
        </div>

        <div class="db-list-foot db-erd-foot">
            <span>{{ number_format($tables) }} {{ $tables === 1 ? 'table' : 'tables' }}@if ($views > 0) · {{ number_format($views) }} {{ $views === 1 ? 'view' : 'views' }}@endif · {{ number_format($relations) }} {{ $relations === 1 ? 'foreign key' : 'foreign keys' }}</span>
            <span><b>PK</b> primary key</span>
            <span><b>FK</b> foreign key — its line ends with an arrow on the table it points at</span>
            <span>Click a table to keep only its relations lit; its name opens it.</span>
        </div>
    </section>

    @push('scripts')
    <script>
        (() => {
            // The page reads without scripts — the diagram scrolls. With them: zoom, drag, and one table's relations lit.
            document.querySelectorAll('[data-db-erd]').forEach((root) => {
                const wrap = root.querySelector('[data-db-erd-wrap]');
                const svg = root.querySelector('[data-db-erd-svg]');
                const tools = root.querySelector('[data-db-erd-tools]');
                const level = root.querySelector('[data-db-erd-level]');

                if (!wrap || !svg || !tools || !level) {
                    return;
                }

                const width = svg.viewBox.baseVal.width;
                const height = svg.viewBox.baseVal.height;
                let zoom = 1;

                // Zoom about a point of the screen — the middle of the frame when none is given.
                const set = (next, clientX, clientY) => {
                    const frame = wrap.getBoundingClientRect();
                    const px = clientX === undefined ? wrap.clientWidth / 2 : clientX - frame.left;
                    const py = clientY === undefined ? wrap.clientHeight / 2 : clientY - frame.top;
                    const ux = (wrap.scrollLeft + px) / zoom;
                    const uy = (wrap.scrollTop + py) / zoom;

                    zoom = Math.min(2, Math.max(0.2, next));
                    svg.style.width = (width * zoom) + 'px';
                    svg.style.height = (height * zoom) + 'px';
                    wrap.scrollLeft = ux * zoom - px;
                    wrap.scrollTop = uy * zoom - py;
                    level.textContent = Math.round(zoom * 100) + '%';
                };

                const fit = () => Math.min(1, wrap.clientWidth / width, (parseFloat(getComputedStyle(wrap).maxHeight) || window.innerHeight) / height);

                tools.hidden = false;
                tools.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-db-erd-zoom]');

                    if (!button) {
                        return;
                    }

                    const action = button.dataset.dbErdZoom;
                    set(action === 'in' ? zoom * 1.25 : action === 'out' ? zoom / 1.25 : action === 'fit' ? fit() : 1);
                });

                // Ctrl or ⌘ with the wheel — and a pinch on a trackpad — zooms; the wheel alone scrolls.
                wrap.addEventListener('wheel', (event) => {
                    if (!event.ctrlKey && !event.metaKey) {
                        return;
                    }

                    event.preventDefault();
                    set(zoom * Math.exp(-event.deltaY * 0.01), event.clientX, event.clientY);
                }, { passive: false });

                let drag = null;
                let moved = false;

                wrap.addEventListener('pointerdown', (event) => {
                    if (event.pointerType !== 'mouse' || event.button !== 0) {
                        return;
                    }

                    drag = { x: event.clientX, y: event.clientY, left: wrap.scrollLeft, top: wrap.scrollTop };
                    moved = false;
                });

                window.addEventListener('pointermove', (event) => {
                    if (!drag) {
                        return;
                    }

                    const dx = event.clientX - drag.x;
                    const dy = event.clientY - drag.y;

                    if (!moved && Math.abs(dx) + Math.abs(dy) < 4) {
                        return;
                    }

                    moved = true;
                    wrap.classList.add('is-dragging');
                    wrap.scrollLeft = drag.left - dx;
                    wrap.scrollTop = drag.top - dy;
                });

                window.addEventListener('pointerup', () => {
                    drag = null;
                    wrap.classList.remove('is-dragging');
                });

                wrap.addEventListener('dragstart', (event) => event.preventDefault());

                // The click that ends a drag opens nothing and picks nothing.
                wrap.addEventListener('click', (event) => {
                    if (moved) {
                        moved = false;
                        event.preventDefault();
                        event.stopPropagation();
                    }
                }, true);

                const nodes = svg.querySelectorAll('[data-table]');
                const lines = svg.querySelectorAll('[data-from]');
                let pinned = null;

                const light = (key) => {
                    const lit = new Set(key === null ? [] : [key]);

                    lines.forEach((line) => {
                        const on = key !== null && (line.dataset.from === key || line.dataset.to === key);

                        line.classList.toggle('is-on', on);

                        if (on) {
                            lit.add(line.dataset.from);
                            lit.add(line.dataset.to);
                        }
                    });

                    nodes.forEach((node) => node.classList.toggle('is-on', lit.has(node.dataset.table)));
                    svg.classList.toggle('is-focus', key !== null);
                };

                nodes.forEach((node) => {
                    node.addEventListener('mouseenter', () => pinned === null && light(node.dataset.table));
                    node.addEventListener('mouseleave', () => pinned === null && light(null));
                });

                wrap.addEventListener('click', (event) => {
                    if (event.target.closest('a')) {
                        return;
                    }

                    const node = event.target.closest('[data-table]');
                    const key = node ? node.dataset.table : null;

                    pinned = key === pinned ? null : key;
                    light(pinned);
                });

                // A diagram wider than the frame opens small enough to be seen whole, never too small to read.
                set(Math.max(0.6, Math.min(1, wrap.clientWidth / width)));
                wrap.scrollLeft = 0;
                wrap.scrollTop = 0;
            });
        })();
    </script>
    @endpush
@endif
