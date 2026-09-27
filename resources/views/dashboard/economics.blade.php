@extends('larapilot::dashboard.layout')

@section('title', 'Economics')

@section('main-class', 'is-wide')

@push('styles')
<style>
    .economics-page { display: flex; flex-direction: column; gap: 22px; }
    .economics-page[aria-busy="true"] { opacity: 0.55; transition: opacity 120ms ease; }
    .economics-page .page-head,
    .economics-page .metrics { margin-bottom: 0; }
    .economics-page .chips { margin-bottom: 12px; }
    .economics-page .page-head .chips { margin: 12px 0 0; }

    .eco-answer {
        padding: 20px 22px;
        border-color: color-mix(in srgb, var(--accent) 40%, var(--border));
        background: var(--accent-soft);
    }

    .eco-answer p {
        margin: 0;
        font-size: 1.08rem;
        line-height: 1.55;
        max-width: 70ch;
    }

    .eco-how {
        margin: 0;
        color: var(--muted);
        font-size: 0.9rem;
        line-height: 1.55;
        max-width: 72ch;
    }

    .eco-steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr));
        gap: 12px;
    }

    .eco-step { padding: 16px 18px; display: flex; flex-direction: column; gap: 6px; }

    .eco-step-k {
        color: var(--accent);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.07em;
        text-transform: uppercase;
    }

    .eco-step .metric-value { margin-top: 0; font-size: 1.45rem; }
    .eco-step p { margin: 0; color: var(--muted); font-size: 0.8rem; line-height: 1.45; }

    .eco-fold { padding: 14px 18px; }

    .eco-fold > summary,
    .eco-details > summary {
        cursor: pointer;
        font-weight: 600;
        font-size: 0.92rem;
    }

    .eco-fold > summary::marker,
    .eco-details > summary::marker { color: var(--muted); }

    .eco-fold > .hint, .eco-fold > .panel, .eco-fold > .tiers, .eco-fold > .plan-lines, .eco-fold > .grid-2 { margin-top: 14px; }
    .eco-fold > .panel + .panel { margin-top: 14px; }

    .eco-console-hint {
        margin: 0;
        color: var(--muted);
        font-size: 0.84rem;
        line-height: 1.45;
        max-width: 72ch;
    }

    /* ---- the pricing console ---- */
    .eco-console {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 16px 18px;
    }

    /* pinned only on a screen tall enough to leave the answer in view */
    @media (min-width: 1024px) and (min-height: 1100px) {
        .eco-console {
            position: sticky;
            top: 12px;
            z-index: 5;
            box-shadow: 0 10px 28px color-mix(in srgb, var(--text) 8%, transparent);
        }
    }

    .eco-console-row {
        display: flex;
        align-items: center;
        gap: 12px 14px;
        flex-wrap: wrap;
    }

    .eco-console-title {
        min-width: 110px;
        padding-top: 6px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .eco-console-row .eco-console-title { padding-top: 0; }

    .eco-console-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .eco-console-actions .chip { margin: 0; }

    .eco-console-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid var(--border);
    }

    @media (min-width: 720px) {
        .eco-console-group { flex-direction: row; gap: 14px; align-items: flex-start; }
    }

    .eco-console-group:first-of-type { border-top: 0; padding-top: 0; }
    details.eco-console-group { display: block; }
    .eco-console details.eco-fold { padding: 12px 0 0; }
    details.eco-console-group > .eco-fields { margin-top: 12px; }

    .eco-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 170px), 1fr));
        gap: 10px 14px;
        flex: 1;
    }

    .eco-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }

    .eco-field-label {
        display: flex;
        align-items: center;
        gap: 5px;
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .eco-field-label b { color: var(--accent); font-size: 1rem; line-height: 0.6; }

    .eco-field select {
        width: 100%;
        min-height: 38px;
        padding: 6px 10px;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-sm);
        background: var(--surface);
        color: var(--text);
        font-family: inherit;
        font-size: 0.86rem;
        font-weight: 550;
    }

    .eco-field select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
    }

    .eco-toggle {
        display: inline-flex;
        flex-wrap: wrap;
        padding: 3px;
        gap: 2px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface-2);
    }

    .eco-toggle label {
        position: relative;
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        padding: 0 14px;
        border-radius: var(--radius-xs);
        color: var(--muted);
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
    }

    .eco-toggle label:hover { color: var(--text); }
    .eco-toggle label.is-on { background: var(--accent); color: var(--accent-contrast); }
    .eco-toggle label:focus-within { outline: 2px solid var(--accent); outline-offset: 2px; }
    .eco-toggle input { position: absolute; opacity: 0; pointer-events: none; }

    .eco-command {
        margin: 8px 0;
        padding: 10px 12px;
        border-radius: var(--radius-xs);
        background: var(--surface-3);
        font-size: 0.76rem;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-all;
    }

    /* numbered sections keep the reading order obvious */
    .eco-section { display: flex; flex-direction: column; gap: 14px; }
    .eco-section-head { display: flex; gap: 14px; align-items: flex-start; }

    .eco-section-num {
        display: grid;
        place-items: center;
        flex: none;
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent-strong);
        font-size: 0.8rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .eco-section-head h3 { margin: 2px 0 4px; font-size: 1.08rem; }

    .eco-section-head p {
        margin: 0;
        color: var(--muted);
        font-size: 0.85rem;
        line-height: 1.5;
        max-width: 92ch;
    }

    .economics-page .metrics { grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); }
    .economics-page .metric-value { font-size: 1.45rem; }

    .metric-sub {
        margin-top: 5px;
        color: var(--text-2);
        font-size: 0.78rem;
        font-weight: 600;
    }

    .metric-hint {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--border-strong);
        color: var(--muted);
        font-size: 0.76rem;
        line-height: 1.45;
    }

    .economics-page .panel { padding: 18px 20px; box-shadow: none; }
    .panel h4 { margin: 0 0 6px; font-size: 0.95rem; }
    .panel .hint { margin: 0 0 14px; }

    .grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
        gap: 16px;
    }

    /* ---- packaging + business plan ---- */
    .tiers, .plan-lines {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
        gap: 14px;
    }

    .tier, .plan-line { padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; box-shadow: none; }

    .tier.is-selected, .plan-line.is-selected {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
    }

    .tier-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .tier-head .chip { margin: 0; }

    .tier-name {
        color: var(--accent);
        font-size: 0.74rem;
        font-weight: 650;
        letter-spacing: 0.09em;
        text-transform: uppercase;
    }

    .tier-price { font-size: 1.8rem; font-weight: 650; line-height: 1; letter-spacing: -0.025em; font-variant-numeric: tabular-nums; }
    .tier-price small { margin-left: 6px; color: var(--muted); font-size: 0.78rem; font-weight: 600; letter-spacing: 0; }
    .tier-annual { margin-top: -6px; color: var(--muted); font-size: 0.76rem; }
    .tier-note { margin: 0; color: var(--muted); font-size: 0.78rem; line-height: 1.5; }
    .tier-features { font-size: 0.78rem; }

    .tier-features > span {
        display: block;
        margin-bottom: 4px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .tier-features ul { margin: 0; padding-left: 1.05rem; color: var(--text-2); }
    .tier-features li { margin: 2px 0; }
    .tier-features li.more { color: var(--muted); list-style: none; margin-left: -1.05rem; }

    .trend { color: var(--muted); font-size: 0.8rem; font-weight: 650; }
    .trend.up { color: var(--danger); }
    .trend.down { color: var(--ok); }

    .bars { display: grid; gap: 12px; }

    .bar-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 6px 12px;
        align-items: center;
        font-size: 0.85rem;
    }

    .bar-row .bar-track { grid-column: 1 / -1; grid-row: 2; height: 10px; }
    .bar-row .num { font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 600; }

    @media (min-width: 640px) {
        .bar-row { grid-template-columns: 170px minmax(0, 1fr) 110px; }
        .bar-row .bar-track { grid-column: auto; grid-row: auto; }
        .bar-row .num { text-align: right; }
    }

    .bar-row .bar-label { display: flex; flex-direction: column; }
    .bar-row .bar-label small { color: var(--muted); font-size: 0.7rem; line-height: 1.35; }

    .bar-fill.is-tax { background: var(--warn-fill); }
    .bar-fill.is-net { background: var(--ok-fill); }
    .bar-fill.is-margin { background: var(--violet-fill); }

    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .table th, .table td {
        padding: 9px 6px;
        border-top: 1px solid var(--border);
        text-align: left;
        vertical-align: top;
    }

    .table thead th {
        border-top: 0;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.07em;
        text-transform: uppercase;
    }

    .table tbody th { font-weight: 600; }

    .table tbody th small {
        display: block;
        margin-top: 2px;
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 400;
        line-height: 1.4;
    }

    .table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .table tr.is-total th, .table tr.is-total td { border-top: 2px solid var(--border-strong); }

    .economics-page .panel { overflow-x: auto; }
    .scroll-y { max-height: 460px; overflow: auto; scrollbar-width: thin; }

    .defs { margin: 0; font-size: 0.84rem; }
    .defs div { padding: 10px 0; border-top: 1px solid var(--border); }
    .defs div:first-child { border-top: 0; padding-top: 0; }
    .defs dt { font-weight: 600; }
    .defs dd { margin: 3px 0 0; color: var(--muted); line-height: 1.5; }

    .eco-details { padding: 16px 20px; }
    .eco-models .defs dt { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .eco-models .defs dt .chip { margin: 0; }

    .banner {
        padding: 14px 16px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, var(--border));
        border-radius: var(--radius-sm);
        background: color-mix(in srgb, var(--accent) 8%, var(--surface));
        font-size: 0.86rem;
        line-height: 1.5;
    }

    .banner strong { display: block; margin-bottom: 4px; font-weight: 600; }

    .banner.warn {
        border-color: color-mix(in srgb, var(--warn-fill) 50%, var(--border));
        background: color-mix(in srgb, var(--warn-fill) 11%, var(--surface));
    }

    .banner ul { margin: 6px 0 0; padding-left: 1.1rem; }
    .banner li { margin: 3px 0; }

    .tag {
        display: inline-flex;
        padding: 1px 8px;
        border: 1px solid var(--border);
        border-radius: 999px;
        color: var(--muted);
        font-size: 0.66rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .tag.plan { border-color: color-mix(in srgb, var(--status-planned) 55%, var(--border)); color: color-mix(in srgb, var(--status-planned) 70%, var(--text)); }
    .tag.points { border-color: color-mix(in srgb, var(--status-review) 55%, var(--border)); color: var(--violet); }
    .tag.unsized { border-color: color-mix(in srgb, var(--warn-fill) 55%, var(--border)); color: var(--warn); }

    .notes { margin: 0; padding-left: 1.1rem; color: var(--muted); font-size: 0.84rem; }
    .notes li { margin: 0.35rem 0; }

    .forecast {
        display: grid;
        grid-template-columns: repeat(36, minmax(4px, 1fr));
        gap: 3px;
        align-items: end;
        height: 140px;
        margin-top: 8px;
    }

    .forecast-bar {
        min-height: 3px;
        border-radius: 3px 3px 0 0;
        background: color-mix(in srgb, var(--accent) 50%, var(--surface-3));
    }

    .forecast-bar.is-recovered { background: var(--ok-fill); }

    .empty-card { padding: 48px 24px; text-align: center; }
    .empty-card h2 { margin: 0 0 8px; font-size: 1.2rem; }
    .empty-card p { margin: 0 auto 16px; max-width: 62ch; color: var(--muted); }

    .disclaimer { margin: 0; color: var(--muted); font-size: 0.75rem; line-height: 1.5; }
</style>
@endpush

@section('content')
    <div class="economics-page" id="eco-panel">
        @include('larapilot::dashboard.partials.economics-panel')
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const panel = document.getElementById('eco-panel');

    if (! panel) {
        return;
    }

    const pageUrl = @json(route('larapilot.dashboard.economics'));
    const panelUrl = @json(route('larapilot.dashboard.economics.panel'));

    // Only what the user actually moved travels in the URL, so the query stays
    // readable and "overridden" keeps meaning something on screen.
    const changedParams = () => {
        const form = document.getElementById('eco-controls');
        const params = new URLSearchParams();

        if (! form) {
            return params;
        }

        form.querySelectorAll('select, input').forEach((field) => {
            if (! field.name || (field.type === 'radio' && ! field.checked)) {
                return;
            }

            const fallback = field.dataset.ecoDefault;

            if (fallback === undefined || String(field.value) === String(fallback)) {
                return;
            }

            params.set(field.name, field.value);
        });

        return params;
    };

    let pending = 0;

    const refresh = async (focusName) => {
        const query = changedParams().toString();
        const token = ++pending;

        panel.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(panelUrl + (query ? '?' + query : ''), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (! response.ok || token !== pending) {
                return;
            }

            panel.innerHTML = await response.text();
            window.history.replaceState(null, '', pageUrl + (query ? '?' + query : ''));

            if (focusName) {
                const restored = panel.querySelector('[name="' + focusName + '"]');

                if (restored) {
                    restored.focus();
                }
            }
        } catch (error) {
            // Network or server error: leave the last good panel on screen and
            // fall back to the plain form submit the markup still carries.
            panel.querySelector('#eco-controls')?.setAttribute('data-eco-degraded', 'true');
        } finally {
            if (token === pending) {
                panel.removeAttribute('aria-busy');
            }
        }
    };

    panel.addEventListener('change', (event) => {
        const field = event.target.closest('#eco-controls [name]');

        if (field) {
            refresh(field.type === 'radio' ? null : field.name);
        }
    });

    panel.addEventListener('click', (event) => {
        const reset = event.target.closest('[data-eco-reset]');

        if (reset) {
            event.preventDefault();
            window.history.replaceState(null, '', pageUrl);
            panel.querySelectorAll('#eco-controls [name]').forEach((field) => {
                if (field.type === 'radio') {
                    field.checked = String(field.value) === String(field.dataset.ecoDefault);
                } else if (field.dataset.ecoDefault !== undefined) {
                    field.value = field.dataset.ecoDefault;
                }
            });
            refresh(null);

            return;
        }

        const copy = event.target.closest('[data-eco-copy]');

        if (copy) {
            const command = panel.querySelector('[data-eco-command]');

            if (command && navigator.clipboard) {
                navigator.clipboard.writeText(command.textContent.trim()).then(() => {
                    const label = copy.textContent;
                    copy.textContent = 'Copied';
                    window.setTimeout(() => { copy.textContent = label; }, 1600);
                });
            }
        }
    });
})();
</script>
@endpush
