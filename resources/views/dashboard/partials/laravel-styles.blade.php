{{-- Shared by the tabs of the Laravel page. --}}
<style>
    .lv-tabs { display: flex; gap: 2px; width: fit-content; max-width: 100%; margin: -6px 0 20px; padding: 4px; overflow-x: auto; scrollbar-width: none; border: 1px solid var(--border); border-radius: 999px; background: var(--surface-2); }
    .lv-tabs::-webkit-scrollbar { display: none; }
    .lv-tabs a { display: inline-flex; flex: none; align-items: center; min-height: 34px; padding: 0 13px; border-radius: 999px; color: var(--text-2); font-size: 0.84rem; font-weight: 600; white-space: nowrap; text-decoration: none; }
    .lv-tabs a:hover { background: var(--surface-3); color: var(--text); text-decoration: none; }
    .lv-tabs a.is-current { background: var(--surface); color: var(--accent-strong); box-shadow: var(--shadow); }

    a.lv-metric { display: block; color: inherit; text-decoration: none; transition: border-color 0.15s ease; }
    a.lv-metric:hover { border-color: color-mix(in srgb, var(--accent) 55%, var(--border)); text-decoration: none; }
    .lv-metric .metric-value.is-alert { color: var(--danger); }
    .lv-metric .metric-note { overflow-wrap: anywhere; }

    .lv-grid { display: grid; gap: 14px; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1100px) { .lv-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .lv-grid > .card { min-width: 0; }

    .lv-panel { padding: 0; overflow: hidden; }
    .lv-panel-head { display: flex; align-items: baseline; justify-content: space-between; gap: 6px 14px; flex-wrap: wrap; padding: 16px 18px 12px; }
    .lv-panel-head h3 { margin: 0; font-size: 1rem; }
    .lv-panel-head .hint { margin: 0; }
    .lv-panel + .lv-panel { margin-top: 14px; }
    .lv-grid .lv-panel + .lv-panel { margin-top: 0; }

    .lv-rows { margin: 0; padding: 0; list-style: none; }
    .lv-row { display: grid; grid-template-columns: minmax(0, 1fr); gap: 4px 16px; padding: 12px 18px; border-top: 1px solid var(--border); }
    @media (min-width: 560px) { .lv-row { grid-template-columns: minmax(104px, 24%) minmax(0, 1fr); } }
    .lv-row-label { color: var(--muted); font-size: 0.84rem; font-weight: 600; }
    .lv-row-body { min-width: 0; display: flex; flex-direction: column; gap: 5px; font-size: 0.88rem; overflow-wrap: anywhere; }
    .lv-row-main { display: flex; align-items: center; gap: 6px 8px; flex-wrap: wrap; }
    .lv-row-main strong { font-weight: 600; }
    .lv-row-body .hint { margin: 0; }
    .lv-row-body code { white-space: normal; overflow-wrap: anywhere; }
    .lv-warn { color: var(--warn); font-size: 0.82rem; line-height: 1.5; }

    /* A state is a word beside a colour, never the colour alone. */
    .lv-state { --tone: var(--status-todo); display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px 2px 9px; border-radius: 999px; background: color-mix(in srgb, var(--tone) 16%, transparent); color: color-mix(in srgb, var(--tone) 55%, var(--text)); font-size: 0.72rem; font-weight: 650; white-space: nowrap; }
    .lv-state::before { content: ''; flex: none; width: 7px; height: 7px; border-radius: 999px; background: var(--tone); }
    .lv-state.is-on { --tone: var(--ok-fill); }
    .lv-state.is-warn { --tone: var(--warn-fill); }
    .lv-state.is-bad { --tone: var(--danger-fill); }
    .lv-state.is-busy { --tone: var(--sky-fill); }

    .lv-tag { display: inline-flex; align-items: center; padding: 1px 8px; border: 1px solid var(--border); border-radius: 999px; color: var(--text-2); font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
    .lv-tag.is-off { border-style: dashed; color: var(--muted); }
    .lv-tags { display: flex; flex-wrap: wrap; gap: 5px; }

    /* On a phone a table keeps its width and scrolls inside its card. */
    .lv-table { width: 100%; min-width: 660px; border-collapse: collapse; font-size: 0.86rem; }
    .lv-table.is-compact { min-width: 0; }
    .lv-table.is-compact td code { white-space: nowrap; }
    .lv-table th, .lv-table td { padding: 10px 18px; border-top: 1px solid var(--border); text-align: left; vertical-align: top; }
    .lv-table thead th { color: var(--muted); font-size: 0.7rem; font-weight: 650; letter-spacing: 0.06em; text-transform: uppercase; white-space: nowrap; }
    .lv-table td.num, .lv-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .lv-table td small { display: block; margin-top: 3px; color: var(--muted); font-size: 0.8rem; line-height: 1.45; overflow-wrap: anywhere; }
    .lv-table td code { overflow-wrap: anywhere; }
    .lv-table .nowrap { white-space: nowrap; }
    .lv-table tr.is-off td { color: var(--muted); }
    .lv-job { font-family: var(--mono); font-size: 0.82rem; overflow-wrap: anywhere; }

    .lv-bar { display: flex; align-items: center; gap: 6px 8px; flex-wrap: wrap; margin: -6px 0 18px; }
    .lv-bar .chip { text-decoration: none; }
    .lv-bar a.chip:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
    .lv-bar code { padding: 0; background: transparent; font-size: 0.78rem; }

    .lv-note { margin: 0; padding: 12px 18px; border-top: 1px solid var(--border); color: var(--muted); font-size: 0.82rem; line-height: 1.55; }
    .lv-note code { white-space: normal; overflow-wrap: anywhere; }
    .lv-error { margin: 0; padding: 12px 18px; border-top: 1px solid var(--border); color: var(--danger); font-size: 0.86rem; overflow-wrap: anywhere; }

    .lv-list { overflow: hidden; }
    .lv-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 16px; align-items: baseline; padding: 12px 18px; border-top: 1px solid var(--border); color: inherit; text-decoration: none; }
    .lv-item:first-child { border-top: 0; }
    a.lv-item:hover { background: var(--surface-2); text-decoration: none; }
    .lv-item-title { min-width: 0; color: var(--text); font-weight: 600; overflow-wrap: anywhere; }
    .lv-item-when { color: var(--muted); font-size: 0.8rem; white-space: nowrap; }
    .lv-item-meta { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 4px 12px; color: var(--muted); font-size: 0.82rem; overflow-wrap: anywhere; }
    .lv-item-meta code { padding: 0; background: transparent; }

    .lv-dump { margin: 0; padding: 12px 18px 14px; border-top: 1px solid var(--border); background: var(--surface-2); font-size: 0.8rem; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; max-height: 420px; overflow-y: auto; }
    .lv-dump-entry + .lv-dump-entry { margin-top: 12px; }
    .lv-dump-entry { overflow: hidden; }

    .lv-mail-facts { margin: 0; font-size: 0.88rem; }
    .lv-mail-facts div { display: grid; grid-template-columns: minmax(84px, 18%) minmax(0, 1fr); gap: 12px; padding: 8px 0; border-top: 1px solid var(--border); }
    .lv-mail-facts div:first-child { border-top: 0; padding-top: 0; }
    .lv-mail-facts dt { color: var(--muted); }
    .lv-mail-facts dd { margin: 0; overflow-wrap: anywhere; }

    /* A mail is written for a white page, whatever the theme of the dashboard. */
    .lv-mail-frame { display: block; width: 100%; height: min(78vh, 900px); border: 0; border-top: 1px solid var(--border); background: #fff; color-scheme: light; }
    .lv-mail-text { margin: 0; padding: 16px 18px; border-top: 1px solid var(--border); font-size: 0.84rem; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
