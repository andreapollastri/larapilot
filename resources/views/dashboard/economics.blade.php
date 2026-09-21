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
    }
    .btn:hover { text-decoration: none; }
    .btn.ghost {
        border-color: var(--border);
        background: var(--surface);
        color: var(--text);
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

    .scroll-y { max-height: 420px; overflow: auto; }

    .defs { margin: 0; font-size: 0.82rem; }
    .defs div {
        padding: 10px 0;
        border-top: 1px solid var(--border);
    }
    .defs div:first-child { border-top: 0; padding-top: 0; }
    .defs dt { font-weight: 700; }
    .defs dd { margin: 3px 0 0; color: var(--muted); line-height: 1.5; }

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
    .notes li.is-current { color: var(--text); font-weight: 600; }

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
    .forecast-bar.is-recovered {
        background: linear-gradient(180deg, #10b981, #14b8a6);
    }

    .empty-card { padding: 40px 28px; text-align: center; }
    .empty-card h2 { margin: 0 0 8px; }
    .empty-card p { margin: 0 auto 16px; max-width: 62ch; color: var(--muted); }

    .disclaimer {
        margin: 0;
        color: var(--muted);
        font-size: 0.75rem;
        line-height: 1.5;
    }
</style>
@endpush

@section('content')
    @php
        $enabled = (bool) ($enabled ?? false);
        $account = $account ?? 'NONE';
        $currency = $country['currency'] ?? ($profile['currency'] ?? 'EUR');
        $money = static function (mixed $amount) use ($currency): string {
            return $currency.' '.number_format((float) $amount, 0, '.', ',');
        };
        $hours = static fn (mixed $value): string => rtrim(rtrim(number_format((float) $value, 1, '.', ','), '0'), '.').'h';
        $quote = is_array($quote ?? null) ? $quote : [];
        $tax = is_array($tax ?? null) ? $tax : [];
        $saas = is_array($saas ?? null) ? $saas : null;
        $sales = is_array($sales ?? null) ? $sales : [];
        $scenarios = is_array($scenarios ?? null) ? $scenarios : [];
        $effort = is_array($effort ?? null) ? $effort : [];
        $payback = is_array($payback ?? null) ? $payback : [];
        $forecast = is_array($forecast ?? null) ? $forecast : [];
        $breakdown = is_array($effort['breakdown'] ?? null) ? $effort['breakdown'] : [];
        $warnings = is_array($effort['warnings'] ?? null) ? $effort['warnings'] : [];
        $quoteDoc = is_array($quote_document ?? null) ? $quote_document : [];
        $gross = (float) ($quote['gross'] ?? 0);
        $barMax = max($gross, (float) ($quote['client_total'] ?? 0), 1);
        $maxArr = 1.0;
        foreach ($forecast as $row) {
            $maxArr = max($maxArr, (float) ($row['arr'] ?? 0));
        }
        $isSaas = ($product['model'] ?? '') === 'saas';
    @endphp

    <div class="economics-page">
        @if (! $enabled)
            <section class="card empty-card">
                <h2>Economics is off</h2>
                <p>Turn on <strong>Account</strong> mode to generate real quotes: freelance (partita IVA, forfettario, sole trader) or company (SRL, SPA, Ltd) calibrated to current country tax, plus payback and subscription forecasts.</p>
                <p>Enable with <code>/larapilot-settings</code> or:</p>
                <p><code>php artisan larapilot:settings-set --account=FREELANCE</code><br>
                <code>php artisan larapilot:settings-set --account=COMPANY</code></p>
                <p>Then calibrate country, regime, and rates with <code>/larapilot-economics</code>.</p>
            </section>
        @else
            <div class="eco-top">
                <div>
                    <h2>Economics</h2>
                    <p class="sub">What this project costs, what to charge for it, and what stays with you after tax —
                        {{ $account === 'FREELANCE' ? 'freelance' : 'company' }} in {{ $country['name'] ?? 'the selected country' }},
                        {{ $regime['label'] ?? '' }}, sold as {{ strtolower((string) ($product['label'] ?? 'a delivery')) }}.
                        Hours come straight from the backlog; tax from the FY-{{ $fiscal_year ?? 2026 }} statutory catalogue.</p>
                    <div class="chips" style="margin-top:12px;margin-bottom:0">
                        <span class="chip live">Live · recomputed on every load</span>
                        @if (! empty($snapshot_saved_at))
                            <span class="chip">Last computed {{ $snapshot_saved_at }}</span>
                        @endif
                        @if (($quoteDoc['source'] ?? 'template') === 'document')
                            <span class="chip {{ ! empty($quoteDoc['stale']) ? 'stale' : 'current' }}">
                                Client quote: written document{{ ! empty($quoteDoc['lang']) ? ' ('.$quoteDoc['lang'].')' : '' }}{{ ! empty($quoteDoc['stale']) ? ' · outdated' : '' }}
                            </span>
                        @else
                            <span class="chip">Client quote: built-in template ({{ $quoteDoc['lang'] ?? 'en' }})</span>
                        @endif
                    </div>
                </div>
                <div class="eco-actions">
                    <a class="btn" href="{{ route('larapilot.dashboard.economics.quote') }}">Download quote</a>
                    <a class="btn ghost" href="{{ route('larapilot.dashboard.economics.report') }}">Internal report</a>
                </div>
            </div>

            @if (empty($profile['configured']))
                <div class="banner warn">
                    <strong>Using catalogue defaults</strong>
                    Country, hourly rate, and regime are assumed from {{ $country['name'] ?? 'Italy' }}. Confirm them with <code>/larapilot-economics</code> or <code>php artisan larapilot:economics-set --country=IT --regime={{ $regime['id'] ?? 'forfettario_15' }} --hourly-rate=…</code>
                </div>
            @endif

            @if ($warnings !== [])
                <div class="banner warn">
                    <strong>Read the hours before you send this quote</strong>
                    <ul>
                        @foreach ($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (($quoteDoc['source'] ?? 'template') === 'template')
                <div class="banner">
                    <strong>The downloadable quote is the built-in template</strong>
                    It renders in English, Italian, Spanish, or French. Run <code>/larapilot-economics</code> to have the client document written in the PRD's own language — any language — and stored at <code>.larapilot/docs/quote.md</code>.
                </div>
            @elseif (! empty($quoteDoc['stale']))
                <div class="banner warn">
                    <strong>The stored client quote predates the current backlog</strong>
                    <code>{{ $quoteDoc['path'] }}</code> was written {{ $quoteDoc['generated_at'] }}, before the latest spec / plan / PRD change. Re-run <code>/larapilot-economics</code> to rewrite it with these numbers.
                </div>
            @endif

            {{-- 1 · SCOPE & EFFORT --}}
            <section class="eco-section">
                <div class="eco-section-head">
                    <span class="eco-section-num">1</span>
                    <div>
                        <h3>Scope &amp; effort</h3>
                        <p>Everything below is derived from this number. It is built spec by spec: planned task hours when a
                            user story has a plan, story points × hours-per-point when it does not, plus a
                            {{ (int) round((((float) ($effort['buffer'] ?? 1.15)) - 1) * 100) }}% project-management and testing buffer.</p>
                    </div>
                </div>

                <div class="metrics">
                    <article class="card metric">
                        <div class="metric-label">Billable hours</div>
                        <div class="metric-value">{{ $hours($effort['billable_hours'] ?? 0) }}</div>
                        <div class="metric-sub">{{ $effort['source_label'] ?? 'Backlog' }}</div>
                        <div class="metric-hint">The hours you will invoice: {{ $hours($effort['base_hours'] ?? 0) }} of estimates
                            @if ((float) ($effort['scope_multiplier'] ?? 1) > 1)
                                × {{ $effort['scope_multiplier'] }} scope
                            @endif
                            × {{ $effort['buffer'] ?? 1.15 }} buffer.</div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Calendar time</div>
                        <div class="metric-value">{{ $effort['calendar_months'] ?? 0 }} mo</div>
                        <div class="metric-sub">{{ $effort['person_years'] ?? 0 }} person-years of capacity</div>
                        <div class="metric-hint">Elapsed months at {{ $profile['hours_per_day'] ?? 6 }}h/day, 20 days/month. Above 1.0 person-years one person cannot deliver this inside a year.</div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Backlog sized</div>
                        <div class="metric-value">{{ $effort['spec_count'] ?? 0 }}</div>
                        <div class="metric-sub">{{ $effort['planned_specs'] ?? 0 }} planned · {{ $effort['story_points'] ?? 0 }} points · {{ $effort['unsized_specs'] ?? 0 }} unsized</div>
                        <div class="metric-hint">User stories in the backlog and how firm their estimates are. A planned story carries real task hours; an unsized one is a guess.</div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Hours per point</div>
                        <div class="metric-value">{{ $effort['hours_per_point'] ?? 4 }}h</div>
                        <div class="metric-sub">{{ ($effort['hours_per_point_source'] ?? 'settings') === 'plans' ? 'Calibrated on your plans' : 'From the effort setting' }}</div>
                        <div class="metric-hint">Conversion used for stories without a plan. Once enough stories are planned, the rate those plans imply replaces the {{ $effort['hours_per_point_setting'] ?? 4 }}h default.</div>
                    </article>
                    @if ((float) ($effort['actual_hours'] ?? 0) > 0)
                        <article class="card metric">
                            <div class="metric-label">Tracked so far</div>
                            <div class="metric-value">{{ $hours($effort['actual_hours']) }}</div>
                            <div class="metric-sub">{{ $hours($effort['delivered_hours'] ?? 0) }} estimated as done</div>
                            <div class="metric-hint">Real hours in the usage ledger against the estimate for the stories already marked DONE — your estimate accuracy to date.</div>
                        </article>
                    @endif
                </div>

                <section class="card panel">
                    <h4>Where the hours come from</h4>
                    <p class="hint">{{ $effort['notes'] ?? '' }}
                        Planned task hours: {{ $hours($effort['plan_hours'] ?? 0) }} over {{ $effort['planned_tasks'] ?? 0 }} tasks ·
                        from story points: {{ $hours($effort['hours_from_points'] ?? 0) }} ({{ $effort['unplanned_points'] ?? 0 }} points) ·
                        remaining to deliver: {{ $hours($effort['remaining_hours'] ?? 0) }}.</p>
                    @if ($breakdown === [])
                        <p class="hint" style="margin:0">
                            The backlog is empty, so the hours are a scope heuristic from the inception answers
                            ({{ $inception['project_kind'] ?? 'kind unset' }} · {{ $inception['delivery_target'] ?? 'target unset' }} · {{ $inception['website_type'] ?? 'type unset' }}).
                            Run <code>/larapilot-spec</code> to build the backlog and this quote follows it.
                        </p>
                    @else
                        <div class="scroll-y">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User story</th>
                                        <th>Status</th>
                                        <th>Estimate from</th>
                                        <th class="num">Points</th>
                                        <th class="num">Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($breakdown as $row)
                                        <tr>
                                            <td><strong>{{ $row['code'] ?? '' }}</strong> — {{ $row['title'] ?? '' }}</td>
                                            <td>{{ $row['status'] ?? '' }}</td>
                                            <td>
                                                @if (($row['from'] ?? '') === 'plan')
                                                    <span class="tag plan">plan</span> {{ $row['tasks'] ?? 0 }} tasks
                                                @elseif (($row['from'] ?? '') === 'points')
                                                    <span class="tag points">points</span> × {{ $effort['hours_per_point'] ?? 4 }}h
                                                @else
                                                    <span class="tag unsized">unsized</span> assumed
                                                @endif
                                            </td>
                                            <td class="num">{{ $row['points'] ?? 0 }}</td>
                                            <td class="num">{{ $hours($row['hours'] ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="is-total">
                                        <th colspan="4">Estimates total (before the {{ (int) round((((float) ($effort['buffer'] ?? 1.15)) - 1) * 100) }}% buffer)</th>
                                        <td class="num"><strong>{{ $hours($effort['base_hours'] ?? 0) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th colspan="4">Billable hours used in the quote</th>
                                        <td class="num"><strong>{{ $hours($effort['billable_hours'] ?? 0) }}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </section>
            </section>

            {{-- 2 · WHAT THE CLIENT PAYS --}}
            <section class="eco-section">
                <div class="eco-section-head">
                    <span class="eco-section-num">2</span>
                    <div>
                        <h3>What the client pays</h3>
                        <p>Hours × your rate, plus the overhead those months cost you, plus your target margin. VAT sits on top
                            and is money you collect for the tax office, never revenue.</p>
                    </div>
                </div>

                <div class="metrics">
                    <article class="card metric">
                        <div class="metric-label">Client price</div>
                        <div class="metric-value">{{ $money($quote['gross'] ?? 0) }}</div>
                        <div class="metric-sub">ex VAT · {{ $hours($quote['billable_hours'] ?? 0) }} @ {{ $money($quote['hourly_rate'] ?? 0) }}/h</div>
                        <div class="metric-hint">The figure to put on the offer, before tax. It already includes overhead and margin.</div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Client total</div>
                        <div class="metric-value">{{ $money($quote['client_total'] ?? 0) }}</div>
                        <div class="metric-sub">
                            @if (($quote['vat_mode'] ?? 'domestic') === 'eu_b2b')
                                EU B2B reverse charge · no VAT invoiced
                            @elseif (($quote['vat_rate'] ?? 0) > 0)
                                incl. VAT {{ $quote['vat_rate'] }}%
                            @else
                                VAT exempt / not registered
                            @endif
                        </div>
                        <div class="metric-hint">What actually leaves the client's bank account. The VAT part is collected on behalf of the state.</div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Margin</div>
                        <div class="metric-value">{{ $money($quote['margin'] ?? 0) }}</div>
                        <div class="metric-sub">{{ $quote['margin_pct'] ?? 0 }}% on labour + overhead</div>
                        <div class="metric-hint">Your buffer over cost: it absorbs scope creep, unpaid days, and slow payers. Cut it and a single bad estimate makes the project unprofitable.</div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Maintenance / year</div>
                        <div class="metric-value">{{ $money($quote['maintenance_year'] ?? 0) }}</div>
                        <div class="metric-sub">{{ $money($quote['maintenance_monthly'] ?? 0) }} per month</div>
                        <div class="metric-hint">Suggested retainer at {{ $profile['maintenance_annual_pct'] ?? 15 }}% of the build: security updates, dependency upgrades, small fixes. Recurring revenue you keep after go-live.</div>
                    </article>
                </div>

                <div class="grid-2">
                    <section class="card panel">
                        <h4>How the price is built</h4>
                        <p class="hint">Each bar is a slice of the same client price. Net to owner is what survives tax — section 3.</p>
                        <div class="bars">
                            @foreach ([
                                ['Labour', 'hours × hourly rate', $quote['labor'] ?? 0, ''],
                                ['Overhead', 'tools, accountant, workspace for ' . ($quote['calendar_months'] ?? 0) . ' months', $quote['overhead'] ?? 0, ''],
                                ['Margin', 'your risk buffer and profit', $quote['margin'] ?? 0, 'is-margin'],
                                ['VAT', 'collected for the tax office', $quote['vat'] ?? 0, 'is-tax'],
                                ['Net to owner', 'after every tax and contribution', $quote['net_to_owner'] ?? 0, 'is-net'],
                            ] as [$label, $explain, $value, $mod])
                                <div class="bar-row">
                                    <span class="bar-label">{{ $label }}<small>{{ $explain }}</small></span>
                                    <div class="bar-track"><div class="bar-fill {{ $mod }}" style="width: {{ min(100, ((float) $value / $barMax) * 100) }}%"></div></div>
                                    <span class="num">{{ $money($value) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @php $oneShot = is_array($sales['one_shot'] ?? null) ? $sales['one_shot'] : []; @endphp
                    @if ($oneShot !== [])
                        <section class="card panel">
                            <h4>Selling it once</h4>
                            <p class="hint">One delivery, paid once, with maintenance recurring afterwards — the default for client work.</p>
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <th>Client price ex VAT<small>What you invoice for the build.</small></th>
                                        <td class="num">{{ $money($oneShot['client_price_ex_vat'] ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Net to owner<small>What is left for you once tax is paid.</small></th>
                                        <td class="num">{{ $money($oneShot['net_to_owner'] ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Maintenance / year<small>Recurring revenue after go-live.</small></th>
                                        <td class="num">{{ $money($oneShot['maintenance_annual'] ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Suggested licence price<small>{{ ($oneShot['license_price_source'] ?? '') === 'configured_annual_price'
                                            ? 'Your configured annual price, if you resell the same product.'
                                            : 'No price configured yet, so this is 1/80 of the build — set one with /larapilot-economics.' }}</small></th>
                                        <td class="num">{{ $money($oneShot['suggested_license_price'] ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Licences to recover the build<small>Copies to sell at that price to earn the build back.</small></th>
                                        <td class="num">{{ $oneShot['units_to_recover_build'] ?? 0 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    @endif
                </div>
            </section>

            {{-- 3 · WHAT YOU KEEP --}}
            <section class="eco-section">
                <div class="eco-section-head">
                    <span class="eco-section-num">3</span>
                    <div>
                        <h3>What you keep</h3>
                        <p>The client price minus every tax, social contribution, and compliance cost for
                            {{ $country['name'] ?? '' }} FY-{{ $fiscal_year ?? 2026 }} under {{ $regime['label'] ?? 'this regime' }}.
                            Planning figures from statutory rates — not a substitute for your accountant.</p>
                    </div>
                </div>

                <div class="metrics">
                    <article class="card metric">
                        <div class="metric-label">Net to owner</div>
                        <div class="metric-value">{{ $money($quote['net_to_owner'] ?? 0) }}</div>
                        <div class="metric-sub">{{ $tax['effective_rate_pct'] ?? 0 }}% effective tax rate</div>
                        <div class="metric-hint">Money that ends up in your personal pocket, after tax, contributions, the accountant, and the overhead these months cost you.
                            @if (! empty($tax['loss']))
                                <strong>Negative: this price does not cover its own costs.</strong>
                            @endif
                        </div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Total withheld</div>
                        <div class="metric-value">{{ $money($tax['total_withheld'] ?? $tax['total_tax'] ?? 0) }}</div>
                        <div class="metric-sub">{{ $tax['withheld_rate_pct'] ?? 0 }}% of the client price</div>
                        <div class="metric-hint">Tax, contributions, accountant, and any profit the law keeps in the company. Client price minus overhead minus this is exactly your net.</div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Net per hour</div>
                        <div class="metric-value">{{ $money($payback['effective_hourly_net'] ?? 0) }}</div>
                        <div class="metric-sub">vs {{ $money($quote['hourly_rate'] ?? 0) }} invoiced</div>
                        <div class="metric-hint">Your real take-home per worked hour. This, not the billed rate, is what you compare against a salary.</div>
                    </article>
                </div>

                <div class="grid-2">
                    <section class="card panel">
                        <h4>Tax breakdown</h4>
                        <p class="hint">{{ $regime['notes'] ?? 'Statutory snapshot for planning.' }}</p>
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th>Taxable base<small>The slice of revenue the tax is calculated on, after the deductions this regime allows.</small></th>
                                    <td class="num">{{ $money($tax['taxable'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <th>Income / corporate tax<small>The main tax on profit — IRPEF or the substitute tax for freelances, IRES for companies.</small></th>
                                    <td class="num">{{ $money($tax['income_tax'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <th>Social contributions<small>Pension and welfare (INPS and equivalents). Due even in a loss-making year.</small></th>
                                    <td class="num">{{ $money($tax['social'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <th>Local tax<small>Regional and municipal surcharges, e.g. IRAP or addizionali.</small></th>
                                    <td class="num">{{ $money($tax['local_tax'] ?? 0) }}</td>
                                </tr>
                                @if ((float) ($tax['personal_tax'] ?? 0) > 0)
                                    <tr>
                                        <th>Personal income tax<small>Tax on the salary you draw from your own company.</small></th>
                                        <td class="num">{{ $money($tax['personal_tax']) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <th>Dividend / extraction tax<small>What it costs to move profit out of the company and into your name.</small></th>
                                    <td class="num">{{ $money($tax['dividend_tax'] ?? 0) }}</td>
                                </tr>
                                @if ((float) ($tax['legal_reserve'] ?? 0) > 0)
                                    <tr>
                                        <th>Legal reserve<small>Profit the law keeps inside the company: still yours, not withdrawable.</small></th>
                                        <td class="num">{{ $money($tax['legal_reserve']) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <th>Compliance<small>Accountant, filings, chamber of commerce — the share falling on this project's months.</small></th>
                                    <td class="num">{{ $money($tax['compliance'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <th>Total tax and contributions<small>The rows above, excluding compliance and the reserve.</small></th>
                                    <td class="num">{{ $money($tax['total_tax'] ?? 0) }}</td>
                                </tr>
                                <tr class="is-total">
                                    <th>Total withheld<small>Everything that leaves before you: tax, contributions, compliance, retained reserve.</small></th>
                                    <td class="num"><strong>{{ $money($tax['total_withheld'] ?? $tax['total_tax'] ?? 0) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                        @php $extraction = is_array($tax['extraction'] ?? null) ? $tax['extraction'] : []; @endphp
                        @if ($extraction !== [])
                            <p class="hint" style="margin-top:14px;margin-bottom:0">
                                <strong>Extraction ({{ ucfirst($extraction['method'] ?? 'auto') }}):</strong> how profit reaches you.
                                @if ((float) ($extraction['director_gross'] ?? 0) > 0)
                                    Director pay {{ $money($extraction['director_gross']) }} gross → {{ $money($extraction['director_net'] ?? 0) }} net.
                                @endif
                                @if ((float) ($extraction['dividends_net'] ?? 0) > 0)
                                    Dividends {{ $money($extraction['dividends_net']) }} net.
                                @endif
                            </p>
                        @endif
                        @if (! empty($tax['over_cap']))
                            <p class="hint" style="margin-bottom:0">Revenue exceeds the {{ $money($tax['revenue_cap']) }} ceiling for this regime{{ ! empty($tax['forced_exit']) ? ' — computed under the exit regime' : ' — you must leave this regime next year' }}.</p>
                        @endif
                    </section>

                    <section class="card panel">
                        <h4>Assumptions &amp; alternatives</h4>
                        <p class="hint">Every line the tax engine assumed, and what the same project would net under the other account type.</p>
                        @if (! empty($tax['assumptions']))
                            <ul class="notes">
                                @foreach ($tax['assumptions'] as $assumption)
                                    <li>{{ $assumption }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($scenarios))
                            <h4 style="margin-top:18px">Scenarios</h4>
                            <p class="hint">Same revenue, different assumptions about how active you are in the company and how you take money out.</p>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Scenario</th>
                                        <th class="num">Net</th>
                                        <th class="num">Effective</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($scenarios as $scenario)
                                        <tr>
                                            <th>{{ $scenario['label'] ?? '' }}<small>{{ $scenario['hint'] ?? '' }}</small></th>
                                            <td class="num">{{ $money($scenario['net_to_owner'] ?? 0) }}</td>
                                            <td class="num">{{ $scenario['effective_rate_pct'] ?? 0 }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        @if (! empty($alternate))
                            <p class="hint" style="margin-top:14px;margin-bottom:0">
                                @if (($alternate['applicable'] ?? true) === false)
                                    <strong>As a {{ strtolower((string) $alternate['account']) }}</strong> ({{ $alternate['regime'] }}): not applicable — {{ $alternate['reason'] ?? 'regime ceiling exceeded' }}
                                @else
                                    <strong>As a {{ strtolower((string) $alternate['account']) }}</strong> ({{ $alternate['regime'] }}) the same project would net
                                    {{ $money($alternate['net_to_owner']) }} at {{ $alternate['effective_rate_pct'] }}% effective{{ ! empty($alternate['social']) ? ' · contributions '.$money($alternate['social']) : '' }}.
                                @endif
                            </p>
                        @endif
                    </section>
                </div>
            </section>

            {{-- 4 · PAYBACK & CAPACITY --}}
            <section class="eco-section">
                <div class="eco-section-head">
                    <span class="eco-section-num">4</span>
                    <div>
                        <h3>Payback &amp; capacity</h3>
                        <p>What this project does to your year: how much of your working time it eats, and what a full year of
                            work like this would return.</p>
                    </div>
                </div>

                <div class="metrics">
                    <article class="card metric">
                        <div class="metric-label">Capacity used</div>
                        <div class="metric-value">{{ $payback['utilization_pct'] ?? 0 }}%</div>
                        <div class="metric-sub">of {{ $hours($payback['capacity_hours_year'] ?? 0) }} billable per year</div>
                        <div class="metric-hint">Share of your yearly billable capacity ({{ $profile['billable_days_per_year'] ?? 220 }} days × {{ $profile['hours_per_day'] ?? 6 }}h) this one project takes.
                            @if (! empty($payback['over_capacity']))
                                <strong>Over 100%: it does not fit in a single year of your time.</strong>
                            @endif
                        </div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Projects per year</div>
                        <div class="metric-value">{{ $payback['projects_per_year'] ?? 0 }}</div>
                        <div class="metric-sub">at this size, fully booked</div>
                        <div class="metric-hint">How many projects of this size fit in a year if you are busy all the time and nothing overruns. Below 1 means this one project is more than a year of work.</div>
                    </article>
                    <article class="card metric">
                        <div class="metric-label">Annual net at capacity</div>
                        <div class="metric-value">{{ $money($payback['annual_net_at_capacity'] ?? 0) }}</div>
                        <div class="metric-sub">{{ $money($payback['annual_gross_at_capacity'] ?? 0) }} invoiced</div>
                        <div class="metric-hint">Your yearly take-home if every slot were filled with work priced like this. The ceiling of the current pricing.</div>
                    </article>
                    @if (! empty($payback['orders_per_month_to_recover_12m']))
                        <article class="card metric">
                            <div class="metric-label">Orders / month to break even</div>
                            <div class="metric-value">{{ $payback['orders_per_month_to_recover_12m'] }}</div>
                            <div class="metric-sub">AOV {{ $money($payback['assumed_aov'] ?? 0) }} · take rate {{ $payback['assumed_take_rate_pct'] ?? 0 }}%</div>
                            <div class="metric-hint">For an e-commerce build: monthly orders needed to earn the build back inside a year.</div>
                        </article>
                    @endif
                </div>
            </section>

            {{-- 5 · RECURRING REVENUE --}}
            @if ($saas)
                <section class="eco-section">
                    <div class="eco-section-head">
                        <span class="eco-section-num">5</span>
                        <div>
                            <h3>Recurring revenue{{ $isSaas ? '' : ' (if you sold this as a subscription)' }}</h3>
                            <p>{{ $isSaas
                                ? 'This project is priced as a subscription, so revenue arrives monthly instead of once.'
                                : 'This project is quoted as a one-off, but here is what the same product would look like sold as a subscription.' }}
                                List price {{ $money($saas['price_monthly']) }}/month ({{ $money($saas['price_annual']) }}/year, {{ $saas['annual_discount_pct'] }}% annual discount), churn {{ $saas['churn_monthly_pct'] }}%/month.</p>
                        </div>
                    </div>

                    <section class="card panel">
                        <h4>The words on this card</h4>
                        <dl class="defs">
                            <div>
                                <dt>MRR — Monthly Recurring Revenue</dt>
                                <dd>The predictable revenue invoiced every month: paying customers × monthly price. New sign-ups add to it, cancellations take it away. It is revenue, not profit — hosting, support, and tax still come out of it.</dd>
                            </div>
                            <div>
                                <dt>ARR — Annual Recurring Revenue</dt>
                                <dd>The same thing over a year: MRR × 12. It is the headline number banks, buyers, and investors ask for, and it assumes today's customers stay for twelve months. {{ $money($saas['price_monthly']) }}/month from 100 customers is {{ $money(($saas['price_monthly'] ?? 0) * 100) }} MRR and {{ $money(($saas['price_monthly'] ?? 0) * 100 * 12) }} ARR.</dd>
                            </div>
                            <div>
                                <dt>Churn</dt>
                                <dd>The share of paying customers who cancel each month. At {{ $saas['churn_monthly_pct'] }}%/month an average customer stays about {{ ((float) ($saas['churn_monthly_pct'] ?? 0)) > 0 ? (int) round(100 / (float) $saas['churn_monthly_pct']) : 0 }} months, so you must keep selling just to stand still.</dd>
                            </div>
                            <div>
                                <dt>Contribution per customer</dt>
                                <dd>{{ $money($saas['contribution_per_customer']) }} per customer per month: the price minus payment fees and the support that customer costs. This is what actually pays the fixed bills.</dd>
                            </div>
                            <div>
                                <dt>LTV and CAC</dt>
                                <dd>LTV is the total revenue one customer brings before cancelling ({{ $money($saas['ltv']) }}). CAC is what it costs in marketing and sales to win one ({{ $money($saas['cac']) }}). LTV:CAC above 3× is usually considered a healthy business; below 1× you lose money on every new customer.</dd>
                            </div>
                            <div>
                                <dt>Break-even customers</dt>
                                <dd>Paying customers needed before the monthly bills — hosting, maintenance, allocated overhead ({{ $money($saas['fixed_monthly']) }}/month) — are covered. Below that number the product costs you money every month.</dd>
                            </div>
                        </dl>
                    </section>

                    <div class="metrics">
                        <article class="card metric">
                            <div class="metric-label">Break-even customers</div>
                            <div class="metric-value">{{ $saas['break_even_customers'] }}</div>
                            <div class="metric-sub">{{ $money($saas['fixed_monthly']) }}/mo of fixed costs</div>
                            <div class="metric-hint">Where the product stops costing you money each month. Nothing here pays back the build yet.</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">Customers to repay the build</div>
                            <div class="metric-value">{{ $saas['customers_to_recover_12m'] }}</div>
                            <div class="metric-sub">in 12 months · {{ $saas['customers_to_recover_18m'] }} in 18 · {{ $saas['customers_to_recover_24m'] }} in 24</div>
                            <div class="metric-hint">Paying customers needed for the subscription to earn back the {{ $money($quote['gross'] ?? 0) }} build inside that window.</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">MRR at planning</div>
                            <div class="metric-value">{{ $money($saas['mrr_at_planning'] ?? 0) }}</div>
                            <div class="metric-sub">{{ $saas['planning_customers'] ?? 0 }} customers × {{ $money($saas['price_monthly']) }}</div>
                            <div class="metric-hint">Monthly recurring revenue at the planning customer count — the monthly invoice run you are aiming at.</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">ARR at planning</div>
                            <div class="metric-value">{{ $money($saas['arr_at_planning'] ?? 0) }}</div>
                            <div class="metric-sub">break-even ARR {{ $money($saas['arr_at_break_even']) }}</div>
                            <div class="metric-hint">That MRR seen as a year (× 12). Revenue, not profit: monthly profit at this size is {{ $money($saas['monthly_profit_at_planning'] ?? 0) }}.</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">LTV : CAC</div>
                            <div class="metric-value">{{ $saas['ltv_cac'] ?? '—' }}×</div>
                            <div class="metric-sub">LTV {{ $money($saas['ltv']) }} · CAC {{ $money($saas['cac']) }}</div>
                            <div class="metric-hint">Revenue per customer against the cost of winning one. Aim above 3×.</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">Gross margin</div>
                            <div class="metric-value">{{ $saas['gross_margin_pct'] }}%</div>
                            <div class="metric-sub">{{ $money($saas['contribution_per_customer']) }} per customer / month</div>
                            <div class="metric-hint">Share of each subscription left after payment fees and support. Software usually sits at 70–90%.</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">Leads needed</div>
                            <div class="metric-value">{{ $saas['leads_for_planning'] }}</div>
                            <div class="metric-sub">{{ $saas['conversion_pct'] }}% convert · {{ $saas['trial_to_paid_pct'] }}% trial→paid</div>
                            <div class="metric-hint">Visitors or contacts to reach {{ $saas['planning_customers'] ?? 0 }} paying customers at the configured conversion rate.</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">Months to repay</div>
                            <div class="metric-value">{{ $saas['months_to_recover_at_planning'] ?? '—' }}</div>
                            <div class="metric-sub">at {{ $saas['planning_customers'] ?? 0 }} customers</div>
                            <div class="metric-hint">How long the build takes to pay for itself once the planning customer count is reached.</div>
                        </article>
                    </div>

                    @if (! empty($saas['server_notes']))
                        <section class="card panel">
                            <h4>Running costs to expect</h4>
                            <ul class="notes">
                                @foreach ($saas['server_notes'] as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($forecast !== [])
                        <section class="card panel">
                            <h4>36-month forecast</h4>
                            <p class="hint">Each bar is ARR for that month, growing at {{ $saas['churn_monthly_pct'] }}% churn against the configured growth rate.
                                Bars turn green once cumulative profit after tax has repaid the {{ $money($quote['gross'] ?? 0) }} build.</p>
                            <div class="forecast" title="Monthly ARR over 36 months">
                                @foreach ($forecast as $row)
                                    @php $h = max(4, ((float) ($row['arr'] ?? 0) / $maxArr) * 140); @endphp
                                    <div class="forecast-bar {{ ! empty($row['recovered']) ? 'is-recovered' : '' }}" style="height: {{ $h }}px" title="M{{ $row['month'] }} · {{ $row['customers'] }} customers · ARR {{ $money($row['arr']) }} · cumulative {{ $money($row['cumulative']) }}"></div>
                                @endforeach
                            </div>
                            <table class="table" style="margin-top:16px">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="num">Customers</th>
                                        <th class="num">MRR</th>
                                        <th class="num">ARR</th>
                                        <th class="num">Net / month</th>
                                        <th class="num">Cumulative</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (collect($forecast)->whereIn('month', [1, 3, 6, 12, 18, 24, 36]) as $row)
                                        <tr>
                                            <td>M{{ $row['month'] }}</td>
                                            <td class="num">{{ $row['customers'] }}</td>
                                            <td class="num">{{ $money($row['mrr']) }}</td>
                                            <td class="num">{{ $money($row['arr']) }}</td>
                                            <td class="num">{{ $money($row['net']) }}</td>
                                            <td class="num">{{ $money($row['cumulative']) }}{{ ! empty($row['recovered']) ? ' ✓' : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="hint" style="margin:12px 0 0">Cumulative starts at −{{ $money($quote['gross'] ?? 0) }} (the build you financed) and adds each month's profit after tax.</p>
                        </section>
                    @endif
                </section>
            @endif

            {{-- 6 · INPUTS --}}
            <section class="eco-section">
                <div class="eco-section-head">
                    <span class="eco-section-num">{{ $saas ? 6 : 5 }}</span>
                    <div>
                        <h3>Inputs &amp; files</h3>
                        <p>Change any of these and every number above moves with it, on the next page load and in the stored snapshot.</p>
                    </div>
                </div>

                <div class="grid-2">
                    <section class="card panel">
                        <h4>Account profile</h4>
                        <div class="chips">
                            <span class="chip current">{{ $account }}</span>
                            <span class="chip current">{{ $country['code'] ?? '' }}</span>
                            <span class="chip current">{{ $regime['id'] ?? '' }}</span>
                            <span class="chip">{{ $currency }}</span>
                            <span class="chip">{{ $money($profile['hourly_rate'] ?? 0) }}/h</span>
                            <span class="chip">margin {{ $profile['margin_target_pct'] ?? 0 }}%</span>
                            <span class="chip">overhead {{ $money($profile['overhead_monthly'] ?? 0) }}/mo</span>
                        </div>
                        <p class="hint">Account mode lives in Settings. Country, regime, rate, margin, overhead, and subscription prices are set by <code>/larapilot-economics</code> (never by hand).</p>
                        @if (! empty($regime['options']))
                            <p class="hint" style="margin-bottom:6px">Regimes available for a {{ strtolower($account) }} account in {{ $country['name'] ?? '' }}:</p>
                            <ul class="notes">
                                @foreach ($regime['options'] as $option)
                                    <li @class(['is-current' => ($option['id'] ?? '') === ($regime['id'] ?? '')])>
                                        <code>{{ $option['id'] }}</code> — {{ $option['label'] }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="card panel">
                        <h4>Project inputs</h4>
                        <p class="hint">Inception answers size an empty backlog and pick the product model; the backlog and plans take over as soon as they exist.</p>
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th>Project kind<small>Sizes a heuristic estimate while nothing is planned.</small></th>
                                    <td>{{ $inception['project_kind'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Delivery target<small>MVP, v1, full product, enterprise.</small></th>
                                    <td>{{ $inception['delivery_target'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Product type<small>Decides whether subscription maths applies.</small></th>
                                    <td>{{ $inception['website_type'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Deploy platform<small>Sets the hosting baseline in the running costs.</small></th>
                                    <td>{{ $inception['deploy_platform'] ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Product model<small>How this project makes money.</small></th>
                                    <td>{{ $product['label'] ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="hint" style="margin:14px 0 0">
                            Profile: <code>{{ $path ?? '.larapilot/economics.yaml' }}</code><br>
                            Snapshot: <code>{{ $snapshot_path ?? '.larapilot/economics.snapshot.yaml' }}</code>@if (! empty($snapshot_saved_at)) · {{ $snapshot_saved_at }}@endif<br>
                            Client quote:
                            @if (($quoteDoc['source'] ?? 'template') === 'document')
                                <code>{{ $quoteDoc['path'] }}</code>
                            @else
                                built-in template
                            @endif
                        </p>
                    </section>
                </div>
            </section>

            <p class="disclaimer">{{ $disclaimer ?? '' }} Hours follow the backlog and plans; tax follows the FY-{{ $fiscal_year ?? 2026 }} statutory catalogue. Talk to a commercialista / accountant before you commit to anything here.</p>
        @endif
    </div>
@endsection
