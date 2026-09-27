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

    /* ---- colours of the money ----
       Two sets of three, each checked for colour-blind separation on both
       surfaces: what a price is made of (work, costs, margin) and where it
       ends up (kept, tax, costs). Costs wear the same colour in both. The
       figures are always written beside the colour. */
    .economics-page {
        --eco-work: #2a78d6;
        --eco-costs: #eda100;
        --eco-margin: #e87ba4;
        --eco-keep: #1baf7a;
        --eco-tax: #4a3aa7;
        --eco-line: #2a78d6;
        --eco-other: #9aa7b3;
    }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .economics-page {
            --eco-work: #3987e5;
            --eco-costs: #c98500;
            --eco-margin: #d55181;
            --eco-keep: #199e70;
            --eco-tax: #9085e9;
            --eco-line: #3987e5;
            --eco-other: #5d6b79;
        }
    }

    :root[data-theme="dark"] .economics-page {
        --eco-work: #3987e5;
        --eco-costs: #c98500;
        --eco-margin: #d55181;
        --eco-keep: #199e70;
        --eco-tax: #9085e9;
        --eco-line: #3987e5;
        --eco-other: #5d6b79;
    }

    .k-work { --kind: var(--eco-work); }
    .k-costs { --kind: var(--eco-costs); }
    .k-margin { --kind: var(--eco-margin); }
    .k-keep { --kind: var(--eco-keep); }
    .k-tax { --kind: var(--eco-tax); }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .swatch {
        display: inline-block;
        flex: none;
        width: 10px;
        height: 10px;
        border-radius: 3px;
        background: var(--kind, var(--border-strong));
    }

    /* ---- the way through the page ---- */
    .eco-nav { display: flex; align-items: baseline; gap: 8px 14px; flex-wrap: wrap; }

    .eco-nav ul {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .eco-nav a {
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        padding: 0 12px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text-2);
        font-size: 0.8rem;
        font-weight: 550;
    }

    .eco-nav a:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
    .eco-section { scroll-margin-top: 84px; }

    /* ---- the short answer ---- */
    .eco-answer {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 20px 18px;
        border-color: color-mix(in srgb, var(--accent) 40%, var(--border));
        background: var(--accent-soft);
    }

    @media (min-width: 640px) {
        .eco-answer { padding: 24px 26px; }
    }

    .eco-answer .eyebrow { color: var(--accent-strong); }

    .eco-answer-lead {
        margin: 0;
        font-size: 1.12rem;
        font-weight: 550;
        line-height: 1.5;
        letter-spacing: -0.008em;
        max-width: 72ch;
    }

    @media (min-width: 900px) {
        .eco-answer-lead { font-size: 1.22rem; }
    }

    .eco-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
        gap: 10px;
    }

    .eco-kpi {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
        padding: 14px 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
    }

    .eco-kpi-label {
        display: flex;
        align-items: center;
        gap: 7px;
        color: var(--muted);
        font-size: 0.72rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .eco-kpi-value {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1.5rem;
        font-weight: 650;
        line-height: 1.15;
        letter-spacing: -0.02em;
        overflow-wrap: anywhere;
    }

    .eco-kpi-value .icon { width: 20px; height: 20px; flex: none; }
    .eco-kpi.is-good .eco-kpi-value .icon { color: var(--ok); }
    .eco-kpi.is-warn .eco-kpi-value .icon { color: var(--warn); }
    .eco-kpi-sub { color: var(--text-2); font-size: 0.8rem; line-height: 1.4; }

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

    .eco-console-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px 16px;
        flex-wrap: wrap;
    }

    .eco-console-head h3 { margin: 0 0 3px; font-size: 1.02rem; }

    .eco-console-help {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin: 0;
        padding: 10px 12px;
        border-radius: var(--radius-xs);
        background: var(--surface-2);
        color: var(--text-2);
        font-size: 0.82rem;
        line-height: 1.45;
        min-height: 2.9em;
    }

    .eco-console-help .icon { flex: none; width: 16px; height: 16px; margin-top: 2px; color: var(--muted); }
    .eco-console-help.is-live { background: var(--accent-soft); color: var(--text); }
    .eco-console-help.is-live .icon { color: var(--accent); }

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

    .eco-console-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
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

    /* ---- a sum: a title, a bar to scale, and the receipt ---- */
    .eco-pair {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr));
        gap: 16px;
        align-items: start;
    }

    .eco-sum { display: flex; flex-direction: column; gap: 14px; }
    .eco-sum > header { display: flex; align-items: flex-start; gap: 12px; }
    .eco-sum > header h4 { margin: 0 0 4px; font-size: 1rem; }
    .eco-sum > header .hint { margin: 0; max-width: 74ch; }

    .eco-step-k {
        display: grid;
        place-items: center;
        flex: none;
        width: 24px;
        height: 24px;
        margin-top: 1px;
        border: 1px solid color-mix(in srgb, var(--accent) 45%, var(--border));
        border-radius: 999px;
        color: var(--accent-strong);
        font-size: 0.74rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .split { display: flex; gap: 2px; height: 16px; }

    .split-part {
        flex: 1 0 0;
        min-width: 4px;
        background: var(--kind);
    }

    .split-part:first-child { border-radius: 4px 0 0 4px; }
    .split-part:last-child { border-radius: 0 4px 4px 0; }
    .split-part:only-child { border-radius: 4px; }
    .split-part:hover { filter: brightness(1.08); }

    .split-key {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 16px;
        margin: -6px 0 0;
        padding: 0;
        list-style: none;
        color: var(--text-2);
        font-size: 0.78rem;
    }

    .split-key li { display: inline-flex; align-items: center; gap: 6px; }
    .split-key b { color: var(--text); font-weight: 650; font-variant-numeric: tabular-nums; }

    .receipt { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .receipt th, .receipt td { padding: 9px 0; border-top: 1px solid var(--border); vertical-align: top; }
    .receipt tr:first-child th, .receipt tr:first-child td { border-top: 0; padding-top: 0; }
    .receipt th { padding-right: 12px; font-weight: 500; text-align: left; }
    .receipt-label { display: flex; align-items: center; gap: 8px; color: var(--text); }
    .receipt th small { display: block; margin-top: 2px; color: var(--muted); font-size: 0.76rem; font-weight: 400; line-height: 1.4; }
    .receipt th .swatch + * , .receipt-label .swatch { margin-top: 0; }
    .receipt td { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

    .receipt-sign {
        display: inline-block;
        width: 1.1em;
        margin-right: 4px;
        color: var(--muted);
        font-weight: 650;
        text-align: center;
    }

    .receipt tr.is-result th, .receipt tr.is-result td { border-top: 2px solid var(--border-strong); font-weight: 650; }
    .receipt tr.is-result th small { font-weight: 400; }
    .receipt tr.is-final th, .receipt tr.is-final td { padding-top: 11px; font-size: 1.02rem; }
    .receipt tr.is-final .receipt-amount { font-size: 1.12rem; }

    .eco-plain {
        margin: 0;
        padding: 12px 14px;
        border-radius: var(--radius-xs);
        background: var(--surface-2);
        color: var(--text);
        font-size: 0.92rem;
        line-height: 1.55;
    }

    .eco-after { margin: 0; }

    /* ---- how many customers it takes ---- */
    .eco-needs {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr));
        gap: 12px;
    }

    .eco-need {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 16px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface-2);
    }

    .eco-need-value { font-size: 1.9rem; font-weight: 650; line-height: 1.05; letter-spacing: -0.025em; }
    .eco-need-value small { color: var(--muted); font-size: 0.82rem; font-weight: 500; letter-spacing: 0; }
    .eco-need strong { font-size: 0.92rem; font-weight: 600; }
    .eco-need p { margin: 0; color: var(--text-2); font-size: 0.84rem; line-height: 1.5; }

    .eco-formula {
        align-self: flex-start;
        padding: 3px 8px;
        border-radius: 5px;
        background: var(--surface-3);
        color: var(--text-2);
        font-size: 0.76rem;
        line-height: 1.5;
        white-space: normal;
    }

    .eco-gap h5 { margin: 0 0 3px; font-size: 0.92rem; font-weight: 600; letter-spacing: -0.005em; }
    .eco-gap .hint { margin: 0 0 10px; }

    .gap-chart { --label-w: 96px; --value-w: 0px; }

    @media (min-width: 760px) {
        .gap-chart { --label-w: 112px; --value-w: 250px; }
    }

    .gap-key {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 18px;
        margin: 0 0 22px;
        padding: 0;
        list-style: none;
        color: var(--text);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .gap-key li { display: inline-flex; align-items: center; gap: 7px; }
    .gap-tick-key { width: 2px; height: 14px; background: var(--text-2); }

    .gap-row {
        display: grid;
        grid-template-columns: var(--label-w) minmax(0, 1fr);
        align-items: center;
        row-gap: 2px;
        padding: 7px 0;
    }

    .gap-label { color: var(--text-2); font-size: 0.82rem; }
    .gap-track { position: relative; display: block; height: 16px; background: var(--surface-3); border-radius: 0 4px 4px 0; }

    /* how many it takes, marked on every bar; the number is written once */
    .gap-tick { position: absolute; top: -5px; bottom: -5px; width: 2px; margin-left: -1px; background: var(--text-2); }
    .gap-tick.is-bills { left: var(--bills); }
    .gap-tick.is-build { left: var(--build); }

    .gap-row:first-of-type .gap-tick::before {
        content: attr(data-n);
        position: absolute;
        bottom: calc(100% + 1px);
        left: 50%;
        transform: translateX(-50%);
        color: var(--text);
        font-size: 0.72rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        line-height: 1;
    }

    .gap-bar { display: block; height: 100%; min-width: 3px; border-radius: 0 4px 4px 0; background: var(--eco-line); }
    .gap-value { grid-column: 2; color: var(--text-2); font-size: 0.8rem; line-height: 1.35; }
    .gap-value b { color: var(--text); font-weight: 650; font-variant-numeric: tabular-nums; }
    .gap-value small { font-size: inherit; }

    @media (min-width: 760px) {
        .gap-row { grid-template-columns: var(--label-w) minmax(0, 1fr) var(--value-w); }
        .gap-value { grid-column: auto; padding-left: 12px; }
    }

    /* ---- when the money comes back ---- */
    .cash-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 18px;
        margin: 0;
        padding: 0;
        list-style: none;
        color: var(--text-2);
        font-size: 0.8rem;
    }

    .cash-legend li { display: inline-flex; align-items: center; gap: 7px; }
    .cash-legend li.is-selected { color: var(--text); font-weight: 600; }
    .cash-key { width: 18px; height: 2px; border-radius: 2px; background: var(--eco-other); }
    .cash-legend li.is-selected .cash-key { height: 3px; background: var(--eco-line); }

    .cash {
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr);
        column-gap: 6px;
    }

    .cash-y { position: relative; grid-row: 1; }

    .cash-y span {
        position: absolute;
        right: 0;
        transform: translateY(-50%);
        color: var(--muted);
        font-size: 0.7rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .cash-y span.is-zero { color: var(--text); font-weight: 650; }
    .cash-y span.cash-unit { top: -30px; transform: none; font-weight: 650; letter-spacing: 0.04em; }

    .cash { padding-top: 30px; }

    .cash-plot {
        position: relative;
        grid-column: 2;
        grid-row: 1;
        height: 230px;
        margin-right: 8px;
        cursor: crosshair;
        touch-action: pan-y;
    }

    @media (min-width: 760px) {
        .cash-plot { height: 300px; }
    }

    .cash-plot:focus-visible { outline: 2px solid var(--accent); outline-offset: 6px; border-radius: 4px; }
    .cash-grid { position: absolute; left: 0; right: 0; height: 1px; background: var(--border); }
    .cash-grid.is-zero { background: var(--text-2); }

    .cash-zero-label {
        position: absolute;
        right: 0;
        transform: translateY(-120%);
        padding: 0 4px;
        border-radius: 4px;
        background: var(--surface);
        color: var(--text-2);
        font-size: 0.7rem;
        font-weight: 600;
    }

    .cash-plot svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
    .cash-line { fill: none; stroke: var(--eco-line); stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; vector-effect: non-scaling-stroke; }
    .cash-line.is-other { stroke: var(--eco-other); stroke-width: 1.5; }
    .cash-area { fill: var(--eco-line); opacity: 0.1; }

    .cash-dot {
        position: absolute;
        width: 12px;
        height: 12px;
        border: 2px solid var(--surface);
        border-radius: 999px;
        background: var(--eco-line);
        transform: translate(-50%, -50%);
        pointer-events: none;
    }

    .cash-dot.is-bills, .cash-dot.is-back { width: 14px; height: 14px; box-shadow: 0 0 0 2px var(--eco-line); }
    .cash-dot.is-cursor { width: 14px; height: 14px; z-index: 2; }
    .cash-dot[hidden], .cash-cursor[hidden], .cash-tip[hidden] { display: none; }

    .cash-cursor {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 1px;
        background: var(--border-strong);
        pointer-events: none;
    }

    .cash-tip {
        position: absolute;
        z-index: 3;
        min-width: 200px;
        max-width: 260px;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: var(--radius-xs);
        background: var(--surface);
        box-shadow: var(--shadow-lg);
        font-size: 0.78rem;
        line-height: 1.4;
        pointer-events: none;
    }

    .cash-tip strong { display: block; margin-bottom: 6px; font-size: 0.82rem; }
    .cash-tip dl { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 3px 12px; margin: 0; }
    .cash-tip dt { color: var(--muted); }
    .cash-tip dd { margin: 0; font-weight: 650; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    .cash-x { position: relative; grid-column: 2; height: 22px; margin-right: 8px; }

    .cash-x span {
        position: absolute;
        top: 6px;
        transform: translateX(-50%);
        color: var(--muted);
        font-size: 0.7rem;
        font-variant-numeric: tabular-nums;
    }

    .cash-x span:first-child { transform: none; }
    .cash-x span:last-child { transform: translateX(-100%); }
    .cash-axis { grid-column: 2; margin: 2px 0 0; color: var(--muted); font-size: 0.72rem; text-align: center; }
    .cash-hint { margin: 0; }

    @media (hover: none) {
        .cash-hint { display: none; }
    }

    .cash-story {
        display: grid;
        gap: 0;
        margin: 0;
        padding: 0;
        list-style: none;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
    }

    .cash-story li {
        display: grid;
        grid-template-columns: 96px minmax(0, 1fr);
        gap: 12px;
        padding: 11px 14px;
        border-top: 1px solid var(--border);
        line-height: 1.45;
    }

    .cash-story li:first-child { border-top: 0; }
    .cash-when { font-weight: 650; font-variant-numeric: tabular-nums; }
    .cash-story li.is-back { background: color-mix(in srgb, var(--ok-fill) 10%, transparent); }
    .cash-story li.is-back .cash-when { color: var(--ok); }
    .cash-story li.is-never { background: color-mix(in srgb, var(--warn-fill) 10%, transparent); }

    .eco-fold.is-inner { padding: 0; border: 0; }

    /* ---- a verdict: an icon and a sentence, never a colour alone ---- */
    .verdict {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin: 0;
        padding: 9px 11px;
        border-radius: var(--radius-xs);
        font-size: 0.86rem;
        font-weight: 600;
        line-height: 1.4;
        --tone: var(--warn-fill);
        background: color-mix(in srgb, var(--tone) 12%, transparent);
    }

    .verdict .icon { flex: none; width: 17px; height: 17px; margin-top: 1px; }
    .verdict.is-good { --tone: var(--ok-fill); color: var(--ok); }
    .verdict.is-mid { --tone: var(--warn-fill); color: var(--warn); }
    .verdict.is-bad { --tone: var(--danger-fill); color: var(--danger); }
    .verdict span { color: var(--text); }

    .plan-line { border: 1px solid var(--border); border-radius: var(--radius-sm); }

    .eco-meter { height: 6px; margin-top: 10px; }

    /* ---- the words ---- */
    .eco-words {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr));
        gap: 0 28px;
        margin: 0;
    }

    .eco-words > div { padding: 12px 0; border-top: 1px solid var(--border); }
    .eco-words dt { font-weight: 650; font-size: 0.92rem; }
    .eco-words dd { margin: 3px 0 0; color: var(--text-2); font-size: 0.86rem; line-height: 1.5; }

    .eco-here {
        display: inline-block;
        margin-left: 2px;
        padding: 0 7px;
        border-radius: 5px;
        background: var(--accent-soft);
        color: var(--accent-strong);
        font-size: 0.78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .bar-fill.k-work { background: var(--eco-work); }

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

    // A fold the reader opened stays open when a control redraws the page.
    const openFolds = () => [...panel.querySelectorAll('details[data-fold]')]
        .filter((fold) => fold.open)
        .map((fold) => fold.dataset.fold);

    const refresh = async (focusName) => {
        const query = changedParams().toString();
        const token = ++pending;
        const folds = openFolds();

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

            panel.querySelectorAll('details[data-fold]').forEach((fold) => {
                if (folds.includes(fold.dataset.fold)) {
                    fold.open = true;
                }
            });

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

    // ---- what a control changes, read where the eye already is ----
    // The panel is redrawn by every change, so everything below listens on
    // the panel itself and looks for its target when the event arrives.
    const explain = (source) => {
        const line = panel.querySelector('[data-eco-help-line]');

        if (! line) {
            return;
        }

        const text = source ? source.dataset.ecoHelp : '';

        line.querySelector('span').textContent = text || line.dataset.ecoHelpIdle || '';
        line.classList.toggle('is-live', Boolean(text));
    };

    const helpOf = (target) => {
        if (! (target instanceof Element)) {
            return null;
        }

        const holder = target.closest('#eco-controls label');

        return holder ? holder.querySelector('[data-eco-help]') : null;
    };

    panel.addEventListener('focusin', (event) => explain(helpOf(event.target)));
    panel.addEventListener('focusout', () => explain(null));
    panel.addEventListener('mouseover', (event) => {
        const source = helpOf(event.target);

        if (source) {
            explain(source);
        }
    });
    panel.addEventListener('mouseout', (event) => {
        if (helpOf(event.target) && ! helpOf(event.relatedTarget)) {
            explain(helpOf(document.activeElement));
        }
    });

    // ---- the chart of the money coming back: any month, by pointer or keys ----
    const readings = new WeakMap();

    const chartOf = (plot) => {
        if (readings.has(plot)) {
            return readings.get(plot);
        }

        const chart = plot.closest('[data-cash]');
        let months = [];
        let labels = {};

        try {
            months = JSON.parse(chart.dataset.months || '[]');
            labels = JSON.parse(chart.dataset.labels || '{}');
        } catch (error) {}

        const reading = { months, labels, at: -1 };

        readings.set(plot, reading);

        return reading;
    };

    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    const show = (plot, index) => {
        const reading = chartOf(plot);
        const point = reading.months[index];
        const cursor = plot.querySelector('[data-cash-cursor]');
        const dot = plot.querySelector('[data-cash-dot]');
        const tip = plot.querySelector('[data-cash-tip]');

        if (! point || ! cursor || ! dot || ! tip) {
            return;
        }

        reading.at = index;
        cursor.style.left = point.x + '%';
        dot.style.left = point.x + '%';
        dot.style.top = point.y + '%';

        const rows = point.month === 0
            ? [[reading.labels.total, point.total]]
            : [
                [reading.labels.customers, point.customers],
                [reading.labels.income, point.income],
                [reading.labels.left, point.left],
                [reading.labels.total, point.total],
            ];

        tip.innerHTML = '<strong>' + escapeHtml(point.month === 0 ? reading.labels.start : reading.labels.month + ' ' + point.month) + '</strong><dl>'
            + rows.map((row) => '<dt>' + escapeHtml(row[0]) + '</dt><dd>' + escapeHtml(row[1]) + '</dd>').join('')
            + '</dl>';

        cursor.hidden = false;
        dot.hidden = false;
        tip.hidden = false;

        // Beside the point, on the side with room, and never over the line.
        const width = plot.clientWidth;
        const x = point.x / 100 * width;
        const onLeft = x > width / 2;

        tip.style.left = onLeft ? 'auto' : Math.min(width - tip.offsetWidth, x + 14) + 'px';
        tip.style.right = onLeft ? Math.min(width - tip.offsetWidth, width - x + 14) + 'px' : 'auto';
        tip.style.top = Math.max(0, Math.min(plot.clientHeight - tip.offsetHeight, point.y / 100 * plot.clientHeight - tip.offsetHeight - 14)) + 'px';
    };

    const hide = (plot) => {
        plot.querySelectorAll('[data-cash-cursor], [data-cash-dot], [data-cash-tip]').forEach((node) => {
            node.hidden = true;
        });
        chartOf(plot).at = -1;
    };

    const plotOf = (target) => (target instanceof Element ? target.closest('[data-cash-plot]') : null);

    panel.addEventListener('pointermove', (event) => {
        const plot = plotOf(event.target);

        if (! plot) {
            return;
        }

        const box = plot.getBoundingClientRect();
        const months = chartOf(plot).months.length - 1;
        const index = Math.round(Math.max(0, Math.min(1, (event.clientX - box.left) / box.width)) * months);

        show(plot, index);
    });

    panel.addEventListener('pointerleave', (event) => {
        const plot = plotOf(event.target);

        if (plot && event.target === plot && document.activeElement !== plot) {
            hide(plot);
        }
    }, true);

    panel.addEventListener('focusin', (event) => {
        const plot = plotOf(event.target);

        if (plot && chartOf(plot).at < 0) {
            show(plot, chartOf(plot).months.length - 1);
        }
    });

    panel.addEventListener('focusout', (event) => {
        const plot = plotOf(event.target);

        if (plot) {
            hide(plot);
        }
    });

    panel.addEventListener('keydown', (event) => {
        const plot = plotOf(event.target);

        if (! plot) {
            return;
        }

        const reading = chartOf(plot);
        const last = reading.months.length - 1;
        const moves = { ArrowLeft: -1, ArrowRight: 1, PageUp: -6, PageDown: 6 };
        let next = null;

        if (event.key in moves) {
            next = reading.at + moves[event.key];
        } else if (event.key === 'Home') {
            next = 0;
        } else if (event.key === 'End') {
            next = last;
        } else if (event.key === 'Escape') {
            hide(plot);
            plot.blur();

            return;
        }

        if (next !== null) {
            event.preventDefault();
            show(plot, Math.max(0, Math.min(last, next)));
        }
    });
})();
</script>
@endpush
