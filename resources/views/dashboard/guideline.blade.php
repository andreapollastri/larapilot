@extends('larapilot::dashboard.layout')

@section('title', $guideline['file'].' — Skills')

@push('styles')
<style>
    .skill-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px 16px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--muted);
        font-size: 0.86rem;
        font-weight: 500;
        text-decoration: none;
    }

    .back-link:hover { color: var(--accent); text-decoration: none; }
    .back-link .icon { width: 15px; height: 15px; }

    .skill-head { padding: 20px 18px; margin-bottom: 18px; }

    @media (min-width: 640px) {
        .skill-head { padding: 24px 28px; }
    }

    .skill-head h2 { margin: 0 0 6px; font-family: var(--mono); font-size: 1.3rem; letter-spacing: -0.01em; overflow-wrap: anywhere; }
    .skill-head .about { margin: 10px 0 0; color: var(--text-2); font-size: 0.98rem; line-height: 1.6; max-width: 84ch; }

    .skill-facts {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 22px;
        margin: 16px 0 0;
        padding: 14px 0 0;
        border-top: 1px solid var(--border);
        list-style: none;
        color: var(--muted);
        font-size: 0.8rem;
    }

    .skill-facts b { color: var(--text-2); font-weight: 600; }
    .skill-facts code { padding: 0; background: transparent; font-size: 0.78rem; overflow-wrap: anywhere; }

    .skill-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 999px;
        font-family: var(--font);
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
        vertical-align: middle;
        --tone: var(--status-todo);
        background: color-mix(in srgb, var(--tone) 15%, transparent);
        color: color-mix(in srgb, var(--tone) 54%, var(--text));
    }

    .skill-chip::before { content: ''; width: 6px; height: 6px; border-radius: 999px; background: var(--tone); }
    .skill-chip.registered { --tone: var(--status-done); }
    .skill-chip.hand { --tone: var(--status-progress); }
    .skill-chip.package { --tone: var(--violet); }
    .skill-chip.boost { --tone: var(--accent); }

    .skill-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    @media (min-width: 1000px) {
        .skill-layout { grid-template-columns: 270px minmax(0, 1fr); gap: 22px; }
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
    .toc ul { list-style: none; margin: 0; padding: 0 8px 12px; }

    .toc a {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        padding: 6px 9px;
        border-radius: var(--radius-xs);
        color: var(--text-2);
        font-size: 0.84rem;
        line-height: 1.35;
        text-decoration: none;
        overflow-wrap: anywhere;
    }

    .toc a small { flex: none; color: var(--muted); font-size: 0.7rem; }
    .toc a:hover { background: var(--surface-3); color: var(--text); }
    .toc a.is-here { background: var(--accent-soft); color: var(--accent-strong); font-weight: 600; }
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

    .guide-parts { display: flex; flex-direction: column; gap: 16px; }

    .guide-part { padding: 0; scroll-margin-top: 84px; }

    .guide-part > header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px 14px;
        flex-wrap: wrap;
        padding: 14px 18px;
        border-bottom: 1px solid var(--border);
        background: var(--surface-2);
        border-radius: var(--radius) var(--radius) 0 0;
    }

    .guide-part > header h3 { margin: 0; font-family: var(--mono); font-size: 0.95rem; overflow-wrap: anywhere; }
    .guide-part > header p { margin: 2px 0 0; color: var(--muted); font-size: 0.78rem; }

    .skill-body { padding: 22px 18px; }

    @media (min-width: 640px) {
        .guide-part > header { padding: 16px 28px; }
        .skill-body { padding: 26px 28px; }
    }

    .skill-body.markdown { font-size: 0.95rem; line-height: 1.7; }
    .skill-body.markdown > :is(p, ul, ol, blockquote) { max-width: 82ch; }
    .skill-body.markdown > :first-child { margin-top: 0; }
    .skill-body.markdown h2 { margin-top: 2rem; font-size: 1.15rem; }
    .skill-body.markdown h3 { font-size: 1rem; }
    .skill-body.markdown li { margin: 0.4rem 0; }
    .skill-body.markdown pre { white-space: pre-wrap; overflow-wrap: anywhere; }
    .skill-body :is(h1, h2, h3, h4) { scroll-margin-top: 84px; }

    @media print {
        .skill-topbar, .toc { display: none; }
    }
</style>
@endpush

@section('content')
    @php
        $guides = \Larapilot\Services\AgentGuidelineService::class;
        $sections = $guideline['sections'];
        $size = static fn (int $bytes): string => $bytes < 1024
            ? $bytes.' B'
            : rtrim(rtrim(number_format($bytes / 1024, 1, '.', ','), '0'), '.').' KB';

        $authors = [
            $guides::BOOST => ['Boost', 'boost', 'Written by Laravel Boost'],
            $guides::LARAPILOT => ['Larapilot', '', 'Written by Larapilot'],
            $guides::PACKAGE => ['Package', 'package', 'Written by a package of the project'],
            $guides::PROJECT => ['Project', 'registered', 'Written by the team, in .ai/guidelines/'],
            $guides::HAND => ['By hand', 'hand', 'Outside what Boost writes: by hand, or by another tool'],
        ];

        // The title of a document that is read whole is already the title
        // of the page.
        $html = $sections === []
            ? (preg_replace('/^\s*<h1[^>]*>.*?<\/h1>\s*/s', '', $guideline['html'], 1) ?? $guideline['html'])
            : '';
    @endphp

    <div class="skill-topbar">
        <a class="back-link" href="{{ route('larapilot.dashboard.skills') }}#skills-told-title">@include('larapilot::dashboard.partials.icon', ['name' => 'back'])All skills</a>
    </div>

    <header class="card skill-head">
        <h2>{{ $guideline['file'] }}</h2>
        @if ($guideline['managed'])
            <span class="skill-chip boost">Written by Boost</span>
        @elseif ($guideline['kind'] === 'source')
            <span class="skill-chip registered">Project</span>
        @else
            <span class="skill-chip hand">By hand</span>
        @endif
        <p class="about">Read by {{ $guideline['reader'] }}.
            @if ($guideline['managed'])
                Boost writes a part of this file for each package that brings guidelines, and writes it again at every <code>boost:update</code>: a change made by hand inside those parts is lost. What is before and after them is kept.
            @elseif ($guideline['template'])
                This is a template: Boost fills it in when it writes it into the files of the agents, so it is shown as it is written.
            @elseif ($guideline['kind'] === 'source')
                Boost writes it into the files of the agents at the next <code>boost:update</code>.
            @else
                Boost wrote nothing here: the file is as somebody wrote it.
            @endif
        </p>
        <ul class="skill-facts">
            <li>{{ number_format($guideline['lines']) }} lines · {{ $size($guideline['bytes']) }}</li>
            @if ($guideline['modified'])
                <li>Changed {{ \Illuminate\Support\Carbon::createFromTimestamp($guideline['modified'])->format('M j, Y') }}</li>
            @endif
            @if ($sections !== [])
                <li><b>{{ count($sections) }} {{ count($sections) === 1 ? 'part' : 'parts' }}</b></li>
            @endif
            @foreach ($guideline['front_matter'] as $key => $value)
                <li>{{ $key }}: <code>{{ \Illuminate\Support\Str::limit($value, 120) }}</code></li>
            @endforeach
        </ul>
    </header>

    @if ($sections !== [])
        <div class="skill-layout">
            <details class="card toc" open data-toc>
                <summary>In this file @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
                <ul>
                    @foreach ($sections as $section)
                        <li><a href="#{{ $section['id'] }}"><span>{{ $section['key'] }}</span><small>{{ $authors[$section['origin']][0] }}</small></a></li>
                    @endforeach
                </ul>
            </details>

            <div class="guide-parts" id="skill-body">
                @foreach ($sections as $section)
                    <section class="card guide-part" id="{{ $section['id'] }}" data-part>
                        <header>
                            <div>
                                <h3>{{ $section['key'] }}</h3>
                                <p>{{ $authors[$section['origin']][2] }}{{ $section['origin'] === $guides::PACKAGE ? ': '.$section['source'] : '' }} · {{ $size($section['bytes']) }}</p>
                            </div>
                            <span class="skill-chip {{ $authors[$section['origin']][1] }}">{{ $authors[$section['origin']][0] }}</span>
                        </header>
                        <article class="skill-body markdown">
                            {!! $section['html'] !!}
                        </article>
                    </section>
                @endforeach
            </div>
        </div>
    @elseif (trim($html) === '')
        <div class="card empty">
            <p>The file is empty.</p>
        </div>
    @else
        <div class="skill-layout">
            @if ($guideline['headings'] !== [])
                <details class="card toc" open data-toc>
                    <summary>In this file @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
                    <ul>
                        @foreach ($guideline['headings'] as $heading)
                            <li @class(['level-3' => $heading['level'] === 3])>
                                <a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            <div @style(['grid-column: 1 / -1' => $guideline['headings'] === []])>
                <article class="card skill-body markdown" id="skill-body" data-document>
                    {!! $html !!}
                </article>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (() => {
        // On a phone the list of parts starts folded, so the text comes first.
        document.querySelectorAll('[data-toc]').forEach((toc) => {
            const wide = window.matchMedia('(min-width: 1000px)');
            const sync = () => {
                toc.open = wide.matches;
            };

            sync();
            wide.addEventListener?.('change', sync);

            toc.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (!wide.matches) {
                        toc.open = false;
                    }
                });
            });
        });

        // The part being read is marked in the list.
        const links = [...document.querySelectorAll('[data-toc] a')];
        const body = document.getElementById('skill-body');

        if (!body || links.length === 0) {
            return;
        }

        const byId = new Map(links.map((link) => [decodeURIComponent(link.hash.slice(1)), link]));
        const marks = [...body.querySelectorAll('[data-part], h2[id], h3[id]')].filter((mark) => byId.has(mark.id));

        const mark = () => {
            let current = marks[0];

            marks.forEach((item) => {
                if (item.getBoundingClientRect().top <= 120) {
                    current = item;
                }
            });

            links.forEach((link) => link.classList.remove('is-here'));

            if (current) {
                byId.get(current.id)?.classList.add('is-here');
            }
        };

        let waiting = false;

        window.addEventListener('scroll', () => {
            if (waiting) {
                return;
            }

            waiting = true;
            window.requestAnimationFrame(() => {
                waiting = false;
                mark();
            });
        }, { passive: true });

        mark();
    })();
</script>
@endpush
