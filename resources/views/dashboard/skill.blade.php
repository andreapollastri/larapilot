@extends('larapilot::dashboard.layout')

@section('title', ($skill['title'] ?? $skill['name']).' — Skills')

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

    .skill-head h2 { margin: 0 0 6px; font-size: 1.5rem; letter-spacing: -0.022em; overflow-wrap: anywhere; }

    .skill-head .trigger {
        padding: 0;
        background: transparent;
        color: var(--accent);
        font-size: 0.95rem;
        font-weight: 600;
    }

    .skill-head .about {
        margin: 14px 0 0;
        color: var(--text-2);
        font-size: 0.98rem;
        line-height: 1.6;
        max-width: 84ch;
    }

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
    .skill-chip.pending { --tone: var(--status-progress); }
    .skill-chip.package { --tone: var(--violet); }
    .skill-chip.boost { --tone: var(--accent); }

    /* what has to be known before the text is trusted */
    .skill-notes { display: flex; flex-direction: column; gap: 8px; margin: 16px 0 0; padding: 0; list-style: none; }

    .skill-notes li {
        padding: 10px 14px;
        border-left: 3px solid var(--tone, var(--accent));
        border-radius: 0 var(--radius-xs) var(--radius-xs) 0;
        background: color-mix(in srgb, var(--tone, var(--accent)) 8%, transparent);
        color: var(--text-2);
        font-size: 0.86rem;
        line-height: 1.55;
        max-width: 96ch;
    }

    .skill-notes li.warn { --tone: var(--warn-fill); }
    .skill-notes code { overflow-wrap: anywhere; }

    .skill-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    @media (min-width: 1000px) {
        .skill-layout { grid-template-columns: 250px minmax(0, 1fr); gap: 22px; }
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
        display: block;
        padding: 6px 9px;
        border-radius: var(--radius-xs);
        color: var(--text-2);
        font-size: 0.86rem;
        line-height: 1.35;
        text-decoration: none;
    }

    .toc a:hover { background: var(--surface-3); color: var(--text); }
    .toc a.is-here { background: var(--accent-soft); color: var(--accent-strong); font-weight: 600; }
    .toc .level-3 a { padding-left: 22px; color: var(--muted); font-size: 0.82rem; }
    .toc .level-3 a.is-here { color: var(--accent-strong); }

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

    /* a skill is long prose with tables and commands: a measure the eye can
       follow, tables that scroll inside their own frame, code that wraps */
    .skill-body { padding: 22px 18px; }

    @media (min-width: 640px) {
        .skill-body { padding: 32px 38px; }
    }

    .skill-body.markdown { font-size: 0.97rem; line-height: 1.7; }
    .skill-body.markdown > :is(p, ul, ol, blockquote) { max-width: 82ch; }
    .skill-body.markdown h1 { font-size: 1.5rem; }
    .skill-body.markdown h2 { margin-top: 2.6rem; }
    .skill-body.markdown h2:first-child { margin-top: 0.4rem; }
    .skill-body.markdown li { margin: 0.4rem 0; }
    .skill-body.markdown th { white-space: nowrap; }
    .skill-body.markdown td:first-child { font-weight: 600; }
    .skill-body.markdown pre { white-space: pre-wrap; overflow-wrap: anywhere; }
    .skill-body :is(h1, h2, h3, h4) { scroll-margin-top: 84px; }

    .skill-files { margin-top: 18px; }
    .skill-files h3 { margin: 0 0 4px; font-size: 1rem; }
    .skill-files .hint { margin: 0 0 12px; }
    .skill-files ul { margin: 0; padding: 0; list-style: none; }

    .skill-files li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 0;
        border-top: 1px solid var(--border);
        font-size: 0.86rem;
    }

    .skill-files li:first-child { border-top: 0; }
    .skill-files code { padding: 0; background: transparent; overflow-wrap: anywhere; }
    .skill-files small { flex: none; color: var(--muted); font-variant-numeric: tabular-nums; }
    .skill-files .where { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .skill-files .where b { font-weight: 600; }
    .skill-files .state { display: inline-flex; align-items: center; gap: 7px; flex: none; color: var(--text-2); font-size: 0.8rem; }
    .skill-files .dot { flex: none; width: 7px; height: 7px; border-radius: 999px; background: var(--border-strong); }
    .skill-files .dot.on { background: var(--ok-fill); }
    .skill-files .dot.warn { background: var(--warn-fill); }

    @media print {
        .skill-topbar, .toc { display: none; }
    }
</style>
@endpush

@section('content')
    @php
        $library = \Larapilot\Services\SkillLibraryService::class;
        $origin = $skill['origin'];
        $isCustom = $origin === $library::CUSTOM;
        // The name leads to a skill of the project or of Larapilot. Any
        // other is asked for by where it comes from.
        $address = ['name' => $skill['folder']];

        if (! in_array($origin, [$library::CUSTOM, $library::PACKAGED], true)) {
            $address['from'] = $skill['id'];
        }

        [$chip, $tone] = match (true) {
            $isCustom && $skill['registered'] => ['Registered', 'registered'],
            $isCustom => ['Not registered', 'pending'],
            $origin === $library::PACKAGED => ['Packaged', ''],
            $origin === $library::PACKAGE => ['Package', 'package'],
            $origin === $library::BOOST => ['Boost', 'boost'],
            $origin === $library::PROJECT => ['Project', 'registered'],
            default => ['Agent only', 'pending'],
        };

        $comesFrom = match ($origin) {
            $library::CUSTOM => 'Written for this project',
            $library::PACKAGED => 'Ships with Larapilot',
            $library::PACKAGE => 'Brought by '.$skill['source'],
            $library::BOOST => trim('Built into Laravel Boost, for '.($skill['about'] ?? 'Laravel').' '.($skill['version'] ?? '')),
            $library::PROJECT => 'Written for Boost by the team',
            default => 'Only in the folder of an agent',
        };

        $sources = [
            $library::CUSTOM => 'this project',
            $library::PACKAGED => 'Larapilot',
            $library::PACKAGE => 'a package',
            $library::BOOST => 'Laravel Boost',
            $library::PROJECT => 'the project, in .ai/',
            $library::AGENT => 'the folder of an agent',
        ];

        $states = [
            'same' => ['on', 'Same text as the source'],
            'made' => ['on', 'Made by Boost from the template'],
            'differs' => ['warn', 'Not the same text as the source'],
        ];

        $hasAgents = ($skill['agents'] ?? []) !== [];
        $canBrowse = $isCustom
            && Route::has('larapilot.dashboard.files.browse')
            && app(\Larapilot\Services\ConfigService::class)->fileManagerBrowsable();
        $size = static fn (int $bytes): string => $bytes < 1024
            ? $bytes.' B'
            : rtrim(rtrim(number_format($bytes / 1024, 1, '.', ','), '0'), '.').' KB';
        // The title of the document is already the title of the page.
        $html = preg_replace('/^\s*<h1[^>]*>.*?<\/h1>\s*/s', '', $skill['html'], 1) ?? $skill['html'];
    @endphp

    <div class="skill-topbar">
        <a class="back-link" href="{{ route('larapilot.dashboard.skills') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'back'])All skills</a>
        <div class="page-actions">
            @if ($canBrowse)
                <a class="btn ghost" href="{{ app(\Larapilot\Services\FileManagerService::class)->url('skills', $skill['folder']) }}">@include('larapilot::dashboard.partials.icon', ['name' => 'folder'])Open folder</a>
            @endif
            <a class="btn ghost" href="{{ route('larapilot.dashboard.skill.download', $address) }}" title="The skill exactly as it is on disk, front matter included">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download SKILL.md</a>
        </div>
    </div>

    <header class="card skill-head">
        <h2>{{ $skill['title'] ?? $skill['name'] }}
            <span class="skill-chip {{ $tone }}">{{ $chip }}</span>
        </h2>
        <code class="trigger">{{ $skill['trigger'] }}</code>
        @if ($skill['description'])
            <p class="about">{{ $skill['description'] }}</p>
        @endif
        <ul class="skill-facts">
            <li><b>{{ $comesFrom }}</b></li>
            <li><code>{{ $skill['relative_path'] }}</code></li>
            <li>{{ number_format($skill['lines']) }} lines · {{ $size($skill['bytes']) }}</li>
            @if ($skill['modified'])
                <li>Changed {{ \Illuminate\Support\Carbon::createFromTimestamp($skill['modified'])->format('M j, Y') }}</li>
            @endif
            @if ($skill['author'])
                <li>By {{ $skill['author'] }}</li>
            @endif
            @if ($skill['license'])
                <li>{{ $skill['license'] }} license</li>
            @endif
        </ul>

        @php
            $notes = [];

            if ($skill['template'] && $skill['shown_from'] !== null) {
                $notes[] = ['', 'This skill is a template Boost fills in for the project. It is shown as Boost published it, from <code>'.e($skill['shown_from']).'</code>.'];
            } elseif ($skill['template']) {
                $notes[] = ['warn', 'This skill is a template Boost fills in when it publishes it. No agent of this project has it, so it is shown as it is written, with the Blade directives Boost replaces.'];
            }

            if ($skill['override'] !== null) {
                $notes[] = ['warn', 'The project keeps its own version of this skill in <code>'.e($skill['override']).'</code>. Boost publishes that one in place of this.'];
            }

            if ($origin === $library::PACKAGE && ($skill['direct'] ?? true) === false) {
                $notes[] = ['warn', '<code>'.e($skill['source']).'</code> came with another package: the project does not require it itself, so Boost does not publish its skills. Require it in <code>'.($skill['manager'] === 'npm' ? 'package.json' : 'composer.json').'</code> to have them.'];
            }

            if ($origin === $library::AGENT) {
                $notes[] = ['warn', 'No package and no folder of the project accounts for this skill. It was added by hand or by another tool, and <code>boost:update</code> does not keep it up to date.'];
            }
        @endphp
        @if ($notes !== [])
            <ul class="skill-notes">
                @foreach ($notes as [$kind, $note])
                    <li @class(['warn' => $kind === 'warn'])>{!! $note !!}</li>
                @endforeach
            </ul>
        @endif
    </header>

    <div class="skill-layout">
        @if ($skill['headings'] !== [])
            <details class="card toc" open data-toc>
                <summary>In this skill @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
                <ul>
                    @foreach ($skill['headings'] as $heading)
                        <li @class(['level-3' => $heading['level'] === 3])>
                            <a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        <div @style(['grid-column: 1 / -1' => $skill['headings'] === []])>
            <article class="card skill-body markdown" id="skill-body">
                {!! $html !!}
            </article>

            @if ($hasAgents || $skill['installed'] !== [])
                <section class="card panel skill-files" id="skill-agents">
                    <h3>Where the agents find it</h3>
                    <p class="hint">A skill runs in an agent when its folder is in the folder of that agent.</p>
                    <ul>
                        @foreach ($skill['agents'] as $folder)
                            @php
                                $copy = collect($skill['installed'])->firstWhere('folder', $folder['folder']);
                                [$dot, $says] = $copy === null ? ['', 'Not there'] : $states[$copy['state']];
                            @endphp
                            <li>
                                <span class="where">
                                    <b>{{ $folder['agent'] }}</b>
                                    <code>{{ $copy['relative_path'] ?? $folder['folder'].'/' }}</code>
                                </span>
                                <span class="state"><i class="dot {{ $dot }}"></i>{{ $says }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if (in_array('differs', array_column($skill['installed'], 'state'), true))
                        <p class="hint" style="margin: 12px 0 0;">{{ $isCustom ? 'Open the Skills page again: Larapilot copies the skill to the agents every time it is opened.' : 'Run php artisan boost:update to publish the text of the source again.' }}</p>
                    @endif
                </section>
            @endif

            @if ($skill['others'] !== [])
                <section class="card panel skill-files" id="skill-others">
                    <h3>Other skills of this name</h3>
                    <p class="hint">One name, several sources. The project comes first, then Larapilot, then the other packages, then Boost.</p>
                    <ul>
                        @foreach ($skill['others'] as $other)
                            <li>
                                <span class="where">
                                    <a href="{{ route('larapilot.dashboard.skill', ['name' => $skill['folder'], 'from' => $other['id']]) }}"><b>From {{ $sources[$other['origin']] ?? $other['origin'] }}{{ $other['origin'] === $library::PACKAGE ? ': '.$other['source'] : '' }}{{ $other['version'] !== null ? ', version '.$other['version'] : '' }}</b></a>
                                    <code>{{ $other['relative_path'] }}</code>
                                </span>
                                <span class="state"><i class="dot {{ $other['published'] ? 'on' : '' }}"></i>{{ $other['published'] ? 'Published' : 'Not published' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($skill['files'] !== [])
                <section class="card panel skill-files">
                    <h3>Files beside it</h3>
                    <p class="hint">What sits in the folder of the skill and the skill may refer to.</p>
                    <ul>
                        @foreach ($skill['files'] as $file)
                            <li>
                                @if ($canBrowse)
                                    <a href="{{ app(\Larapilot\Services\FileManagerService::class)->url('skills', $skill['folder'].'/'.$file['path']) }}"><code>{{ $file['path'] }}</code></a>
                                @else
                                    <code>{{ $file['path'] }}</code>
                                @endif
                                <small>{{ $size($file['bytes']) }}</small>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        // On a phone the section list starts folded, so the skill comes first.
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

        // The section being read is marked in the list.
        const links = [...document.querySelectorAll('[data-toc] a')];
        const body = document.getElementById('skill-body');

        if (!body || links.length === 0 || !('IntersectionObserver' in window)) {
            return;
        }

        const byId = new Map(links.map((link) => [decodeURIComponent(link.hash.slice(1)), link]));
        const headings = [...body.querySelectorAll('h2[id], h3[id]')].filter((heading) => byId.has(heading.id));

        const mark = () => {
            let current = headings[0];

            headings.forEach((heading) => {
                if (heading.getBoundingClientRect().top <= 120) {
                    current = heading;
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
