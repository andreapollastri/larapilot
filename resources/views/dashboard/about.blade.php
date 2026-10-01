@extends('larapilot::dashboard.layout')

@section('title', 'About')

@push('styles')
<style>
    .about-page { display: flex; flex-direction: column; gap: 20px; }
    .about-page .page-head { margin-bottom: 0; }

    .flash--info { --tone: var(--accent); color: var(--accent-strong); }

    .support-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(0, 1fr);
    }

    @media (min-width: 640px) { .support-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1200px) { .support-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

    .support-card { display: flex; flex-direction: column; gap: 10px; padding: 16px 18px; min-width: 0; }
    .support-card .eyebrow { margin: 0; }

    .support-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; flex-wrap: wrap; }

    .support-version {
        font-size: 1.55rem;
        font-weight: 650;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
        overflow-wrap: anywhere;
    }

    .support-note { margin: 0; color: var(--muted); font-size: 0.8rem; line-height: 1.45; }

    /* A state is a word beside a colour, never the colour alone. */
    .state-chip {
        --tone: var(--status-todo);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px 3px 9px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--tone) 16%, transparent);
        color: color-mix(in srgb, var(--tone) 55%, var(--text));
        font-size: 0.72rem;
        font-weight: 650;
        white-space: nowrap;
    }

    .state-chip::before { content: ''; width: 7px; height: 7px; border-radius: 999px; background: var(--tone); }
    .state-chip.is-active { --tone: var(--ok-fill); }
    .state-chip.is-security { --tone: var(--warn-fill); }
    .state-chip.is-eol { --tone: var(--danger-fill); }
    .state-chip.is-ending { --tone: var(--warn-fill); }

    .lifecycle { position: relative; height: 8px; margin: 6px 0 2px; border-radius: 999px; background: var(--surface-3); }
    .lifecycle span { position: absolute; top: 0; bottom: 0; }
    .lifecycle .seg-active { background: color-mix(in srgb, var(--ok-fill) 75%, transparent); border-radius: 999px 0 0 999px; }
    .lifecycle .seg-security { background: color-mix(in srgb, var(--warn-fill) 75%, transparent); border-radius: 0 999px 999px 0; }
    .lifecycle .seg-active.is-whole { border-radius: 999px; }

    .lifecycle .today {
        top: -5px;
        bottom: -5px;
        width: 2px;
        margin-left: -1px;
        border-radius: 2px;
        background: var(--text);
    }

    .lifecycle-dates { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2px 10px; color: var(--muted); font-size: 0.72rem; font-variant-numeric: tabular-nums; }
    .lifecycle-dates span { white-space: nowrap; }

    .about-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(0, 1fr);
    }

    @media (min-width: 900px) { .about-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

    .about-grid > .card, .about-wide { min-width: 0; }
    .about-wide { grid-column: 1 / -1; }

    .panel h3 { margin: 0 0 12px; font-size: 1rem; }

    .facts { margin: 0; font-size: 0.88rem; }
    .facts div { display: grid; grid-template-columns: minmax(110px, 34%) minmax(0, 1fr); gap: 12px; padding: 8px 0; border-top: 1px solid var(--border); }
    .facts div:first-child { border-top: 0; padding-top: 0; }
    .facts dt { color: var(--muted); }
    .facts dd { margin: 0; overflow-wrap: anywhere; }

    .ext-grid { display: flex; flex-wrap: wrap; gap: 6px; }

    .ext {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border: 1px solid var(--border);
        border-radius: 999px;
        font-family: var(--mono);
        font-size: 0.74rem;
        color: var(--text-2);
    }

    .ext.is-missing { color: var(--muted); border-style: dashed; text-decoration: line-through; text-decoration-color: color-mix(in srgb, var(--muted) 60%, transparent); }
    .ext .icon { width: 13px; height: 13px; color: var(--ok); }

    .stack-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
    .stack-table th, .stack-table td { padding: 8px 10px; border-top: 1px solid var(--border); text-align: left; vertical-align: top; }
    .stack-table thead th { border-top: 0; color: var(--muted); font-size: 0.7rem; font-weight: 650; letter-spacing: 0.06em; text-transform: uppercase; white-space: nowrap; }
    .stack-table td code { white-space: nowrap; }
    .stack-table .muted { color: var(--muted); }

    .next-steps { margin: 0; padding-left: 1.1rem; display: flex; flex-direction: column; gap: 8px; font-size: 0.9rem; line-height: 1.55; }
    .next-steps code { white-space: normal; overflow-wrap: anywhere; }
</style>
@endpush

@section('content')
    @php
        $project = $facts['project'];
        $php = $facts['php'];
        $laravel = $facts['laravel'];
        $database = $facts['database'];
        $frontend = $facts['frontend'];
        $tooling = $facts['tooling'];
        $day = static fn (?string $date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('M j, Y') : '—';
        $month = static fn (?string $date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('M Y') : '';
        $stateLabel = static fn (array $support): string => $support['ending'] ? 'Ends in '.$support['days_left'].' days' : \Larapilot\Support\SupportPolicy::stateLabel($support['state']);
        $stateClass = static fn (array $support): string => $support['ending'] ? 'is-ending' : 'is-'.$support['state'];
        $engineLabel = [
            'mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite', 'sqlsrv' => 'SQL Server',
        ];

        // Where the dates of a lifecycle fall on one bar, in percent.
        $lifecycle = static function (array $support): ?array {
            if ($support['released'] === null || $support['security_until'] === null) {
                return null;
            }

            $start = strtotime($support['released']);
            $end = strtotime($support['security_until']);
            $span = max(1, $end - $start);
            $at = static fn (int $time): float => round(max(0, min(100, ($time - $start) / $span * 100)), 2);
            $active = $support['active_until'] !== null ? $at(strtotime($support['active_until'])) : 100.0;

            return [
                'active' => $active,
                'today' => $at(time()),
                'past' => time() > $end,
            ];
        };

        $cards = [
            ['eyebrow' => 'Framework', 'title' => 'Laravel', 'version' => $laravel['version'], 'support' => $laravel['support'], 'note' => $laravel['constraint'] ? 'composer.json asks for '.$laravel['constraint'] : null],
            ['eyebrow' => 'Runtime', 'title' => 'PHP', 'version' => $php['running'], 'support' => $php['support'], 'note' => 'This page runs on PHP '.$php['running'].' ('.$php['sapi'].')'.($php['constraint'] ? '; composer.json allows '.$php['constraint'] : '').($php['platform'] ? '; Composer resolves for '.$php['platform'] : '')],
            ['eyebrow' => 'Database', 'title' => $engineLabel[$database['engine'] ?? ''] ?? ($database['driver'] !== '' ? $database['driver'] : 'None'), 'version' => $database['version'] ?? ($database['reachable'] === false ? 'Not reachable' : '—'), 'support' => $database['support'], 'note' => 'Connection “'.$database['default'].'”'.($database['database'] ? ' · '.$database['database'] : '')],
            ['eyebrow' => 'Frontend', 'title' => 'Node.js', 'version' => $frontend['node'] ?? 'Not pinned', 'support' => $frontend['node_support'], 'note' => $frontend['node'] ? 'Pinned in .nvmrc, .node-version, or package.json' : 'No .nvmrc, .node-version, or engines.node'],
        ];

        $next = [];

        if (($laravel['support']['cycle'] ?? null) !== null && ! $laravel['support']['is_latest']) {
            $next[] = ['skill' => '/larapilot-laravel-upgrade', 'what' => 'Laravel '.$laravel['support']['cycle'].' → '.$laravel['support']['latest'], 'command' => 'php artisan larapilot:upgrade-check --laravel='.$laravel['support']['latest']];
        }

        if (($php['support']['cycle'] ?? null) !== null && ! $php['support']['is_latest']) {
            $next[] = ['skill' => '/larapilot-php-upgrade', 'what' => 'PHP '.$php['support']['cycle'].' → '.$php['support']['latest'], 'command' => 'php artisan larapilot:upgrade-check --php='.$php['support']['latest']];
        }

        if (is_array($database['support'] ?? null) && $database['support']['cycle'] !== null && ! $database['support']['is_latest']) {
            $next[] = ['skill' => '/larapilot-db-upgrade', 'what' => ($engineLabel[$database['engine']] ?? $database['engine']).' '.$database['support']['cycle'].' → '.$database['support']['latest'], 'command' => 'php artisan larapilot:upgrade-check --db='.$database['engine'].':'.$database['support']['latest']];
        }
    @endphp

    <div class="about-page">
        <header class="page-head">
            <div>
                <h2>About</h2>
                <p class="sub">What <strong>{{ $project['name'] }}</strong> runs on, and where each version stands in its upstream support window. Read live from this application, <code>composer.lock</code>, and the files around it.</p>
                <div class="chips" style="margin-top: 12px">
                    <span class="chip current">{{ $project['environment'] }}</span>
                    @if ($project['debug'])
                        <span class="chip stale">Debug on</span>
                    @endif
                    @if ($project['maintenance'])
                        <span class="chip stale">Maintenance mode</span>
                    @endif
                    <span class="chip">Larapilot {{ $project['larapilot'] }}</span>
                    <span class="chip">Lifecycle table of {{ $day($facts['support_table']) }}</span>
                </div>
            </div>
            <div class="page-actions">
                <a class="btn ghost" href="{{ route('larapilot.dashboard.sbom') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'archive'])Dependencies (SBOM)</a>
            </div>
        </header>

        @foreach ($facts['alerts'] as $alert)
            <div @class(['flash', 'flash--error' => $alert['level'] === 'critical', 'flash--warn' => $alert['level'] === 'warning', 'flash--info' => $alert['level'] === 'info']) role="{{ $alert['level'] === 'critical' ? 'alert' : 'status' }}">
                <strong>{{ $alert['message'] }}</strong>
            </div>
        @endforeach

        <section class="support-grid" aria-label="Support windows">
            @foreach ($cards as $card)
                @php $support = $card['support']; $bar = is_array($support) ? $lifecycle($support) : null; @endphp
                <article class="card support-card">
                    <p class="eyebrow">{{ $card['eyebrow'] }}</p>
                    <div class="support-head">
                        <div>
                            <div class="metric-label" style="margin: 0">{{ $card['title'] }}</div>
                            <div class="support-version">{{ $card['version'] }}</div>
                        </div>
                        @if (is_array($support))
                            <span class="state-chip {{ $stateClass($support) }}">{{ $stateLabel($support) }}</span>
                        @endif
                    </div>
                    @if ($bar !== null)
                        <div class="lifecycle" role="img" aria-label="{{ $support['label'] }} {{ $support['cycle'] }}: released {{ $day($support['released']) }}{{ $support['active_until'] ? ', bug fixes until '.$day($support['active_until']) : '' }}, security fixes until {{ $day($support['security_until']) }}">
                            <span @class(['seg-active', 'is-whole' => $bar['active'] >= 100]) style="left: 0; width: {{ $bar['active'] }}%"></span>
                            @if ($bar['active'] < 100)
                                <span class="seg-security" style="left: {{ $bar['active'] }}%; width: {{ 100 - $bar['active'] }}%"></span>
                            @endif
                            <span class="today" style="left: {{ $bar['today'] }}%" title="Today"></span>
                        </div>
                        <div class="lifecycle-dates">
                            <span title="Released {{ $day($support['released']) }}">{{ $month($support['released']) }}</span>
                            @if ($support['active_until'])
                                <span title="Bug fixes until {{ $day($support['active_until']) }}">fixes → {{ $month($support['active_until']) }}</span>
                            @endif
                            <span title="Security fixes until {{ $day($support['security_until']) }}">security → {{ $month($support['security_until']) }}</span>
                        </div>
                        <p class="support-note">
                            @if ($support['is_latest'])
                                The newest {{ $support['label'] }} the table knows.
                            @else
                                Newest: {{ $support['label'] }} {{ $support['latest'] }}.
                            @endif
                            @if ($support['php'])
                                Supports PHP {{ $support['php'] }}.
                            @endif
                        </p>
                    @elseif (is_array($support) && $support['state'] === 'unknown')
                        <p class="support-note">{{ $support['label'] }} {{ $support['cycle'] }} is not in the lifecycle table of this Larapilot.</p>
                    @endif
                    @if ($card['note'])
                        <p class="support-note">{{ $card['note'] }}</p>
                    @endif
                </article>
            @endforeach
        </section>

        <div class="about-grid">
            <section class="card panel">
                <h3>Project</h3>
                <dl class="facts">
                    <div><dt>Name</dt><dd>{{ $project['name'] }}</dd></div>
                    @if ($project['package'])
                        <div><dt>Package</dt><dd><code>{{ $project['package'] }}</code></dd></div>
                    @endif
                    @if ($project['description'])
                        <div><dt>Description</dt><dd>{{ $project['description'] }}</dd></div>
                    @endif
                    <div><dt>Environment</dt><dd>{{ $project['environment'] }}{{ $project['debug'] ? ' · debug on' : '' }}</dd></div>
                    <div><dt>URL</dt><dd>{{ $project['url'] !== '' ? $project['url'] : '—' }}</dd></div>
                    <div><dt>Timezone · locale</dt><dd>{{ $project['timezone'] }} · {{ $project['locale'] }}</dd></div>
                    <div><dt>Laravel</dt><dd>{{ $laravel['version'] }}{{ $laravel['running'] !== $laravel['version'] ? ' (running '.$laravel['running'].')' : '' }}</dd></div>
                    <div><dt>Lock file</dt><dd>{{ $project['composer_lock'] ? 'composer.lock present' : 'No composer.lock: versions are not fixed' }}</dd></div>
                </dl>
            </section>

            <section class="card panel">
                <h3>Runtime</h3>
                <dl class="facts">
                    <div><dt>PHP</dt><dd>{{ $php['running'] }} · {{ $php['sapi'] }}</dd></div>
                    <div><dt>composer.json</dt><dd>{{ $php['constraint'] ? 'requires PHP '.$php['constraint'] : 'no PHP requirement' }}{{ $php['platform'] ? ' · platform '.$php['platform'] : '' }}</dd></div>
                    @if ($laravel['php_range'])
                        <div><dt>Laravel {{ $laravel['major'] }} supports</dt><dd>PHP {{ $laravel['php_range'][0] }} to {{ $laravel['php_range'][1] }}{{ $laravel['php_supported'] === false ? ' — not the PHP this runs on' : '' }}</dd></div>
                    @endif
                    <div><dt>Limits</dt><dd>memory {{ $php['ini']['memory_limit'] }} · upload {{ $php['ini']['upload_max_filesize'] }} · post {{ $php['ini']['post_max_size'] }} · OPcache {{ $php['ini']['opcache'] ? 'on' : 'off' }}</dd></div>
                </dl>
                <h3 style="margin-top: 16px">Extensions</h3>
                <div class="ext-grid">
                    @foreach ($php['extensions'] as $extension => $loaded)
                        <span @class(['ext', 'is-missing' => ! $loaded]) title="{{ $loaded ? 'Loaded' : 'Not loaded' }}">@if ($loaded)@include('larapilot::dashboard.partials.icon', ['name' => 'check'])@endif{{ $extension }}</span>
                    @endforeach
                </div>
            </section>

            <section class="card panel">
                <h3>Database</h3>
                <dl class="facts">
                    <div><dt>Connection</dt><dd>{{ $database['default'] !== '' ? $database['default'] : '—' }} · {{ $database['driver'] !== '' ? $database['driver'] : '—' }}</dd></div>
                    <div><dt>Server</dt><dd>
                        @if ($database['reachable'] === true)
                            {{ $database['server'] }}
                        @elseif ($database['reachable'] === false)
                            Did not answer{{ $database['error'] ? ': '.$database['error'] : '' }}
                        @else
                            —
                        @endif
                    </dd></div>
                    @if ($database['database'])
                        <div><dt>Database</dt><dd>{{ $database['database'] }}</dd></div>
                    @endif
                    @if ($database['host'])
                        <div><dt>Host</dt><dd>{{ $database['host'] }}{{ $database['port'] ? ':'.$database['port'] : '' }}</dd></div>
                    @endif
                    @if ($database['charset'] || $database['collation'])
                        <div><dt>Charset</dt><dd>{{ $database['charset'] ?? '—' }}{{ $database['collation'] ? ' · '.$database['collation'] : '' }}</dd></div>
                    @endif
                    <div><dt>Connections</dt><dd>{{ implode(' · ', array_map(static fn ($name, $driver) => $name.($driver !== '' && $driver !== $name ? ' ('.$driver.')' : ''), array_keys($database['connections']), $database['connections'])) }}</dd></div>
                </dl>
            </section>

            <section class="card panel">
                <h3>Drivers</h3>
                <dl class="facts">
                    @foreach ($facts['drivers'] as $service => $driver)
                        @if ($driver !== null)
                            <div><dt>{{ ucfirst($service) }}</dt><dd><code>{{ $driver }}</code></dd></div>
                        @endif
                    @endforeach
                </dl>
            </section>

            <section class="card panel about-wide">
                <h3>Packages that shape an upgrade</h3>
                @if ($facts['packages'] === [])
                    <p class="hint" style="margin: 0">None of the packages Larapilot tracks is installed{{ $project['composer_lock'] ? '' : ', or composer.lock is missing' }}.</p>
                @else
                    <div class="table-wrap">
                        <table class="stack-table">
                            <thead><tr><th>Package</th><th>Role</th><th>Installed</th><th>Constraint</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($facts['packages'] as $package)
                                    <tr>
                                        <td><strong>{{ $package['label'] }}</strong><br><code class="muted">{{ $package['name'] }}</code></td>
                                        <td>{{ $package['role'] }}</td>
                                        <td><code>{{ $package['version'] }}</code></td>
                                        <td>{!! $package['constraint'] ? '<code>'.e($package['constraint']).'</code>' : '<span class="muted">'.($package['direct'] ? '—' : 'transitive').'</span>' !!}</td>
                                        <td>
                                            @if ($package['dev'])<span class="chip">dev</span>@endif
                                            @if ($package['abandoned'] !== false)<span class="state-chip is-eol">Abandoned</span>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card panel">
                <h3>Frontend</h3>
                <dl class="facts">
                    <div><dt>In this repository</dt><dd>{{ $frontend['package_json'] ? ($frontend['package_manager'] ?? 'npm').' · '.$frontend['dependencies'].' declared dependencies' : 'No package.json' }}</dd></div>
                    @if ($frontend['stack'] !== [])
                        <div><dt>Stack</dt><dd>{{ implode(' · ', array_map(static fn (array $item): string => $item['label'].' '.$item['constraint'], $frontend['stack'])) }}</dd></div>
                    @endif
                    <div><dt>Node</dt><dd>{{ $frontend['node'] ?? 'Not pinned' }}</dd></div>
                    <div><dt>Companion</dt><dd>
                        @if ($frontend['companion']['configured'])
                            {{ $frontend['companion']['repository'] }}{{ $frontend['companion']['stack'] ? ' · '.$frontend['companion']['stack'] : '' }} · {{ $frontend['companion']['mode'] }}{{ $frontend['companion']['projects'] !== [] ? ' · '.implode(', ', $frontend['companion']['projects']) : '' }}
                        @else
                            Not linked
                        @endif
                    </dd></div>
                </dl>
            </section>

            <section class="card panel">
                <h3>Tooling</h3>
                <dl class="facts">
                    <div><dt>Git</dt><dd>
                        @if ($tooling['git']['repository'])
                            {{ $tooling['git']['branch'] ?? 'detached' }}{{ $tooling['git']['commit'] ? ' · '.$tooling['git']['commit'] : '' }}{{ $tooling['git']['subject'] ? ' — '.$tooling['git']['subject'] : '' }}
                        @else
                            Not a git repository
                        @endif
                    </dd></div>
                    @if ($tooling['git']['remote'])
                        <div><dt>Remote</dt><dd>{{ $tooling['git']['remote'] }}</dd></div>
                    @endif
                    <div><dt>CI</dt><dd>{{ $tooling['ci'] !== [] ? implode(' · ', $tooling['ci']) : 'None found' }}</dd></div>
                    <div><dt>Runs on</dt><dd>{{ $tooling['deploy'] !== [] ? implode(' · ', $tooling['deploy']) : 'Nothing in the repository says' }}</dd></div>
                </dl>
            </section>

            <section class="card panel about-wide">
                <h3>Files that pin a version</h3>
                <p class="hint" style="margin: -6px 0 12px">An upgrade changes these in the same commit as the code, or the servers keep running the old version.</p>
                @if ($facts['pins'] === [])
                    <p class="hint" style="margin: 0">No Dockerfile, compose file, CI job, or version file pins PHP, Node, or the database.</p>
                @else
                    <div class="table-wrap">
                        <table class="stack-table">
                            <thead><tr><th>File</th><th>Pins</th><th>Version</th><th>Line</th></tr></thead>
                            <tbody>
                                @foreach ($facts['pins'] as $pin)
                                    <tr>
                                        <td><code>{{ $pin['file'] }}:{{ $pin['line'] }}</code></td>
                                        <td>{{ \Larapilot\Support\SupportPolicy::label($pin['kind']) }}</td>
                                        <td><code>{{ $pin['value'] }}</code></td>
                                        <td class="muted"><code>{{ $pin['text'] }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card panel about-wide">
                <h3>Upgrades</h3>
                @if ($next === [])
                    <p class="hint" style="margin: 0 0 10px">Laravel, PHP, and the database are on the newest versions the lifecycle table knows.</p>
                @endif
                <ul class="next-steps">
                    @foreach ($next as $step)
                        <li><strong>{{ $step['what'] }}</strong> — <code>{{ $step['skill'] }}</code> plans and runs it, with a readiness report first: <code>{{ $step['command'] }}</code></li>
                    @endforeach
                    <li>Switch engine (MySQL → PostgreSQL, MySQL → MariaDB, …) — <code>/larapilot-db-upgrade</code>, readiness with <code>php artisan larapilot:upgrade-check --db=pgsql:{{ \Larapilot\Support\SupportPolicy::latest('pgsql') }}</code></li>
                    <li>The same facts as JSON, for an agent or a script: <code>php artisan larapilot:stack</code></li>
                </ul>
            </section>
        </div>
    </div>
@endsection
