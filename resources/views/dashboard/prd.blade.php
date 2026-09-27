@extends('larapilot::dashboard.layout')

@section('title', 'PRD')

@push('styles')
<style>
    .prd-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    @media (min-width: 1000px) {
        .prd-layout { grid-template-columns: 240px minmax(0, 1fr); gap: 22px; }
    }

    .toc { padding: 0; }

    .toc > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 14px 16px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }

    .toc > summary::-webkit-details-marker { display: none; }
    .toc > summary .icon { width: 15px; height: 15px; transition: transform 0.15s ease; }
    .toc[open] > summary .icon { transform: rotate(90deg); }

    .toc ul {
        list-style: none;
        margin: 0;
        padding: 0 8px 12px;
    }

    .toc a {
        display: block;
        padding: 6px 9px;
        border-radius: var(--radius-xs);
        color: var(--text-2);
        font-size: 0.86rem;
        line-height: 1.35;
        text-decoration: none;
    }

    .toc a:hover { background: var(--surface-3); color: var(--text); }
    .toc .level-3 a { padding-left: 22px; color: var(--muted); font-size: 0.82rem; }

    @media (min-width: 1000px) {
        .toc {
            position: sticky;
            top: 80px;
            max-height: calc(100vh - 104px);
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .toc > summary { cursor: default; pointer-events: none; }
        .toc > summary .icon { display: none; }
    }

    .prd-content { padding: 22px 18px; }

    @media (min-width: 640px) {
        .prd-content { padding: 30px 34px; }
    }

    /* the search bar stays in reach while the document scrolls under it */
    .prd-content :is(h1, h2, h3, h4, h5, h6) { scroll-margin-top: 140px; }

    @media (min-width: 1024px) {
        .prd-content :is(h1, h2, h3, h4, h5, h6) { scroll-margin-top: 84px; }
    }

    /* ---- search, in the PRD only ----
       The strip is opaque and reaches the top edge, so the document never
       shows through the gap above the field. */
    .prd-search {
        position: sticky;
        top: 58px;
        z-index: 20;
        margin: -12px 0 6px;
        padding: 12px 0 10px;
        background: var(--bg);
    }

    @media (min-width: 1024px) {
        .prd-search { top: 0; }
    }

    .prd-search-field { position: relative; }

    .prd-search-field > .icon {
        position: absolute;
        left: 14px;
        top: 50%;
        width: 17px;
        height: 17px;
        transform: translateY(-50%);
        color: var(--muted);
        pointer-events: none;
    }

    .prd-search-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 46px 10px 42px;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-sm);
        background: var(--surface);
        color: var(--text);
        font: inherit;
        font-size: 0.95rem;
        box-shadow: 0 6px 18px color-mix(in srgb, var(--text) 7%, transparent);
        -webkit-appearance: none;
        appearance: none;
    }

    .prd-search-field input::placeholder { color: var(--muted); }
    .prd-search-field input::-webkit-search-cancel-button { -webkit-appearance: none; }

    .prd-search-field input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
    }

    .prd-search kbd {
        padding: 1px 6px;
        border: 1px solid var(--border-strong);
        border-bottom-width: 2px;
        border-radius: 5px;
        background: var(--surface-2);
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 600;
    }

    .prd-search-field > kbd {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .prd-search-field input:focus ~ kbd,
    .prd-search.has-found .prd-search-field > kbd { display: none; }

    .prd-search.has-found .prd-search-field input { padding-right: 190px; }

    /* what the last search left highlighted, and the way to clear it */
    .prd-found {
        position: absolute;
        right: 8px;
        top: 50%;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 30px;
        padding: 0 10px 0 11px;
        transform: translateY(-50%);
        border: 1px solid color-mix(in srgb, var(--warn-fill) 50%, var(--border));
        border-radius: 999px;
        background: color-mix(in srgb, var(--warn-fill) 16%, var(--surface));
        color: var(--text);
        font: inherit;
        font-size: 0.78rem;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
    }

    .prd-found[hidden] { display: none; }
    .prd-found .icon { width: 13px; height: 13px; color: var(--muted); }
    .prd-found:hover { border-color: var(--warn-fill); }

    .prd-search-panel {
        position: absolute;
        top: calc(100% - 4px);
        left: 0;
        right: 0;
        display: flex;
        flex-direction: column;
        max-height: min(68vh, 34rem);
        overflow: hidden;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-sm);
        background: var(--surface);
        box-shadow: var(--shadow-lg);
    }

    .prd-search-panel[hidden] { display: none; }

    .prd-search-status {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 14px;
        border-bottom: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 0.76rem;
    }

    .prd-search-status kbd { margin: 0 1px; }

    .prd-search-results {
        list-style: none;
        margin: 0;
        padding: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
    }

    .prd-search-results li + li { border-top: 1px solid var(--border); }

    .prd-search-results a {
        display: block;
        padding: 10px 14px 11px;
        border-left: 3px solid transparent;
        color: inherit;
        text-decoration: none;
    }

    .prd-search-results li[aria-selected="true"] a,
    .prd-search-results a:hover {
        border-left-color: var(--accent);
        background: var(--accent-soft);
    }

    .prd-search-crumb {
        display: block;
        color: var(--accent);
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .prd-search-title { display: block; color: var(--text); font-size: 0.94rem; font-weight: 600; }

    .prd-search-snippet {
        display: -webkit-box;
        margin-top: 2px;
        color: var(--muted);
        font-size: 0.83rem;
        line-height: 1.45;
        overflow: hidden;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .prd-search-count {
        float: right;
        margin-left: 10px;
        color: var(--muted);
        font-size: 0.72rem;
        font-variant-numeric: tabular-nums;
    }

    .prd-search mark,
    .prd-content mark.prd-hit {
        padding: 0;
        border-radius: 3px;
        background: color-mix(in srgb, var(--warn-fill) 34%, transparent);
        color: inherit;
    }

    .prd-search-empty { padding: 14px; color: var(--text-2); font-size: 0.88rem; }

    .prd-search-empty ul {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 10px 0 0;
        padding: 0;
        list-style: none;
    }

    .prd-search-empty li + li { border-top: 0; }

    .prd-search-empty button {
        padding: 4px 11px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--text-2);
        font: inherit;
        font-size: 0.8rem;
        cursor: pointer;
    }

    .prd-search-empty button:hover { border-color: var(--accent); color: var(--accent); }

    .prd-landed { animation: prd-landed 2.2s ease-out 1; border-radius: 4px; }

    @keyframes prd-landed {
        0%, 35% { background: color-mix(in srgb, var(--accent) 22%, transparent); box-shadow: 0 0 0 6px color-mix(in srgb, var(--accent) 22%, transparent); }
        100% { background: transparent; box-shadow: 0 0 0 6px transparent; }
    }

    @media (max-width: 720px) {
        .prd-search-field > kbd,
        .prd-search-status span:last-child { display: none; }
        .prd-search-field input { padding-right: 14px; }
        .prd-search.has-found .prd-search-field input { padding-right: 150px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .prd-landed { animation: none; outline: 2px solid var(--accent); }
    }

    @media print {
        .prd-search { display: none !important; }
    }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>PRD</h2>
            <p class="sub">The product requirements document, as the skills read it from <code>.larapilot/docs/PRD.md</code>.</p>
        </div>
        @if ($prd !== null)
            <div class="page-actions">
                <a class="btn" href="{{ route('larapilot.dashboard.prd.download') }}" title="The whole PRD, exactly as it is on disk">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download PRD (.md)</a>
                <a class="btn ghost" href="{{ route('larapilot.dashboard.prd.summary') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download']){{ $summaryLabel }}</a>
            </div>
        @endif
    </header>

    @if ($prd === null)
        <div class="card empty">
            <p>No PRD found. Run <code>/larapilot-inception</code> to create <code>.larapilot/docs/PRD.md</code>.</p>
        </div>
    @else
        <div class="prd-search" role="search" data-prd-search>
            <div class="prd-search-field">
                @include('larapilot::dashboard.partials.icon', ['name' => 'search'])
                <input type="search" id="prd-search" placeholder="Search the PRD — a requirement, a persona, a word" autocomplete="off" spellcheck="false"
                    role="combobox" aria-label="Search the PRD" aria-expanded="false"
                    aria-controls="prd-search-results" aria-autocomplete="list">
                <kbd aria-hidden="true">/</kbd>
                <button type="button" class="prd-found" id="prd-found" title="Clear the highlights" hidden>
                    <span id="prd-found-text"></span>
                    @include('larapilot::dashboard.partials.icon', ['name' => 'close'])
                </button>
            </div>
            <div class="prd-search-panel" id="prd-search-panel" hidden>
                <div class="prd-search-status">
                    <span id="prd-search-status" aria-live="polite"></span>
                    <span aria-hidden="true"><kbd>↑</kbd><kbd>↓</kbd> move · <kbd>↵</kbd> open · <kbd>esc</kbd> close</span>
                </div>
                <ul class="prd-search-results" id="prd-search-results" role="listbox" aria-label="Search results"></ul>
            </div>
        </div>

        <div class="prd-layout">
            <details class="card toc" open data-toc>
                <summary>Sections @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
                <ul>
                    @foreach ($prd['headings'] as $heading)
                        <li @class(['level-3' => $heading['level'] === 3])>
                            <a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>
                        </li>
                    @endforeach
                    @if (($decisions['entry_count'] ?? 0) > 0)
                        <li>
                            <a href="#decision-journal">Decision journal</a>
                        </li>
                    @endif
                </ul>
            </details>

            <article class="card prd-content markdown" id="prd-content">
                {!! $prd['html'] !!}
            </article>
        </div>
    @endif

    @include('larapilot::dashboard.partials.decisions', ['decisions' => $decisions ?? null])

    @push('scripts')
    <script>
        // On a phone the section list starts folded, so the document comes first.
        document.querySelectorAll('[data-toc]').forEach(function (toc) {
            var wide = window.matchMedia('(min-width: 1000px)');
            var sync = function () {
                toc.open = wide.matches;
            };

            sync();

            if (wide.addEventListener) {
                wide.addEventListener('change', sync);
            }

            toc.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (! wide.matches) {
                        toc.open = false;
                    }
                });
            });
        });

        document.querySelectorAll('.decisions-timeline[data-exclusive-accordion]').forEach(function (accordion) {
            accordion.querySelectorAll('.decision-entry').forEach(function (item) {
                item.addEventListener('toggle', function () {
                    if (! item.open) {
                        return;
                    }

                    accordion.querySelectorAll('.decision-entry').forEach(function (other) {
                        if (other !== item) {
                            other.open = false;
                        }
                    });
                });
            });
        });

        // ---------------------------------------------------------------
        // Search — live, and in the PRD only. The index is built in the
        // browser from the headings of the document and the text under each,
        // so nothing outside the article (menu, decision journal) is found.
        // ---------------------------------------------------------------
        (function () {
            var article = document.getElementById('prd-content');
            var input = document.getElementById('prd-search');
            var panel = document.getElementById('prd-search-panel');
            var list = document.getElementById('prd-search-results');
            var status = document.getElementById('prd-search-status');
            var box = document.querySelector('[data-prd-search]');
            var found = document.getElementById('prd-found');
            var foundText = document.getElementById('prd-found-text');

            if (! article || ! input || ! panel || ! list || ! status) {
                return;
            }

            var HEADINGS = 'h1, h2, h3, h4, h5, h6';
            var LIMIT = 30;

            var fold = function (text) {
                return (text || '').toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '');
            };

            // The same fold one character at a time, so an offset found in the
            // folded text is the same offset in the original.
            var foldKeep = function (text) {
                var out = '';

                for (var i = 0; i < text.length; i++) {
                    var code = text.charCodeAt(i);

                    if (code < 128) {
                        out += code >= 65 && code <= 90 ? String.fromCharCode(code + 32) : text[i];
                        continue;
                    }

                    var lower = text[i].toLowerCase();
                    var plain = lower.normalize('NFKD').replace(/[̀-ͯ]/g, '');

                    out += plain.length === 1 ? plain : (lower.length === 1 ? lower : text[i]);
                }

                return out;
            };

            var squash = function (text) {
                return (text || '').replace(/\s+/g, ' ').trim();
            };

            var escapeHtml = function (text) {
                return text.replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            };

            var index = null;

            var buildIndex = function () {
                if (index) {
                    return index;
                }

                var entries = [];
                var trail = [];
                var current = null;
                var walker = document.createTreeWalker(article, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);

                var skipSubtree = function () {
                    var next = walker.nextSibling();

                    while (! next && walker.parentNode()) {
                        next = walker.nextSibling();
                    }

                    return next;
                };

                var node = walker.nextNode();

                while (node) {
                    if (node.nodeType === Node.ELEMENT_NODE && node.matches(HEADINGS)) {
                        var level = Number(node.tagName.slice(1));
                        var title = squash(node.textContent);

                        trail = trail.filter(function (step) { return step.level < level; });

                        current = {
                            el: node,
                            title: title,
                            // the document title is on every page; it says nothing as a path
                            crumb: trail.filter(function (step) { return step.level > 1; })
                                .map(function (step) { return step.title; }).join(' › '),
                            body: '',
                            order: entries.length,
                        };

                        entries.push(current);
                        trail.push({ level: level, title: title });
                        node = skipSubtree();
                        continue;
                    }

                    if (node.nodeType === Node.TEXT_NODE) {
                        var text = squash(node.nodeValue);

                        if (text) {
                            if (! current) {
                                current = { el: article, title: 'Introduction', crumb: '', body: '', order: entries.length };
                                entries.push(current);
                            }

                            if (current.body.length < 12000) {
                                current.body += (current.body ? ' ' : '') + text;
                            }
                        }
                    }

                    node = walker.nextNode();
                }

                entries.forEach(function (entry) {
                    entry.fTitle = foldKeep(entry.title);
                    entry.fCrumb = foldKeep(entry.crumb);
                    entry.fBody = foldKeep(entry.body);
                });

                index = entries;

                return entries;
            };

            var countOf = function (haystack, needle, cap) {
                var count = 0;

                for (var at = haystack.indexOf(needle); at !== -1 && count < cap; at = haystack.indexOf(needle, at + needle.length)) {
                    count++;
                }

                return count;
            };

            var FILLER = ['a', 'an', 'the', 'to', 'of', 'in', 'on', 'for', 'and', 'or', 'is', 'are', 'it', 'with',
                'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'una', 'di', 'da', 'del', 'della', 'e', 'che', 'per', 'con'];

            var termsOf = function (query) {
                var words = fold(query).split(/\s+/).filter(Boolean);
                var meaningful = words.filter(function (word) { return FILLER.indexOf(word) === -1; });

                return meaningful.length ? meaningful : words;
            };

            var runSearch = function (terms) {
                if (! terms.length) {
                    return [];
                }

                var scored = [];

                buildIndex().forEach(function (entry) {
                    var score = 0;
                    var matches = 0;

                    for (var i = 0; i < terms.length; i++) {
                        var term = terms[i];
                        var hit = 0;
                        var inTitle = entry.fTitle.indexOf(term);

                        if (inTitle !== -1) {
                            var starts = inTitle === 0 || /[^a-z0-9]/.test(entry.fTitle[inTitle - 1]);
                            var after = entry.fTitle[inTitle + term.length];
                            var ends = after === undefined || /[^a-z0-9]/.test(after);

                            hit += starts && ends ? 16 : (starts ? 10 : 6);
                            matches += countOf(entry.fTitle, term, 50);
                        }

                        if (entry.fCrumb.indexOf(term) !== -1) {
                            hit += 3;
                        }

                        var inBody = countOf(entry.fBody, term, 50);

                        if (inBody) {
                            hit += 1 + Math.min(inBody, 6) * 0.4;
                            matches += inBody;
                        }

                        if (! hit) {
                            return;
                        }

                        score += hit;
                    }

                    if (terms.length > 1) {
                        var phrase = terms.join(' ');
                        var loose = function (text) { return text.replace(/[-_/]+/g, ' '); };

                        if (loose(entry.fTitle).indexOf(phrase) !== -1) { score += 12; }
                        if (loose(entry.fBody).indexOf(phrase) !== -1) { score += 5; }
                    }

                    scored.push({ entry: entry, score: score, matches: matches });
                });

                scored.sort(function (a, b) { return b.score - a.score || a.entry.order - b.entry.order; });

                return scored.slice(0, LIMIT);
            };

            var marksIn = function (text, terms) {
                var folded = foldKeep(text);
                var marks = [];

                terms.forEach(function (term) {
                    for (var at = folded.indexOf(term); at !== -1; at = folded.indexOf(term, at + term.length)) {
                        marks.push([at, at + term.length]);
                    }
                });

                marks.sort(function (a, b) { return a[0] - b[0]; });

                return marks.filter(function (mark, i) {
                    return i === 0 || mark[0] >= marks[i - 1][1];
                });
            };

            var highlight = function (text, terms) {
                var out = '';
                var cursor = 0;

                marksIn(text, terms).forEach(function (mark) {
                    if (mark[0] < cursor) {
                        return;
                    }

                    out += escapeHtml(text.slice(cursor, mark[0])) + '<mark>' + escapeHtml(text.slice(mark[0], mark[1])) + '</mark>';
                    cursor = mark[1];
                });

                return out + escapeHtml(text.slice(cursor));
            };

            var snippetOf = function (entry, terms) {
                var body = entry.body;

                if (! body) {
                    return '';
                }

                var at = -1;

                terms.forEach(function (term) {
                    var position = entry.fBody.indexOf(term);

                    if (position !== -1 && (at === -1 || position < at)) {
                        at = position;
                    }
                });

                var from = at > 70 ? body.lastIndexOf(' ', at - 60) + 1 : 0;

                return (from > 0 ? '… ' : '') + body.slice(from, from + 200) + (from + 200 < body.length ? ' …' : '');
            };

            // ---- highlights left in the document after a jump
            var clearHighlights = function () {
                article.querySelectorAll('mark.prd-hit').forEach(function (mark) {
                    var parent = mark.parentNode;

                    parent.replaceChild(document.createTextNode(mark.textContent), mark);
                    parent.normalize();
                });

                if (found) {
                    found.hidden = true;
                }

                if (box) {
                    box.classList.remove('has-found');
                }
            };

            var highlightDocument = function (terms, query) {
                clearHighlights();

                var nodes = [];
                var walker = document.createTreeWalker(article, NodeFilter.SHOW_TEXT);

                for (var node = walker.nextNode(); node; node = walker.nextNode()) {
                    if (node.nodeValue.trim() !== '') {
                        nodes.push(node);
                    }
                }

                var total = 0;

                nodes.forEach(function (node) {
                    var text = node.nodeValue;
                    var marks = marksIn(text, terms);

                    if (! marks.length) {
                        return;
                    }

                    var fragment = document.createDocumentFragment();
                    var cursor = 0;

                    marks.forEach(function (mark) {
                        fragment.appendChild(document.createTextNode(text.slice(cursor, mark[0])));

                        var hit = document.createElement('mark');
                        hit.className = 'prd-hit';
                        hit.textContent = text.slice(mark[0], mark[1]);
                        fragment.appendChild(hit);
                        cursor = mark[1];
                        total++;
                    });

                    fragment.appendChild(document.createTextNode(text.slice(cursor)));
                    node.parentNode.replaceChild(fragment, node);
                });

                if (found && foundText && total > 0) {
                    foundText.textContent = total + (total === 1 ? ' match' : ' matches') + ' highlighted';
                    found.setAttribute('aria-label', total + ' matches for ' + query + ' highlighted. Clear the highlights.');
                    found.hidden = false;

                    if (box) {
                        box.classList.add('has-found');
                    }
                }
            };

            // ---- the panel
            var hits = [];
            var active = -1;

            var setActive = function (position) {
                active = position;

                Array.prototype.forEach.call(list.children, function (item, i) {
                    var on = i === position;

                    item.setAttribute('aria-selected', on ? 'true' : 'false');

                    if (on) {
                        item.scrollIntoView({ block: 'nearest' });
                        input.setAttribute('aria-activedescendant', item.id);
                    }
                });

                if (position < 0) {
                    input.removeAttribute('aria-activedescendant');
                }
            };

            var openPanel = function () {
                panel.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            };

            var closePanel = function () {
                panel.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
            };

            var suggestions = function () {
                return buildIndex()
                    .filter(function (entry) { return entry.el.tagName === 'H2'; })
                    .slice(0, 8)
                    .map(function (entry) { return entry.title; });
            };

            var render = function () {
                var query = input.value.trim();
                var terms = termsOf(query);

                list.innerHTML = '';
                hits = [];
                active = -1;

                if (! terms.length) {
                    var words = suggestions();
                    var intro = document.createElement('li');

                    status.textContent = 'Type to search the PRD — and only the PRD';
                    intro.className = 'prd-search-empty';
                    intro.setAttribute('role', 'presentation');
                    intro.innerHTML = words.length
                        ? 'Jump to a section:<ul>' + words.map(function (word) {
                            return '<li><button type="button" data-suggest="' + escapeHtml(word) + '">' + escapeHtml(word) + '</button></li>';
                        }).join('') + '</ul>'
                        : 'Type a word that appears in the document.';
                    list.appendChild(intro);
                    openPanel();

                    return;
                }

                hits = runSearch(terms);

                var total = hits.reduce(function (sum, hit) { return sum + hit.matches; }, 0);

                status.textContent = hits.length
                    ? total + (total === 1 ? ' match' : ' matches') + ' in ' + hits.length
                        + (hits.length === 1 ? ' section' : ' sections') + ' for “' + query + '”'
                    : 'Nothing for “' + query + '”';

                if (! hits.length) {
                    var empty = document.createElement('li');

                    empty.className = 'prd-search-empty';
                    empty.setAttribute('role', 'presentation');
                    empty.innerHTML = 'The PRD never mentions <mark>' + escapeHtml(query) + '</mark>. Try fewer words, or a requirement id such as <code>FR-001</code>.';
                    list.appendChild(empty);
                    openPanel();

                    return;
                }

                hits.forEach(function (hit, i) {
                    var entry = hit.entry;
                    var item = document.createElement('li');

                    item.id = 'prd-search-hit-' + i;
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', 'false');
                    item.innerHTML = '<a href="#' + encodeURIComponent(entry.el.id || '') + '" data-hit="' + i + '" tabindex="-1">'
                        + '<span class="prd-search-count">' + hit.matches + '×</span>'
                        + (entry.crumb ? '<span class="prd-search-crumb">' + escapeHtml(entry.crumb) + '</span>' : '')
                        + '<span class="prd-search-title">' + highlight(entry.title, terms) + '</span>'
                        + '<span class="prd-search-snippet">' + highlight(snippetOf(entry, terms), terms) + '</span></a>';
                    list.appendChild(item);
                });

                setActive(0);
                openPanel();
            };

            var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            var goTo = function (hit) {
                var query = input.value.trim();
                var target = hit.entry.el;

                closePanel();
                input.blur();
                highlightDocument(termsOf(query), query);

                // Land on the first match of the section, or on its heading.
                var landing = target;

                if (target !== article && foldKeep(hit.entry.title).indexOf(termsOf(query)[0]) === -1) {
                    for (var next = target.nextElementSibling; next && ! next.matches(HEADINGS); next = next.nextElementSibling) {
                        var mark = next.matches('mark.prd-hit') ? next : next.querySelector('mark.prd-hit');

                        if (mark) {
                            landing = mark;
                            break;
                        }
                    }
                }

                if (target.id && document.querySelectorAll('#' + CSS.escape(target.id)).length === 1) {
                    history.replaceState(null, '', '#' + encodeURIComponent(target.id));
                }

                (landing === target ? target : landing).scrollIntoView({
                    behavior: reduced ? 'auto' : 'smooth',
                    block: landing === target ? 'start' : 'center',
                });

                target.classList.remove('prd-landed');
                void target.offsetWidth;
                target.classList.add('prd-landed');
                window.setTimeout(function () { target.classList.remove('prd-landed'); }, 2400);
            };

            var debounce = 0;

            input.addEventListener('input', function () {
                window.clearTimeout(debounce);
                debounce = window.setTimeout(render, 60);
            });

            input.addEventListener('focus', render);

            input.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    if (! hits.length) {
                        return;
                    }

                    event.preventDefault();
                    setActive((active + (event.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length);
                } else if (event.key === 'Enter') {
                    var hit = hits[active] || hits[0];

                    if (hit) {
                        event.preventDefault();
                        goTo(hit);
                    }
                } else if (event.key === 'Escape') {
                    event.preventDefault();

                    if (input.value) {
                        input.value = '';
                        clearHighlights();
                        render();
                    } else {
                        closePanel();
                        input.blur();
                    }
                }
            });

            list.addEventListener('click', function (event) {
                var suggestion = event.target.closest('[data-suggest]');

                if (suggestion) {
                    input.value = suggestion.getAttribute('data-suggest');
                    input.focus();
                    render();

                    return;
                }

                var link = event.target.closest('a[data-hit]');

                if (! link) {
                    return;
                }

                event.preventDefault();

                var hit = hits[Number(link.getAttribute('data-hit'))];

                if (hit) {
                    goTo(hit);
                }
            });

            list.addEventListener('mousemove', function (event) {
                var link = event.target.closest('a[data-hit]');

                if (link && Number(link.getAttribute('data-hit')) !== active) {
                    setActive(Number(link.getAttribute('data-hit')));
                }
            });

            if (found) {
                found.addEventListener('click', function () {
                    input.value = '';
                    clearHighlights();
                    closePanel();
                });
            }

            document.addEventListener('click', function (event) {
                if (! event.target.closest('[data-prd-search]')) {
                    closePanel();
                }
            });

            document.addEventListener('keydown', function (event) {
                var tag = event.target.tagName || '';
                var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(tag) || event.target.isContentEditable;
                var slash = event.key === '/' && ! typing && ! event.metaKey && ! event.ctrlKey && ! event.altKey;
                var palette = (event.key === 'k' || event.key === 'K') && (event.metaKey || event.ctrlKey);

                if (slash || palette) {
                    event.preventDefault();
                    input.focus();
                    input.select();
                }
            });

            (window.requestIdleCallback || function (run) { return window.setTimeout(run, 800); })(buildIndex);

            var asked = new URLSearchParams(window.location.search).get('q');

            if (asked) {
                input.value = asked;
                input.focus();
                render();
            }
        })();
    </script>
    @endpush
@endsection
