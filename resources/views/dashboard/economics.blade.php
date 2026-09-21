@extends('larapilot::dashboard.layout')

@section('title', 'Economics')

@push('styles')
<style>
    body .shell:has(.economics-page) {
        max-width: none;
        padding-left: max(20px, 4vw);
        padding-right: max(20px, 4vw);
    }

    .economics-page { display: flex; flex-direction: column; gap: 20px; }

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
        max-width: 72ch;
        line-height: 1.5;
    }

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

    .metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
    .metric-hint {
        margin-top: 6px;
        color: var(--muted);
        font-size: 0.75rem;
        line-height: 1.4;
    }

    .panel { padding: 18px 20px; }
    .panel h3 { margin: 0 0 8px; font-size: 0.95rem; }
    .panel .hint {
        margin: 0 0 14px;
        color: var(--muted);
        font-size: 0.8rem;
        line-height: 1.45;
    }

    .grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 16px;
    }

    .bars { display: grid; gap: 10px; }
    .bar-row {
        display: grid;
        grid-template-columns: 140px 1fr 110px;
        gap: 10px;
        align-items: center;
        font-size: 0.85rem;
    }
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
        padding: 8px 6px;
        border-top: 1px solid var(--border);
        vertical-align: top;
    }
    .table th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
        border-top: 0;
    }
    .table .num { text-align: right; font-variant-numeric: tabular-nums; }

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

    .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .chip {
        display: inline-flex;
        align-items: center;
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
        line-height: 1.45;
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
        $quote = is_array($quote ?? null) ? $quote : [];
        $tax = is_array($tax ?? null) ? $tax : [];
        $saas = is_array($saas ?? null) ? $saas : null;
        $effort = is_array($effort ?? null) ? $effort : [];
        $payback = is_array($payback ?? null) ? $payback : [];
        $forecast = is_array($forecast ?? null) ? $forecast : [];
        $gross = (float) ($quote['gross'] ?? 0);
        $barMax = max($gross, (float) ($quote['client_total'] ?? 0), 1);
        $maxArr = 1.0;
        foreach ($forecast as $row) {
            $maxArr = max($maxArr, (float) ($row['arr'] ?? 0));
        }
    @endphp

    <div class="economics-page">
        @if (! $enabled)
            <section class="card empty-card">
                <h2>Economics is off</h2>
                <p>Turn on <strong>Account</strong> mode to generate real quotes: freelance (partita IVA, forfettario, sole trader) or company (SRL, SPA, Ltd) calibrated to current country tax, plus payback and SaaS forecasts.</p>
                <p>Enable with <code>/larapilot-settings</code> or:</p>
                <p><code>php artisan larapilot:settings-set --account=FREELANCE</code><br>
                <code>php artisan larapilot:settings-set --account=COMPANY</code></p>
                <p>Then calibrate country, regime, and rates with <code>/larapilot-economics</code>.</p>
            </section>
        @else
            <div class="eco-top">
                <div>
                    <h2>Economics</h2>
                    <p class="sub">{{ $account === 'FREELANCE' ? 'Freelance' : 'Company' }} quote for
                        {{ $country['name'] ?? 'the selected country' }}
                        · {{ $regime['label'] ?? '' }}
                        · {{ $product['label'] ?? 'delivery' }}.
                        Numbers follow inception answers, plan hours, and FY-{{ $fiscal_year ?? 2026 }} statutory rates.</p>
                </div>
                <a class="btn" href="{{ route('larapilot.dashboard.economics.report') }}">Download report</a>
            </div>

            @if (empty($profile['configured']))
                <div class="banner warn">
                    <strong>Using catalogue defaults</strong>
                    Country, hourly rate, and regime are assumed from {{ $country['name'] ?? 'Italy' }}. Confirm them with <code>/larapilot-economics</code> or <code>php artisan larapilot:economics-set --country=IT --regime={{ $regime['id'] ?? 'forfettario_15' }} --hourly-rate=…</code>
                </div>
            @endif

            <div class="metrics">
                <article class="card metric">
                    <div class="metric-label">Client price</div>
                    <div class="metric-value">{{ $money($quote['gross'] ?? 0) }}</div>
                    <div class="metric-hint">ex VAT · {{ $quote['billable_hours'] ?? 0 }}h @ {{ $money($quote['hourly_rate'] ?? 0) }}/h</div>
                </article>
                <article class="card metric">
                    <div class="metric-label">Client total</div>
                    <div class="metric-value">{{ $money($quote['client_total'] ?? 0) }}</div>
                    <div class="metric-hint">{{ ($quote['vat_rate'] ?? 0) > 0 ? 'incl. VAT '.$quote['vat_rate'].'%' : 'VAT exempt / not registered' }}</div>
                </article>
                <article class="card metric">
                    <div class="metric-label">Net to owner</div>
                    <div class="metric-value">{{ $money($quote['net_to_owner'] ?? 0) }}</div>
                    <div class="metric-hint">After tax, social, compliance · {{ $tax['effective_rate_pct'] ?? 0 }}% effective</div>
                </article>
                <article class="card metric">
                    <div class="metric-label">Margin</div>
                    <div class="metric-value">{{ $money($quote['margin'] ?? 0) }}</div>
                    <div class="metric-hint">{{ $quote['margin_pct'] ?? 0 }}% on labor + overhead</div>
                </article>
                <article class="card metric">
                    <div class="metric-label">Maintenance / yr</div>
                    <div class="metric-value">{{ $money($quote['maintenance_year'] ?? 0) }}</div>
                    <div class="metric-hint">{{ $profile['maintenance_annual_pct'] ?? 15 }}% of the build · {{ $money($quote['maintenance_monthly'] ?? 0) }}/mo</div>
                </article>
                @if ($saas)
                    <article class="card metric">
                        <div class="metric-label">ARR at planning</div>
                        <div class="metric-value">{{ $money($saas['arr_at_planning'] ?? 0) }}</div>
                        <div class="metric-hint">{{ $saas['planning_customers'] ?? 0 }} customers · {{ $money($saas['price_monthly'] ?? 0) }}/mo</div>
                    </article>
                @endif
            </div>

            <div class="grid-2">
                <section class="card panel">
                    <h3>Preventivo</h3>
                    <p class="hint">Effort from {{ str_replace('_', ' ', $effort['source'] ?? 'heuristic') }}
                        @if (($effort['spec_count'] ?? 0) > 0)
                            · {{ $effort['spec_count'] }} specs · {{ $effort['story_points'] ?? 0 }} SP
                        @endif
                        · delivery ×{{ $effort['delivery_multiplier'] ?? 1 }}
                        · {{ $effort['calendar_months'] ?? 0 }} months.</p>

                    <div class="bars">
                        @foreach ([
                            ['Labor', $quote['labor'] ?? 0, ''],
                            ['Overhead', $quote['overhead'] ?? 0, ''],
                            ['Margin', $quote['margin'] ?? 0, 'is-margin'],
                            ['VAT', $quote['vat'] ?? 0, 'is-tax'],
                            ['Net to owner', $quote['net_to_owner'] ?? 0, 'is-net'],
                        ] as [$label, $value, $mod])
                            <div class="bar-row">
                                <span>{{ $label }}</span>
                                <div class="bar-track"><div class="bar-fill {{ $mod }}" style="width: {{ min(100, ((float) $value / $barMax) * 100) }}%"></div></div>
                                <span>{{ $money($value) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="card panel">
                    <h3>Tax · {{ $country['name'] ?? '' }} FY-{{ $fiscal_year ?? 2026 }}</h3>
                    <p class="hint">{{ $regime['notes'] ?? 'Statutory snapshot for planning.' }}</p>
                    <table class="table">
                        <tbody>
                            <tr><th>Taxable base</th><td class="num">{{ $money($tax['taxable'] ?? 0) }}</td></tr>
                            <tr><th>Income / corporate</th><td class="num">{{ $money($tax['income_tax'] ?? 0) }}</td></tr>
                            <tr><th>Social contributions</th><td class="num">{{ $money($tax['social'] ?? 0) }}</td></tr>
                            <tr><th>Local tax</th><td class="num">{{ $money($tax['local_tax'] ?? 0) }}</td></tr>
                            <tr><th>Dividend / extraction</th><td class="num">{{ $money($tax['dividend_tax'] ?? 0) }}</td></tr>
                            <tr><th>Compliance (allocated)</th><td class="num">{{ $money($tax['compliance'] ?? 0) }}</td></tr>
                            <tr><th>Total withdrawn</th><td class="num"><strong>{{ $money($tax['total_tax'] ?? 0) }}</strong></td></tr>
                        </tbody>
                    </table>
                    @if (! empty($tax['over_cap']))
                        <p class="hint">Revenue exceeds the {{ $money($tax['revenue_cap']) }} regime ceiling — switch regime or split the year.</p>
                    @endif
                    @if (! empty($alternate))
                        <p class="hint" style="margin-top:14px">If this were a <strong>{{ $alternate['account'] }}</strong> ({{ $alternate['regime'] }}): net {{ $money($alternate['net_to_owner']) }} at {{ $alternate['effective_rate_pct'] }}% effective.</p>
                    @endif
                </section>
            </div>

            <div class="grid-2">
                <section class="card panel">
                    <h3>Effort &amp; payback</h3>
                    <p class="hint">
                        @if (($effort['actual_hours'] ?? 0) > 0)
                            Lucille tracked {{ $effort['actual_hours'] }}h so far vs {{ $effort['billable_hours'] }}h quoted.
                        @else
                            No usage ledger yet — quote uses {{ $effort['source'] === 'plan_hours' ? 'plan task hours' : ($effort['source'] === 'story_points' ? 'story points × hours/point' : 'delivery-target heuristic') }}.
                        @endif
                    </p>
                    <table class="table">
                        <tbody>
                            <tr><th>Billable hours</th><td class="num">{{ $effort['billable_hours'] ?? 0 }}</td></tr>
                            <tr><th>Net / hour</th><td class="num">{{ $money($payback['effective_hourly_net'] ?? 0) }}</td></tr>
                            <tr><th>Capacity used</th><td class="num">{{ $payback['utilization_pct'] ?? 0 }}%</td></tr>
                            <tr><th>Projects / year at capacity</th><td class="num">{{ $payback['projects_per_year'] ?? 0 }}</td></tr>
                            <tr><th>Annual net at capacity</th><td class="num">{{ $money($payback['annual_net_at_capacity'] ?? 0) }}</td></tr>
                            @if (! empty($payback['licenses_to_recover']))
                                <tr><th>Licenses to recover</th><td class="num">{{ $payback['licenses_to_recover'] }} @ {{ $money($payback['suggested_license_price']) }}</td></tr>
                            @endif
                            @if (! empty($payback['orders_per_month_to_recover_12m']))
                                <tr><th>Orders / mo to recover (12m)</th><td class="num">{{ $payback['orders_per_month_to_recover_12m'] }} (AOV {{ $money($payback['assumed_aov']) }})</td></tr>
                            @endif
                        </tbody>
                    </table>
                </section>

                <section class="card panel">
                    <h3>Account profile</h3>
                    <div class="chips">
                        <span class="chip current">{{ $account }}</span>
                        <span class="chip current">{{ $country['code'] ?? '' }}</span>
                        <span class="chip current">{{ $regime['id'] ?? '' }}</span>
                        <span class="chip">{{ $currency }}</span>
                    </div>
                    <p class="hint">Change the mode in Settings. Calibrate country, regime, rate, and SaaS prices with <code>/larapilot-economics</code>.</p>
                    @if (! empty($regime['options']))
                        <p class="hint">Regimes available for this account in {{ $country['name'] ?? '' }}:</p>
                        <ul class="notes">
                            @foreach ($regime['options'] as $option)
                                <li @class(['is-current' => ($option['id'] ?? '') === ($regime['id'] ?? '')])>
                                    <code>{{ $option['id'] }}</code> — {{ $option['label'] }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            @if ($saas)
                <section class="card panel">
                    <h3>SaaS economics</h3>
                    <p class="hint">Subscription model detected from inception / PRD. Price {{ $money($saas['price_monthly']) }}/mo ({{ $money($saas['price_annual']) }}/yr, {{ $saas['annual_discount_pct'] }}% annual discount). Churn {{ $saas['churn_monthly_pct'] }}%/mo.</p>

                    <div class="metrics" style="margin-bottom:18px">
                        <article class="card metric">
                            <div class="metric-label">Break-even customers</div>
                            <div class="metric-value">{{ $saas['break_even_customers'] }}</div>
                            <div class="metric-hint">Cover hosting + maintenance + allocated overhead</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">Recover build in 12 months</div>
                            <div class="metric-value">{{ $saas['customers_to_recover_12m'] }}</div>
                            <div class="metric-hint">18m: {{ $saas['customers_to_recover_18m'] }} · 24m: {{ $saas['customers_to_recover_24m'] }}</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">ARR at break-even</div>
                            <div class="metric-value">{{ $money($saas['arr_at_break_even']) }}</div>
                            <div class="metric-hint">MRR {{ $money(($saas['break_even_customers'] ?? 0) * ($saas['price_monthly'] ?? 0)) }}</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">LTV / CAC</div>
                            <div class="metric-value">{{ $saas['ltv_cac'] ?? '—' }}×</div>
                            <div class="metric-hint">LTV {{ $money($saas['ltv']) }} · CAC {{ $money($saas['cac']) }}</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">Gross margin</div>
                            <div class="metric-value">{{ $saas['gross_margin_pct'] }}%</div>
                            <div class="metric-hint">{{ $money($saas['contribution_per_customer']) }} contribution / customer / mo</div>
                        </article>
                        <article class="card metric">
                            <div class="metric-label">Leads for planning</div>
                            <div class="metric-value">{{ $saas['leads_for_planning'] }}</div>
                            <div class="metric-hint">{{ $saas['conversion_pct'] }}% conversion · {{ $saas['trial_to_paid_pct'] }}% trial→paid</div>
                        </article>
                    </div>

                    @if (! empty($saas['server_notes']))
                        <ul class="notes">
                            @foreach ($saas['server_notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($forecast !== [])
                        <h3 style="margin-top:22px">36-month ARR forecast</h3>
                        <p class="hint">Bars are ARR. Green means cumulative profit has repaid the build investment ({{ $money($quote['gross'] ?? 0) }}).</p>
                        <div class="forecast" title="Monthly ARR over 36 months">
                            @foreach ($forecast as $row)
                                @php $h = max(4, ((float) ($row['arr'] ?? 0) / $maxArr) * 140); @endphp
                                <div class="forecast-bar {{ ! empty($row['recovered']) ? 'is-recovered' : '' }}" style="height: {{ $h }}px" title="M{{ $row['month'] }} · {{ $row['customers'] }} cust · ARR {{ $money($row['arr']) }} · cum {{ $money($row['cumulative']) }}"></div>
                            @endforeach
                        </div>
                        <table class="table" style="margin-top:16px">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th class="num">Customers</th>
                                    <th class="num">MRR</th>
                                    <th class="num">ARR</th>
                                    <th class="num">Net</th>
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
                    @endif
                </section>
            @endif

            <p class="disclaimer">{{ $disclaimer ?? '' }} Override rates in <code>.larapilot/economics.yaml</code> via <code>larapilot:economics-set</code>. Inception: {{ $inception['project_kind'] ?? '—' }} · {{ $inception['delivery_target'] ?? '—' }} · {{ $inception['deploy_platform'] ?? 'deploy unset' }}.</p>
        @endif
    </div>
@endsection
