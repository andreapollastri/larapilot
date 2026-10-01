{{-- Shared by the database list and the table page. --}}
<style>
    .db-dump { justify-content: flex-end; gap: 10px 14px; }
    .db-dump .hint { margin: 0; }

    .db-check {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-2);
        font-size: 0.84rem;
        cursor: pointer;
    }

    .db-check input { width: 16px; height: 16px; margin: 0; accent-color: var(--accent); }

    .db-conn { margin: -8px 0 20px; }
    .db-conn .chip { max-width: 100%; min-width: 0; }
    .db-conn .chip .icon { width: 15px; height: 15px; }
    .db-conn code { padding: 0; background: transparent; color: var(--text-2); font-size: 0.78rem; overflow-wrap: anywhere; }

    .db-error {
        padding: 20px;
        border-color: color-mix(in srgb, var(--danger-fill) 40%, var(--border));
    }

    .db-error h3 { margin: 0 0 6px; color: var(--danger); font-size: 1.05rem; }
    .db-error p { margin: 0 0 12px; color: var(--text-2); font-size: 0.9rem; }

    .db-error pre {
        margin: 0;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface-2);
        font-size: 0.8rem;
        line-height: 1.55;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .db-tag {
        flex: none;
        padding: 1px 8px;
        border-radius: 999px;
        background: var(--surface-3);
        color: var(--muted);
        font-size: 0.64rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .db-schema { color: var(--muted); font-weight: 450; }

    /* ---- the list of tables ---- */
    .db-list { overflow: hidden; }
    .db-list-tools { padding: 12px 14px; border-bottom: 1px solid var(--border); }

    .db-list-head,
    .db-list-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        padding: 10px 14px;
    }

    .db-list-head {
        border-bottom: 1px solid var(--border);
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .db-list-row { border-top: 1px solid var(--border); color: inherit; text-decoration: none; }
    .db-list-head + .db-list-row { border-top: 0; }
    .db-list-row:hover { background: var(--surface-2); text-decoration: none; }

    .db-list-name {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 0;
        color: var(--text);
        font-weight: 550;
    }

    .db-list-row:hover .db-list-name { color: var(--accent); }
    .db-list-name > .icon { color: var(--accent); }
    .db-list-label { min-width: 0; }

    .db-list-label > span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .db-list-label small {
        display: block;
        overflow: hidden;
        color: var(--muted);
        font-size: 0.76rem;
        font-weight: 400;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .db-list-size { color: var(--muted); font-size: 0.82rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .db-list-empty { margin: 0; padding: 28px 20px; color: var(--muted); text-align: center; }

    .db-list-foot {
        padding: 10px 16px;
        border-top: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 0.78rem;
        font-variant-numeric: tabular-nums;
    }

    @media (min-width: 760px) {
        .db-list-head,
        .db-list-row { grid-template-columns: minmax(0, 1fr) 120px; padding: 9px 16px; }

        .db-list-size,
        .db-list-head-size { text-align: right; }
    }
</style>
