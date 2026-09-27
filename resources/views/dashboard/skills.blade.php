@extends('larapilot::dashboard.layout')

@section('title', 'Skills')

@push('styles')
<style>
    .skills-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }

    @media (min-width: 640px) {
        .skills-grid { grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
    }

    .skills-page { display: flex; flex-direction: column; gap: 26px; }
    .skills-page .page-head { margin-bottom: 0; }

    .skills-group { display: flex; flex-direction: column; gap: 14px; }
    .skills-group > header h3 { margin: 0 0 4px; font-size: 1.08rem; }
    .skills-group > header p { margin: 0; color: var(--muted); font-size: 0.86rem; max-width: 80ch; }

    .skills-find { max-width: 420px; }

    /* the whole card leads to the skill */
    .skill-card {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 18px 20px;
        color: inherit;
        text-decoration: none;
        transition: border-color 0.14s ease, box-shadow 0.14s ease, transform 0.14s ease;
    }

    .skill-card:hover {
        border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
        box-shadow: 0 8px 22px color-mix(in srgb, var(--text) 9%, transparent);
        text-decoration: none;
        transform: translateY(-1px);
    }

    .skill-card[hidden] { display: none; }

    .skill-card .read {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--accent);
        font-family: var(--font);
        font-weight: 600;
        white-space: nowrap;
    }

    .skill-card .read .icon { width: 15px; height: 15px; }

    .skill-card .about {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 4;
        line-clamp: 4;
        overflow: hidden;
    }

    .skills-none { padding: 28px 20px; text-align: center; color: var(--muted); }
    .skills-none[hidden] { display: none; }

    .skill-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }

    .skill-card h3 {
        margin: 0 0 4px;
        font-size: 1rem;
    }

    .skill-card .trigger {
        padding: 0;
        background: transparent;
        color: var(--accent);
        font-size: 0.82rem;
        font-weight: 600;
    }

    .skill-card .about {
        margin: 0;
        color: var(--text-2);
        font-size: 0.875rem;
        line-height: 1.55;
    }

    .skill-card .meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: auto;
        padding-top: 12px;
        border-top: 1px solid var(--border);
        color: var(--muted);
        font-size: 0.75rem;
    }

    .skill-card .path {
        font-family: var(--mono);
        overflow-wrap: anywhere;
    }

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
        --tone: var(--status-todo);
        background: color-mix(in srgb, var(--tone) 15%, transparent);
        color: color-mix(in srgb, var(--tone) 54%, var(--text));
    }

    .skill-chip::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--tone);
    }

    .skill-chip.registered { --tone: var(--status-done); }
    .skill-chip.pending { --tone: var(--status-progress); }
    .skill-chip.package { --tone: var(--violet); }
    .skill-chip.boost { --tone: var(--accent); }
    .skill-chip.hand { --tone: var(--status-progress); }

    /* who has the skill, under what it does */
    .skill-card .agents-line {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
        color: var(--muted);
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .dot { flex: none; width: 7px; height: 7px; border-radius: 999px; background: var(--border-strong); }
    .dot.on { background: var(--ok-fill); }
    .dot.warn { background: var(--warn-fill); }

    /* what the project is set up with, before the skills */
    .skills-setup { padding: 16px 20px; }
    .skills-setup h3 { margin: 0 0 10px; font-size: 0.7rem; font-weight: 650; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
    .skills-setup ul { display: flex; flex-wrap: wrap; gap: 8px 10px; margin: 0; padding: 0; list-style: none; }

    .skills-setup li {
        display: inline-flex;
        align-items: baseline;
        gap: 8px;
        padding: 6px 12px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface-2);
        font-size: 0.82rem;
    }

    .skills-setup li b { font-weight: 600; }
    .skills-setup li code { padding: 0; background: transparent; color: var(--muted); font-size: 0.76rem; }
    .skills-setup li small { color: var(--muted); font-variant-numeric: tabular-nums; }
    .skills-setup .hint { margin: 10px 0 0; }

    /* what Boost ships and the project does not use: a list, not cards */
    .skills-more { padding: 0; }

    .skills-more > summary {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 14px 18px;
        color: var(--text-2);
        font-size: 0.88rem;
        font-weight: 550;
        cursor: pointer;
        list-style: none;
    }

    .skills-more > summary::-webkit-details-marker { display: none; }
    .skills-more > summary .icon { width: 15px; height: 15px; transition: transform 0.15s ease; }
    .skills-more[open] > summary .icon { transform: rotate(90deg); }
    .skills-more ul { margin: 0; padding: 0 18px 10px; list-style: none; }

    .skills-more li {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 6px 16px;
        flex-wrap: wrap;
        padding: 9px 0;
        border-top: 1px solid var(--border);
        font-size: 0.86rem;
    }

    .skills-more li[hidden] { display: none; }
    .skills-more li a { font-family: var(--mono); font-size: 0.84rem; }
    .skills-more li small { color: var(--muted); }

    /* a file the agents are told from */
    .guide-card .file { font-family: var(--mono); font-size: 0.95rem; overflow-wrap: anywhere; }
    .guide-card .parts { display: flex; flex-wrap: wrap; gap: 6px; margin: 0; padding: 0; list-style: none; }
</style>
@endpush

@section('content')
    @php
        $library = \Larapilot\Services\SkillLibraryService::class;
        $guides = \Larapilot\Services\AgentGuidelineService::class;

        $custom = is_array($skills ?? null) ? $skills : [];
        $shipped = is_array($packaged ?? null) ? $packaged : [];
        $written = is_array($project ?? null) ? $project : [];
        $brought = is_array($packages ?? null) ? $packages : [];
        $builtIn = is_array($boost ?? null) ? $boost : [];
        $loose = is_array($agent ?? null) ? $agent : [];
        $folders = is_array($agents ?? null) ? $agents : [];
        $told = is_array($guidelines ?? null) ? $guidelines : [];
        $state = is_array($boostState ?? null) ? $boostState : ['installed' => false, 'configured' => false, 'tracked' => [], 'agents' => []];

        $builtInUsed = array_values(array_filter($builtIn, static fn (array $skill): bool => $skill['published']));
        $builtInIdle = array_values(array_filter($builtIn, static fn (array $skill): bool => ! $skill['published']));
        $showAgents = $folders !== [];
        $total = count($custom) + count($shipped) + count($written) + count($brought) + count($builtInUsed) + count($loose);
        $kilobytes = static fn (int $bytes): string => rtrim(rtrim(number_format($bytes / 1024, 1, '.', ','), '0'), '.').' KB';
        $count = static fn (int $number, string $one, string $many): string => number_format($number).' '.($number === 1 ? $one : $many);

        $authors = [
            $guides::BOOST => ['Boost', 'boost'],
            $guides::LARAPILOT => ['Larapilot', ''],
            $guides::PACKAGE => ['Package', 'package'],
            $guides::PROJECT => ['Project', 'registered'],
            $guides::HAND => ['By hand', 'hand'],
        ];
    @endphp

    <div class="skills-page">
        <header class="page-head">
            <div>
                <h2>Skills</h2>
                <p class="sub">The slash commands the agents of this project can run, whoever brought them: the project, Larapilot, another package, Laravel Boost. And what the agents are told before any request. Click a skill to read it.</p>
            </div>
            @if (Route::has('larapilot.dashboard.files.browse') && app(\Larapilot\Services\ConfigService::class)->fileManagerBrowsable())
                <div class="page-actions">
                    <a class="btn ghost" href="{{ route('larapilot.dashboard.files.browse', ['root' => 'skills']) }}">@include('larapilot::dashboard.partials.icon', ['name' => 'folder'])Browse files</a>
                </div>
            @endif
        </header>

        <section class="card skills-setup" aria-labelledby="skills-setup-title">
            <h3 id="skills-setup-title">Who reads them here</h3>
            @if ($folders === [])
                <p class="hint" style="margin: 0;">No agent has a folder of skills in this project yet. <code>php artisan boost:install</code> sets the agents up and publishes the skills to them.</p>
            @else
                <ul>
                    @foreach ($folders as $folder)
                        <li><b>{{ $folder['agent'] }}</b> <code>{{ $folder['folder'] }}/</code> <small>{{ $count($folder['skills'], 'skill', 'skills') }}</small></li>
                    @endforeach
                </ul>
                <p class="hint">{{ $count($total, 'skill', 'skills') }} on this page. A skill is published when its folder is in the folder of an agent: <code>php artisan boost:update</code> does it for every skill a package brings.{{ $state['installed'] ? '' : ' Laravel Boost is not installed in this project.' }}</p>
            @endif
        </section>

        <label class="field skills-find">
            Find a skill
            <input type="search" id="skills-filter" placeholder="Name, package, or a word of what it does…" autocomplete="off">
        </label>

        <section class="skills-group" aria-labelledby="skills-custom-title">
            <header>
                <h3 id="skills-custom-title">Custom skills</h3>
                <p>User-authored Boost skills stored in <code>.larapilot/skills/</code>. Create them with <code>/larapilot-custom-skill</code> (persisted via <code>larapilot:custom-skill-add</code>). Larapilot registers each skill into <code>.ai/skills/</code> so Boost can publish the slash command.</p>
            </header>

            @if ($custom === [])
                <div class="card empty">
                    <p>No custom skills yet. Run <code>/larapilot-custom-skill</code> to author one — it is saved under <code>.larapilot/skills/{name}/SKILL.md</code>.</p>
                </div>
            @else
                <div class="skills-grid">
                    @foreach ($custom as $skill)
                        @include('larapilot::dashboard.partials.skill-card', ['skill' => $skill, 'showAgents' => $showAgents])
                    @endforeach
                </div>
            @endif
        </section>

        @if ($shipped !== [])
            <section class="skills-group" aria-labelledby="skills-packaged-title">
                <header>
                    <h3 id="skills-packaged-title">Packaged with Larapilot</h3>
                    <p>{{ count($shipped) }} skills that come with the package and are updated with it. They are read from the package, so what you read here is what the agent runs.</p>
                </header>
                <div class="skills-grid">
                    @foreach ($shipped as $skill)
                        @include('larapilot::dashboard.partials.skill-card', ['skill' => $skill, 'showAgents' => $showAgents])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($written !== [])
            <section class="skills-group" aria-labelledby="skills-project-title">
                <header>
                    <h3 id="skills-project-title">Written for Boost in <code>.ai/</code></h3>
                    <p>{{ $count(count($written), 'skill', 'skills') }} the team keeps in <code>.ai/</code>, outside Larapilot. Boost publishes them to every agent, and a skill here takes the place of a package skill of the same name.</p>
                </header>
                <div class="skills-grid">
                    @foreach ($written as $skill)
                        @include('larapilot::dashboard.partials.skill-card', ['skill' => $skill, 'showAgents' => $showAgents])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($brought !== [])
            <section class="skills-group" aria-labelledby="skills-packages-title">
                <header>
                    <h3 id="skills-packages-title">From other packages</h3>
                    <p>{{ $count(count($brought), 'skill', 'skills') }} from {{ $count(count(array_unique(array_column($brought, 'source'))), 'package', 'packages') }} installed in the project, read from <code>resources/boost/skills/</code> of each. Boost publishes the skills of a package only when the project requires that package itself.</p>
                </header>
                <div class="skills-grid">
                    @foreach ($brought as $skill)
                        @include('larapilot::dashboard.partials.skill-card', ['skill' => $skill, 'showAgents' => $showAgents])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($builtIn !== [])
            <section class="skills-group" aria-labelledby="skills-boost-title">
                <header>
                    <h3 id="skills-boost-title">From Laravel Boost</h3>
                    <p>Boost ships a skill for each Laravel package it knows, and publishes the ones of the packages the project uses, in the version it uses.</p>
                </header>
                @if ($builtInUsed === [])
                    <div class="card empty">
                        <p>No agent of this project has a skill of Boost yet. Run <code>php artisan boost:install</code>, or <code>php artisan boost:update</code> when Boost is set up already.</p>
                    </div>
                @else
                    <div class="skills-grid">
                        @foreach ($builtInUsed as $skill)
                            @include('larapilot::dashboard.partials.skill-card', ['skill' => $skill, 'showAgents' => $showAgents])
                        @endforeach
                    </div>
                @endif
                @if ($builtInIdle !== [])
                    <details class="card skills-more" data-more>
                        <summary>@include('larapilot::dashboard.partials.icon', ['name' => 'chevron']){{ $count(count($builtInIdle), 'more skill', 'more skills') }} Boost ships and no agent of this project has</summary>
                        <ul>
                            @foreach ($builtInIdle as $skill)
                                <li data-find="{{ strtolower($skill['name'].' '.($skill['about'] ?? '').' '.($skill['description'] ?? '')) }}">
                                    <a href="{{ route('larapilot.dashboard.skill', ['name' => $skill['folder'], 'from' => $skill['id']]) }}">/{{ $skill['name'] }}</a>
                                    <small>{{ trim(($skill['about'] ?? '').' '.($skill['version'] ?? '')) }}</small>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>
        @endif

        @if ($loose !== [])
            <section class="skills-group" aria-labelledby="skills-agent-title">
                <header>
                    <h3 id="skills-agent-title">Only in the folder of an agent</h3>
                    <p>{{ $count(count($loose), 'skill', 'skills') }} no package and no folder of the project accounts for: added by hand, or by a tool Larapilot does not know. Only the agent whose folder it is in can run it.</p>
                </header>
                <div class="skills-grid">
                    @foreach ($loose as $skill)
                        @include('larapilot::dashboard.partials.skill-card', ['skill' => $skill, 'showAgents' => $showAgents])
                    @endforeach
                </div>
            </section>
        @endif

        <p class="card skills-none" id="skills-none" hidden>No skill matches. Try part of its name, or a word of what it does.</p>

        <section class="skills-group" aria-labelledby="skills-told-title" data-always>
            <header>
                <h3 id="skills-told-title">What the agents are told</h3>
                <p>A skill runs when it is called. These files are read before any request: the guidelines Boost writes for every package, and what was written by hand around them. Click a file to read it, one part for each author.</p>
            </header>
            @if ($told === [])
                <div class="card empty">
                    <p>No file of this kind in the project: no <code>CLAUDE.md</code>, no <code>AGENTS.md</code>, nothing under <code>.ai/guidelines/</code>. <code>php artisan boost:install</code> writes them.</p>
                </div>
            @else
                <div class="skills-grid">
                    @foreach ($told as $file)
                        <a class="card skill-card guide-card" href="{{ route('larapilot.dashboard.skills.guideline', $file['id']) }}">
                            <div class="skill-card-head">
                                <div>
                                    <h3 class="file">{{ $file['file'] }}</h3>
                                </div>
                                @if ($file['managed'])
                                    <span class="skill-chip boost">Written by Boost</span>
                                @elseif ($file['kind'] === 'source')
                                    <span class="skill-chip registered">Project</span>
                                @else
                                    <span class="skill-chip hand">By hand</span>
                                @endif
                            </div>
                            <p class="about">Read by {{ $file['reader'] }}.</p>
                            @if ($file['origins'] !== [])
                                <ul class="parts">
                                    @foreach ($authors as $origin => [$label, $tone])
                                        @if (($file['origins'][$origin] ?? 0) > 0)
                                            <li><span class="skill-chip {{ $tone }}">{{ $file['origins'][$origin] }} {{ $label }}</span></li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                            <div class="meta">
                                <span>{{ number_format($file['lines']) }} lines · {{ $kilobytes($file['bytes']) }}</span>
                                <span class="read">Read @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const filter = document.getElementById('skills-filter');
        const none = document.getElementById('skills-none');

        if (!filter) {
            return;
        }

        const cards = [...document.querySelectorAll('.skill-card[data-find], .skills-more li[data-find]')];
        const groups = [...document.querySelectorAll('.skills-group:not([data-always])')];
        const folded = [...document.querySelectorAll('[data-more]')];

        filter.addEventListener('input', () => {
            const words = filter.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
            let shown = 0;

            cards.forEach((card) => {
                const text = card.dataset.find || '';
                const ok = words.every((word) => text.includes(word));

                card.hidden = !ok;

                if (ok) {
                    shown += 1;
                }
            });

            // A group with nothing left steps aside, unless it is the empty
            // state of the custom skills and nothing is being searched.
            groups.forEach((group) => {
                const has = group.querySelector('[data-find]:not([hidden])') !== null;

                group.hidden = words.length > 0 && !has;
            });

            // What is folded opens when the search finds something in it.
            folded.forEach((list) => {
                const has = list.querySelector('li:not([hidden])') !== null;

                list.hidden = words.length > 0 && !has;

                if (words.length > 0 && has) {
                    list.open = true;
                }
            });

            if (none) {
                none.hidden = shown > 0 || words.length === 0;
            }
        });
    })();
</script>
@endpush
