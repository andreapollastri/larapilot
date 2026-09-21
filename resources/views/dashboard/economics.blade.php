@extends('larapilot::dashboard.layout')

@section('title', 'Economics')

@push('styles')
<style>
    body .shell:has(.economics-page) {
        max-width: none;
        padding-left: max(20px, 4vw);
        padding-right: max(20px, 4vw);
    }

    .economics-page { display: flex; flex-direction: column; gap: 22px; }
    .economics-page[aria-busy="true"] { opacity: 0.55; transition: opacity 120ms ease; }

    .eco-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }

    .eco-top h2 { margin: 0 0 6px; font-size: 1.15rem; }
    .eco-top .sub {
        margin: 0;
        color: var(--muted);
        font-size: 0.875rem;
        max-width: 78ch;
        line-height: 1.5;
    }
    .eco-actions { display: flex; gap: 8px; flex-wrap: wrap; }

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
        cursor: pointer;
    }
    .btn:hover { text-decoration: none; }
    .btn.ghost {
        border-color: var(--border);
        background: var(--surface);
        color: var(--text);
    }
    .btn.small { padding: 5px 11px; font-size: 0.78rem; }

    /* ---- the pricing console ---- */
    .eco-console {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 16px 18px;
        position: sticky;
        top: 8px;
        z-index: 5;
    }
    .eco-console-row {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .eco-console-title {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        color: var(--muted);
        min-width: 110px;
    }
    .eco-console-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .eco-console-actions .chip { margin: 0; }

    .eco-console-group {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        border-top: 1px solid var(--border);
        padding-top: 12px;
    }
    .eco-console-group:first-of-type { border-top: 0; padding-top: 0; }
    .eco-console-title { padding-top: 6px; }

    .eco-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 10px 14px;
        flex: 1;
    }
    .eco-field { display: flex; flex-direction: column; gap: 4px; }
    .eco-field-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--muted);
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .eco-field-label b { color: var(--accent); font-size: 1rem; line-height: 0.6; }
    .eco-field select {
        width: 100%;
        padding: 7px 9px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--bg);
        color: var(--text);
        font-size: 0.85rem;
        font-weight: 600;
        font-family: inherit;
    }
    .eco-field select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }

    .eco-toggle { display: inline-flex; border: 1px solid var(--border); border-radius: 999px; overflow: hidden; }
    .eco-toggle label {
        padding: 7px 16px;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--muted);
        cursor: pointer;
        border-left: 1px solid var(--border);
    }
    .eco-toggle label:first-child { border-left: 0; }
    .eco-toggle label.is-on { background: var(--accent); color: #fff; }
    .eco-toggle input { position: absolute; opacity: 0; pointer-events: none; }

    .eco-command {
        margin: 8px 0;
        padding: 10px 12px;
        border-radius: 8px;
        background: color-mix(in srgb, var(--border) 45%, transparent);
        font-size: 0.76rem;
        white-space: pre-wrap;
        word-break: break-all;
        line-height: 1.5;
    }

    /* numbered sections keep the reading order obvious */
    .eco-section { display: flex; flex-direction: column; gap: 14px; }
    .eco-section-head { display: flex; gap: 14px; align-items: baseline; }
    .eco-section-num {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--accent);
        border: 1px solid var(--accent);
        background: var(--accent-soft);
        border-radius: 999px;
        padding: 2px 10px;
        white-space: nowrap;
    }
    .eco-section-head h3 { margin: 0 0 4px; font-size: 1.02rem; }
    .eco-section-head p {
        margin: 0;
        color: var(--muted);
        font-size: 0.84rem;
        line-height: 1.5;
        max-width: 92ch;
    }

    .metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 12px;
    }

    .metric { padding: 16px 18px; }
    .metric-label {
        color: var(--muted);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
    }
    .metric-value {
        margin-top: 6px;
        font-size: 1.45rem;
        font-weight: 700;
        line-height: 1.15;
    }
    .metric-sub {
        margin-top: 4px;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text);
    }
    .metric-hint {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed var(--border);
        color: var(--muted);
        font-size: 0.76rem;
        line-height: 1.45;
    }

    .panel { padding: 18px 20px; }
    .panel h4 { margin: 0 0 6px; font-size: 0.92rem; }
    .panel .hint {
        margin: 0 0 14px;
        color: var(--muted);
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 16px;
    }

    /* ---- packaging + business plan ---- */
    .tiers, .plan-lines {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 14px;
    }
    .tier, .plan-line { padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; }
    .tier.is-selected, .plan-line.is-selected {
        border-color: var(--accent);
        box-shadow: 0 0 0 1px var(--accent) inset;
    }
    .tier-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .tier-head .chip { margin: 0; }
    .tier-name {
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--accent);
    }
    .tier-price { font-size: 1.8rem; font-weight: 700; line-height: 1; }
    .tier-price small { font-size: 0.78rem; font-weight: 600; color: var(--muted); margin-left: 6px; }
    .tier-annual { font-size: 0.76rem; color: var(--muted); margin-top: -6px; }
    .tier-note { margin: 0; font-size: 0.78rem; color: var(--muted); line-height: 1.5; }
    .tier-features { font-size: 0.78rem; }
    .tier-features > span {
        display: block;
        font-weight: 600;
        color: var(--muted);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 4px;
    }
    .tier-features ul { margin: 0; padding-left: 1.05rem; color: var(--text); }
    .tier-features li { margin: 2px 0; }
    .tier-features li.more { color: var(--muted); list-style: none; margin-left: -1.05rem; }

    .trend { font-weight: 700; font-size: 0.8rem; color: var(--muted); }
    .trend.up { color: #dc2626; }
    .trend.down { color: #059669; }

    .bars { display: grid; gap: 10px; }
    .bar-row {
        display: grid;
        grid-template-columns: 160px 1fr 110px;
        gap: 10px;
        align-items: center;
        font-size: 0.85rem;
    }
    .bar-row .bar-label { display: flex; flex-direction: column; }
    .bar-row .bar-label small { color: var(--muted); font-size: 0.7rem; line-height: 1.35; }
    .bar-track {
        height: 10px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--border) 70%, transparent);
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--accent), #0ea5e9);
    }
    .bar-fill.is-tax { background: linear-gradient(90deg, #f59e0b, #ef4444); }
    .bar-fill.is-net { background: linear-gradient(90deg, #10b981, #14b8a6); }
    .bar-fill.is-margin { background: linear-gradient(90deg, #8b5cf6, #6366f1); }

    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .table th, .table td {
        text-align: left;
        padding: 9px 6px;
        border-top: 1px solid var(--border);
        vertical-align: top;
    }
    .table thead th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
        border-top: 0;
    }
    .table tbody th { font-weight: 600; }
    .table tbody th small {
        display: block;
        margin-top: 2px;
        font-weight: 400;
        color: var(--muted);
        font-size: 0.72rem;
        line-height: 1.4;
    }
    .table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .table tr.is-total th, .table tr.is-total td { border-top: 2px solid var(--border); }

    .scroll-y { max-height: 460px; overflow: auto; }

    .defs { margin: 0; font-size: 0.82rem; }
    .defs div { padding: 10px 0; border-top: 1px solid var(--border); }
    .defs div:first-child { border-top: 0; padding-top: 0; }
    .defs dt { font-weight: 700; }
    .defs dd { margin: 3px 0 0; color: var(--muted); line-height: 1.5; }

    .eco-details { padding: 16px 20px; }
    .eco-details summary { cursor: pointer; font-weight: 600; font-size: 0.9rem; }

    .banner {
        padding: 14px 16px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--accent-soft) 70%, transparent);
        font-size: 0.85rem;
        line-height: 1.5;
    }
    .banner strong { display: block; margin-bottom: 4px; }
    .banner.warn {
        border-color: color-mix(in srgb, #f59e0b 50%, var(--border));
        background: color-mix(in srgb, #f59e0b 12%, transparent);
    }
    .banner ul { margin: 6px 0 0; padding-left: 1.1rem; }
    .banner li { margin: 3px 0; }

    .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 999px;
        border: 1px solid var(--border);
        font-size: 0.78rem;
        color: var(--muted);
    }
    .chip.current {
        border-color: var(--accent);
        background: var(--accent-soft);
        color: var(--accent);
        font-weight: 600;
    }
    .chip.live {
        border-color: color-mix(in srgb, var(--status-done) 60%, var(--border));
        background: color-mix(in srgb, var(--status-done) 14%, transparent);
        color: var(--status-done);
        font-weight: 600;
    }
    .chip.stale {
        border-color: color-mix(in srgb, #f59e0b 60%, var(--border));
        background: color-mix(in srgb, #f59e0b 14%, transparent);
        color: #b45309;
        font-weight: 600;
    }

    .tag {
        display: inline-flex;
        padding: 1px 7px;
        border-radius: 999px;
        border: 1px solid var(--border);
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--muted);
    }
    .tag.plan { border-color: var(--status-planned); color: var(--status-planned); }
    .tag.points { border-color: var(--status-review); color: var(--status-review); }
    .tag.unsized { border-color: #f59e0b; color: #b45309; }

    .notes { margin: 0; padding-left: 1.1rem; color: var(--muted); font-size: 0.82rem; }
    .notes li { margin: 0.35rem 0; }

    .forecast {
        display: grid;
        grid-template-columns: repeat(36, minmax(6px, 1fr));
        gap: 3px;
        align-items: end;
        height: 140px;
        margin-top: 8px;
    }
    .forecast-bar {
        background: color-mix(in srgb, var(--accent) 55%, var(--border));
        border-radius: 3px 3px 0 0;
        min-height: 3px;
    }
    .forecast-bar.is-recovered { background: linear-gradient(180deg, #10b981, #14b8a6); }

    .empty-card { padding: 40px 28px; text-align: center; }
    .empty-card h2 { margin: 0 0 8px; }
    .empty-card p { margin: 0 auto 16px; max-width: 62ch; color: var(--muted); }

    .disclaimer { margin: 0; color: var(--muted); font-size: 0.75rem; line-height: 1.5; }

    @media (max-width: 720px) {
        .eco-console { position: static; }
        .eco-console-group { flex-direction: column; gap: 8px; }
        .bar-row { grid-template-columns: 120px 1fr 90px; }
    }
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
