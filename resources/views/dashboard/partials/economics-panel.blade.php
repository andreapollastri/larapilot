@php
    $enabled = (bool) ($enabled ?? false);
    $account = $account ?? 'NONE';
    $quote = is_array($quote ?? null) ? $quote : [];
    $tax = is_array($tax ?? null) ? $tax : [];
    $saas = is_array($saas ?? null) ? $saas : null;
    $sales = is_array($sales ?? null) ? $sales : [];
    $effort = is_array($effort ?? null) ? $effort : [];
    $payback = is_array($payback ?? null) ? $payback : [];
    $packaging = is_array($packaging ?? null) ? $packaging : null;
    $plan = is_array($business_plan ?? null) ? $business_plan : null;
    $maintenance = is_array($maintenance ?? null) ? $maintenance : null;
    $market = is_array($market ?? null) ? $market : ['available' => false];
    $controls = is_array($controls ?? null) ? $controls : [];
    $simulation = is_array($simulation ?? null) ? $simulation : ['active' => false, 'overrides' => []];
    $quoteDoc = is_array($quote_document ?? null) ? $quote_document : [];
    $breakdown = is_array($effort['breakdown'] ?? null) ? $effort['breakdown'] : [];
    $warnings = is_array($effort['warnings'] ?? null) ? $effort['warnings'] : [];

    $currency = $country['currency'] ?? ($profile['currency'] ?? 'EUR');
    $money = static fn (mixed $amount): string => $currency.' '.number_format((float) $amount, 0, '.', ',');
    $money2 = static fn (mixed $amount): string => $currency.' '.number_format((float) $amount, 2, '.', ',');
    $hours = static fn (mixed $value): string => rtrim(rtrim(number_format((float) $value, 1, '.', ','), '0'), '.').'h';

    $overrides = is_array($simulation['overrides'] ?? null) ? $simulation['overrides'] : [];
    $query = http_build_query($overrides);
    $link = static fn (string $route): string => route($route).($query !== '' ? '?'.$query : '');

    $isSaas = ($product['model'] ?? '') === 'saas';
    $grouped = [];
    foreach (is_array($controls['controls'] ?? null) ? $controls['controls'] : [] as $control) {
        $grouped[$control['group']][] = $control;
    }
    $productControl = null;
    foreach ($grouped['account'] ?? [] as $index => $control) {
        if ($control['key'] === 'product_model') {
            $productControl = $control;
            unset($grouped['account'][$index]);
        }
    }
    $selectedLine = null;
    foreach (is_array($plan['lines'] ?? null) ? $plan['lines'] : [] as $line) {
        if (! empty($line['selected'])) {
            $selectedLine = $line;
        }
    }
    $forecast = is_array($selectedLine['forecast'] ?? null) ? $selectedLine['forecast'] : [];
    $maxArr = 1.0;
    foreach ($forecast as $row) {
        $maxArr = max($maxArr, (float) ($row['arr'] ?? 0));
    }
    $barMax = max((float) ($quote['list_price'] ?? 0), (float) ($quote['client_total'] ?? 0), 1);
@endphp

@if (! $enabled)
    <section class="card empty-card">
        <h2>Economics is off</h2>
        <p>Turn on <strong>Account</strong> mode to price projects: freelance (partita IVA, forfettario, sole trader) or company (SRL, Ltd, GmbH) calibrated to current country tax, with quote, packaging, and subscription forecasts.</p>
        <p><code>php artisan larapilot:settings-set --account=FREELANCE</code><br>
        <code>php artisan larapilot:settings-set --account=COMPANY</code></p>
        <p>Then calibrate country, regime, and rates with <code>/larapilot-economics</code>.</p>
    </section>
@else
    <div class="eco-top">
        <div>
            <h2>Economics</h2>
            <p class="sub">Price this project and see what survives tax. Change anything in the console below and every
                figure, chart, and the downloadable quote move with it — nothing is saved until you run the command it gives you.</p>
            <div class="chips" style="margin:10px 0 0">
                @if (($quoteDoc['source'] ?? 'template') === 'document')
                    <span class="chip {{ ! empty($quoteDoc['stale']) ? 'stale' : 'current' }}">
                        Client quote: written document{{ ! empty($quoteDoc['lang']) ? ' ('.$quoteDoc['lang'].')' : '' }}{{ ! empty($quoteDoc['stale']) ? ' · outdated' : '' }}
                    </span>
                @else
                    <span class="chip">Client quote: built-in template ({{ $quoteDoc['lang'] ?? 'en' }})</span>
                @endif
                <span class="chip">{{ $regime['label'] ?? '' }} · {{ $country['name'] ?? '' }}</span>
                <span class="chip">FY-{{ $fiscal_year ?? 2026 }} statutory rates</span>
            </div>
        </div>
        <div class="eco-actions">
            <a class="btn" href="{{ $link('larapilot.dashboard.economics.quote') }}">Download quote</a>
            <a class="btn ghost" href="{{ $link('larapilot.dashboard.economics.report') }}">Internal report</a>
        </div>
    </div>

    {{-- THE PRICING CONSOLE --}}
    <form id="eco-controls" class="card eco-console" method="get" action="{{ route('larapilot.dashboard.economics') }}">
        @if ($productControl)
            <div class="eco-console-row">
                <span class="eco-console-title">Sold as</span>
                <div class="eco-toggle">
                    @foreach ($productControl['options'] as $option)
                        <label @class(['is-on' => (string) $option['value'] === (string) $productControl['value']])>
                            <input type="radio" name="product_model" value="{{ $option['value'] }}"
                                data-eco-default="{{ $productControl['saved'] }}"
                                @checked((string) $option['value'] === (string) $productControl['value'])>
                            <span>{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="eco-console-actions">
                    @if (! empty($simulation['active']))
                        <span class="chip stale">Simulation · {{ count($overrides) }} changed</span>
                        <a class="btn ghost small" href="{{ route('larapilot.dashboard.economics') }}" data-eco-reset>Reset to profile</a>
                    @else
                        <span class="chip live">Saved profile</span>
                    @endif
                    <noscript><button class="btn small" type="submit">Recompute</button></noscript>
                </div>
            </div>
        @endif

        @foreach (['quote' => 'The quote', 'account' => 'Who is selling', 'saas' => 'Subscription'] as $group => $title)
            @php $fields = $grouped[$group] ?? []; @endphp
            @if ($fields !== [] && ($group !== 'saas' || $isSaas))
                <div class="eco-console-group">
                    <span class="eco-console-title">{{ $title }}</span>
                    <div class="eco-fields">
                        @foreach ($fields as $control)
                            <label class="eco-field" title="{{ $control['hint'] }}">
                                <span class="eco-field-label">
                                    {{ $control['label'] }}
                                    @if (! empty($control['overridden']))<b title="Changed for this simulation">•</b>@endif
                                </span>
                                <select name="{{ $control['key'] }}" data-eco-default="{{ $control['saved'] }}">
                                    @foreach ($control['options'] as $option)
                                        <option value="{{ $option['value'] }}" @selected((string) $option['value'] === (string) $control['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </form>

    @if (! empty($simulation['active']))
        <div class="banner">
            <strong>This is a simulation — the project profile is untouched</strong>
            Run this to make it the real one, then rewrite the client quote with <code>/larapilot-economics</code>:
            <pre class="eco-command" data-eco-command>{{ $simulation['command'] }}</pre>
            <button class="btn ghost small" type="button" data-eco-copy>Copy command</button>
        </div>
    @endif

    @if (($quoteDoc['source'] ?? 'template') === 'document' && ! empty($quoteDoc['stale']))
        <div class="banner warn">
            <strong>The stored client quote predates the current backlog</strong>
            <code>{{ $quoteDoc['path'] }}</code> was written {{ $quoteDoc['generated_at'] }}, before the latest spec, plan, or PRD change.
            Re-run <code>/larapilot-economics</code> to rewrite it with these numbers.
        </div>
    @endif

    @if (empty($profile['configured']))
        <div class="banner warn">
            <strong>Using catalogue defaults</strong>
            Country, hourly rate, and regime are assumed from {{ $country['name'] ?? 'the catalogue' }}. Confirm them with <code>/larapilot-economics</code>.
        </div>
    @endif

    @if ($warnings !== [] || ! empty($quote['below_cost']))
        <div class="banner warn">
            <strong>Read this before you send the quote</strong>
            <ul>
                @if (! empty($quote['below_cost']))
                    <li>The discount takes the price below what the project costs to deliver: {{ $money($quote['gross'] ?? 0) }} against {{ $money($quote['direct'] ?? 0) }} of labour and overhead.</li>
                @endif
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 1 · HEADLINE --}}
    <section class="eco-section">
        <div class="eco-section-head">
            <span class="eco-section-num">1</span>
            <div>
                <h3>The numbers that decide it</h3>
                <p>{{ $isSaas
                    ? 'Priced as a subscription on the '.($plan['tier_name'] ?? 'PRO').' line at '.$money2($plan['price_monthly'] ?? 0).'/month, with the build financed up front.'
                    : 'Priced as one delivery: the client pays once and the maintenance retainer recurs afterwards.' }}</p>
            </div>
        </div>

        <div class="metrics">
            <article class="card metric">
                <div class="metric-label">Client price</div>
                <div class="metric-value">{{ $money($quote['gross'] ?? 0) }}</div>
                <div class="metric-sub">
                    @if ((float) ($quote['discount_pct'] ?? 0) > 0)
                        list {{ $money($quote['list_price'] ?? 0) }} − {{ $quote['discount_pct'] }}%
                    @else
                        ex VAT · {{ $hours($quote['billable_hours'] ?? 0) }} @ {{ $money($quote['hourly_rate'] ?? 0) }}/h
                    @endif
                </div>
                <div class="metric-hint">What goes on the offer before tax. Client total with VAT: {{ $money($quote['client_total'] ?? 0) }}.</div>
            </article>
            <article class="card metric">
                <div class="metric-label">Net to owner</div>
                <div class="metric-value">{{ $money($quote['net_to_owner'] ?? 0) }}</div>
                <div class="metric-sub">{{ $tax['withheld_rate_pct'] ?? 0 }}% withheld · {{ $money($payback['effective_hourly_net'] ?? 0) }}/h net</div>
                <div class="metric-hint">Client price minus overhead minus every tax, contribution, and the accountant.
                    @if (! empty($tax['loss']))<strong>Negative: this price does not cover its own costs.</strong>@endif
                </div>
            </article>
            <article class="card metric">
                <div class="metric-label">Margin left</div>
                <div class="metric-value">{{ $money($quote['margin_after_discount'] ?? $quote['margin'] ?? 0) }}</div>
                <div class="metric-sub">{{ $quote['margin_after_discount_pct'] ?? $quote['margin_pct'] ?? 0 }}% of cost @if ((float) ($quote['discount'] ?? 0) > 0) · {{ $money($quote['discount']) }} given away @endif</div>
                <div class="metric-hint">Your buffer after the discount. It absorbs scope creep and unpaid days — the first thing a discount eats.</div>
            </article>
            <article class="card metric">
                <div class="metric-label">Delivery</div>
                <div class="metric-value">{{ $effort['calendar_months'] ?? 0 }} mo</div>
                <div class="metric-sub">{{ $effort['team_size'] ?? 1 }} {{ ((float) ($effort['team_size'] ?? 1)) == 1.0 ? 'person' : 'people' }} · {{ $hours($effort['billable_hours'] ?? 0) }}</div>
                <div class="metric-hint">Elapsed months at {{ $profile['hours_per_day'] ?? 6 }}h/day. Solo it would take {{ $effort['solo_months'] ?? 0 }} month{{ ((float) ($effort['solo_months'] ?? 0)) == 1.0 ? '' : 's' }}; the price does not change with the team.</div>
            </article>
            @if ($isSaas && $saas)
                <article class="card metric">
                    <div class="metric-label">Break-even customers</div>
                    <div class="metric-value">{{ $saas['break_even_customers'] }}</div>
                    <div class="metric-sub">{{ $money($saas['fixed_monthly']) }}/mo of fixed costs</div>
                    <div class="metric-hint">Paying accounts before the product stops costing you money every month.</div>
                </article>
                <article class="card metric">
                    <div class="metric-label">ARR at plan</div>
                    <div class="metric-value">{{ $money($selectedLine['arr_m36'] ?? ($saas['arr_at_planning'] ?? 0)) }}</div>
                    <div class="metric-sub">month 36 · {{ $selectedLine['customers_m36'] ?? 0 }} customers</div>
                    <div class="metric-hint">{{ ucfirst((string) ($plan['selected'] ?? 'realistic')) }} line. Build repaid
                        {{ ($selectedLine['recovered_month'] ?? null) ? 'in month '.$selectedLine['recovered_month'] : 'nowhere inside 36 months' }}.</div>
                </article>
            @endif
        </div>
    </section>

    {{-- 2 · EFFORT --}}
    <section class="eco-section">
        <div class="eco-section-head">
            <span class="eco-section-num">2</span>
            <div>
                <h3>Where the hours come from</h3>
                <p>{{ $effort['source_label'] ?? 'Backlog' }} — {{ $hours($effort['base_hours'] ?? 0) }} of estimates plus a
                    {{ (int) round(((float) ($effort['buffer'] ?? 1.15) - 1) * 100) }}% PM/QA buffer, {{ $hours($effort['billable_hours'] ?? 0) }} billable.
                    Story points convert at {{ $effort['hours_per_point'] ?? 4 }}h
                    ({{ ($effort['hours_per_point_source'] ?? 'settings') === 'plans' ? 'calibrated on your plans' : 'from the effort setting' }}).</p>
            </div>
        </div>

        @php
            $groupRows = is_array($effort['by_release'] ?? null) && $effort['by_release'] !== [] ? $effort['by_release'] : (is_array($effort['by_epic'] ?? null) ? $effort['by_epic'] : []);
            $groupLabel = is_array($effort['by_release'] ?? null) && $effort['by_release'] !== [] ? 'Release' : 'Epic';
        @endphp

        @if ($groupRows !== [])
            <section class="card panel">
                <h4>By {{ strtolower($groupLabel) }}</h4>
                <p class="hint">The same hours, grouped the way the project is delivered. Totals add up to the billable hours above.</p>
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ $groupLabel }}</th>
                            <th class="num">Stories</th>
                            <th class="num">Points</th>
                            <th class="num">Hours</th>
                            <th class="num">Labour</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupRows as $row)
                            <tr>
                                <th>{{ $row['label'] }}<small>{{ $row['done'] }} of {{ $row['specs'] }} delivered</small></th>
                                <td class="num">{{ $row['specs'] }}</td>
                                <td class="num">{{ $row['points'] }}</td>
                                <td class="num">{{ $hours($row['billable_hours']) }}</td>
                                <td class="num">{{ $money($row['labor']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <section class="card panel">
            <h4>By user story</h4>
            @if ($breakdown === [])
                <p class="hint" style="margin:0">The backlog is empty, so the hours are a scope heuristic from the inception answers
                    ({{ $inception['project_kind'] ?? 'kind unset' }} · {{ $inception['delivery_target'] ?? 'target unset' }} · {{ $inception['website_type'] ?? 'type unset' }}).
                    Run <code>/larapilot-spec</code> and the quote follows the backlog instead.</p>
            @else
                <p class="hint">Every story with the source of its hours — a planned story carries real task hours, an unsized one is a guess.</p>
                <div class="scroll-y">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User story</th>
                                <th>Status</th>
                                <th>From</th>
                                <th class="num">Points</th>
                                <th class="num">Hours</th>
                                <th class="num">Labour</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($breakdown as $row)
                                <tr>
                                    <td><strong>{{ $row['code'] ?? '' }}</strong> — {{ $row['title'] ?? '' }}
                                        @if (! empty($row['epic']) || ! empty($row['release']))
                                            <small style="display:block;color:var(--muted)">{{ trim(($row['epic'] ?? '').(! empty($row['epic']) && ! empty($row['release']) ? ' · ' : '').($row['release'] ?? '')) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $row['status'] ?? '' }}</td>
                                    <td>
                                        @if (($row['from'] ?? '') === 'plan')
                                            <span class="tag plan">plan</span>
                                        @elseif (($row['from'] ?? '') === 'points')
                                            <span class="tag points">points</span>
                                        @else
                                            <span class="tag unsized">unsized</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ $row['points'] ?? 0 }}</td>
                                    <td class="num">{{ $hours($row['hours'] ?? 0) }}</td>
                                    <td class="num">{{ $money(((float) ($row['hours'] ?? 0)) * ((float) ($effort['buffer'] ?? 1.15)) * ((float) ($quote['hourly_rate'] ?? 0))) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="is-total">
                                <th colspan="4">Billable hours in this quote</th>
                                <td class="num"><strong>{{ $hours($effort['billable_hours'] ?? 0) }}</strong></td>
                                <td class="num"><strong>{{ $money($quote['labor'] ?? 0) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    </section>

    {{-- 3 · THE BUILD --}}
    <section class="eco-section">
        <div class="eco-section-head">
            <span class="eco-section-num">3</span>
            <div>
                <h3>What the client pays and what you keep</h3>
                <p>{{ $country['name'] ?? '' }} FY-{{ $fiscal_year ?? 2026 }}, {{ $regime['label'] ?? 'this regime' }},
                    {{ strtolower($account) }} account. Planning figures from statutory rates — not a substitute for your accountant.</p>
            </div>
        </div>

        <div class="grid-2">
            <section class="card panel">
                <h4>How the price is built</h4>
                <p class="hint">Each bar is a slice of the same list price. Net to owner is what survives tax.</p>
                <div class="bars">
                    @foreach ([
                        ['Labour', $hours($quote['billable_hours'] ?? 0).' × '.$money($quote['hourly_rate'] ?? 0), $quote['labor'] ?? 0, ''],
                        ['Overhead', ($effort['person_months'] ?? 0).' person-months of tools, workspace, accountant', $quote['overhead'] ?? 0, ''],
                        ['Margin', ($quote['margin_pct'] ?? 0).'% on labour and overhead', $quote['margin'] ?? 0, 'is-margin'],
                        ['Discount', 'given away at the table', -1 * (float) ($quote['discount'] ?? 0), 'is-tax'],
                        ['VAT', 'collected for the tax office', $quote['vat'] ?? 0, 'is-tax'],
                        ['Net to owner', 'after every tax and contribution', $quote['net_to_owner'] ?? 0, 'is-net'],
                    ] as [$label, $explain, $value, $mod])
                        @if ($label !== 'Discount' || (float) $value !== 0.0)
                            <div class="bar-row">
                                <span class="bar-label">{{ $label }}<small>{{ $explain }}</small></span>
                                <div class="bar-track"><div class="bar-fill {{ $mod }}" style="width: {{ min(100, (abs((float) $value) / $barMax) * 100) }}%"></div></div>
                                <span class="num">{{ $money($value) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>

            <section class="card panel">
                <h4>Tax and contributions</h4>
                <p class="hint">{{ $regime['notes'] ?? 'Statutory snapshot for planning.' }}</p>
                <table class="table">
                    <tbody>
                        <tr>
                            <th>Taxable base<small>The slice of revenue the tax is calculated on.</small></th>
                            <td class="num">{{ $money($tax['taxable'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th>Income / corporate tax</th>
                            <td class="num">{{ $money($tax['income_tax'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th>Social contributions<small>Pension and welfare. Due even in a loss-making year.</small></th>
                            <td class="num">{{ $money($tax['social'] ?? 0) }}</td>
                        </tr>
                        @if ((float) ($tax['local_tax'] ?? 0) > 0)
                            <tr><th>Local tax</th><td class="num">{{ $money($tax['local_tax']) }}</td></tr>
                        @endif
                        @if ((float) ($tax['dividend_tax'] ?? 0) > 0)
                            <tr><th>Dividend / extraction tax</th><td class="num">{{ $money($tax['dividend_tax']) }}</td></tr>
                        @endif
                        @if ((float) ($tax['legal_reserve'] ?? 0) > 0)
                            <tr><th>Legal reserve<small>Profit the law keeps in the company: still yours, not withdrawable.</small></th><td class="num">{{ $money($tax['legal_reserve']) }}</td></tr>
                        @endif
                        <tr>
                            <th>Compliance<small>Accountant and filings for these months.</small></th>
                            <td class="num">{{ $money($tax['compliance'] ?? 0) }}</td>
                        </tr>
                        <tr class="is-total">
                            <th>Total withheld</th>
                            <td class="num"><strong>{{ $money($tax['total_withheld'] ?? $tax['total_tax'] ?? 0) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
                @if (! empty($alternate) && ($alternate['applicable'] ?? false))
                    <p class="hint" style="margin:12px 0 0">As a {{ strtolower((string) $alternate['account']) }} ({{ $alternate['regime'] }}) the same project would net
                        {{ $money($alternate['net_to_owner']) }} at {{ $alternate['effective_rate_pct'] }}% effective. Switch the account dropdown to price it properly.</p>
                @endif
            </section>
        </div>

        <div class="metrics">
            <article class="card metric">
                <div class="metric-label">Maintenance / year</div>
                <div class="metric-value">{{ $money($quote['maintenance_year'] ?? 0) }}</div>
                <div class="metric-sub">{{ $money($quote['maintenance_monthly'] ?? 0) }} per month · {{ $profile['maintenance_annual_pct'] ?? 15 }}% of the build</div>
                <div class="metric-hint">
                    @if ($maintenance && ! empty($maintenance['adopted']))
                        Taken from the inception answers, which recommend {{ $maintenance['recommended_pct'] }}%.
                    @elseif ($maintenance && ! $maintenance['follows_inception'])
                        <strong>The inception answers recommend {{ $maintenance['recommended_pct'] }}%</strong> ({{ $money($maintenance['recommended_annual'] ?? 0) }}/year) — see below.
                    @else
                        In line with what the inception answers recommend ({{ $maintenance['recommended_pct'] ?? 15 }}%).
                    @endif
                </div>
            </article>
            <article class="card metric">
                <div class="metric-label">Capacity used</div>
                <div class="metric-value">{{ $payback['utilization_pct'] ?? 0 }}%</div>
                <div class="metric-sub">of {{ $hours($payback['capacity_hours_year'] ?? 0) }} billable per year</div>
                <div class="metric-hint">Share of one person's year this project takes.
                    @if (! empty($payback['over_capacity']))<strong>Over 100%: it does not fit in a single year of one person's time.</strong>@endif
                </div>
            </article>
            <article class="card metric">
                <div class="metric-label">Annual net at capacity</div>
                <div class="metric-value">{{ $money($payback['annual_net_at_capacity'] ?? 0) }}</div>
                <div class="metric-sub">{{ $payback['projects_per_year'] ?? 0 }} projects/year invoiced {{ $money($payback['annual_gross_at_capacity'] ?? 0) }}</div>
                <div class="metric-hint">Your yearly take-home if every slot were filled with work priced like this — the ceiling of the current pricing.</div>
            </article>
        </div>

        @if ($maintenance)
            <section class="card panel">
                <h4>What the retainer is priced on</h4>
                <p class="hint">Maintenance is not a flat percentage: it follows what inception already decided — how far the product goes,
                    who runs the server, how it ships, how it is tested, and how fast support has to answer.
                    Those answers add up to <strong>{{ $maintenance['recommended_pct'] }}%</strong> of the build,
                    {{ $money($maintenance['recommended_annual'] ?? 0) }} a year.</p>
                <div class="grid-2">
                    <div>
                        <table class="table">
                            <thead>
                                <tr><th>What inception decided</th><th class="num">Effect</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>Starting point<small>A maintained Laravel application, nothing unusual about it.</small></th>
                                    <td class="num">12%</td>
                                </tr>
                                @foreach ($maintenance['drivers'] as $driver)
                                    <tr>
                                        <th>{{ $driver['reason'] }}</th>
                                        <td class="num">{{ $driver['delta'] > 0 ? '+' : '' }}{{ $driver['delta'] == 0.0 ? '—' : $driver['delta'].'%' }}</td>
                                    </tr>
                                @endforeach
                                <tr class="is-total">
                                    <th>Recommended retainer</th>
                                    <td class="num"><strong>{{ $maintenance['recommended_pct'] }}%</strong></td>
                                </tr>
                                <tr>
                                    <th>In this quote<small>{{ $maintenance['follows_inception'] ? 'Matches the recommendation.' : 'You chose a different figure — the quote uses yours.' }}</small></th>
                                    <td class="num">{{ $maintenance['configured_pct'] }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <h4>What the client gets for it</h4>
                        <ul class="notes">
                            @foreach ($maintenance['covers'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        @if (! empty($maintenance['gaps']))
                            <h4 style="margin-top:16px">Still unanswered</h4>
                            <ul class="notes">
                                @foreach ($maintenance['gaps'] as $gap)
                                    <li>{{ $gap }}</li>
                                @endforeach
                            </ul>
                            <p class="hint" style="margin:10px 0 0">Run <code>/larapilot-inception</code> (or <code>/larapilot-settings</code>) to answer these and the retainer stops being a guess.</p>
                        @endif
                    </div>
                </div>
            </section>
        @endif
    </section>

    {{-- 4 · PACKAGING & BUSINESS PLAN --}}
    @if ($isSaas && $packaging && $plan)
        <section class="eco-section">
            <div class="eco-section-head">
                <span class="eco-section-num">4</span>
                <div>
                    <h3>Packaging</h3>
                    <p>Three plans around the {{ $money2($packaging['anchor_price'] ?? 0) }} list price.
                        {{ ($packaging['source'] ?? 'derived') === 'research'
                            ? 'Prices and features come from the market research.'
                            : 'Prices are derived from the list price and the plans are cut out of the backlog — research the market and they become real numbers.' }}
                        Pick the price line in the console and everything below recomputes on it.</p>
                </div>
            </div>

            <div class="tiers">
                @foreach ($packaging['tiers'] as $tier)
                    <article @class(['card', 'tier', 'is-selected' => ! empty($tier['selected'])])>
                        <div class="tier-head">
                            <span class="tier-name">{{ $tier['name'] }}</span>
                            @if (! empty($tier['selected']))<span class="chip current">forecast runs on this</span>@endif
                        </div>
                        <div class="tier-price">{{ $money2($tier['price_monthly']) }}<small>/month</small></div>
                        <div class="tier-annual">{{ $money($tier['price_annual']) }}/year · {{ $tier['annual_discount_pct'] }}% off for paying yearly</div>
                        <p class="tier-note">{{ $tier['note'] }}</p>
                        <table class="table">
                            <tbody>
                                <tr><th>Contribution / customer<small>After payment fees and support.</small></th><td class="num">{{ $money2($tier['contribution_per_customer']) }}</td></tr>
                                <tr><th>Break-even customers</th><td class="num">{{ $tier['break_even_customers'] }}</td></tr>
                                <tr><th>Customers to repay the build in 12 months</th><td class="num">{{ $tier['customers_to_recover_12m'] }}</td></tr>
                                <tr><th>Assumed share of customers</th><td class="num">{{ $tier['share_pct'] }}%</td></tr>
                            </tbody>
                        </table>
                        @if (! empty($tier['features']))
                            <div class="tier-features">
                                <span>{{ $loop->first ? 'Includes' : 'Everything above, plus' }} ({{ $tier['includes'] ?? count($tier['features']) }} of the backlog)</span>
                                <ul>
                                    @foreach (array_slice($tier['features'], 0, 8) as $feature)
                                        <li>{{ $feature }}</li>
                                    @endforeach
                                    @if (count($tier['features']) > 8)
                                        <li class="more">+{{ count($tier['features']) - 8 }} more</li>
                                    @endif
                                </ul>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            <section class="card panel">
                <h4>If you sell the mix</h4>
                <p class="hint">At {{ collect($packaging['tiers'])->map(fn ($t) => $t['name'].' '.$t['share_pct'].'%')->implode(' · ') }} the average customer pays
                    <strong>{{ $money2($packaging['blended_arpu'] ?? 0) }}</strong>/month, so 100 customers are {{ $money($packaging['blended_arr_per_100'] ?? 0) }} of ARR.
                    The forecast below deliberately uses a single price line instead — one plan at a time is a decision you can actually check.</p>
            </section>
        </section>

        <section class="eco-section">
            <div class="eco-section-head">
                <span class="eco-section-num">5</span>
                <div>
                    <h3>Business plan</h3>
                    <p>The same product on the {{ $plan['tier_name'] ?? '' }} line at {{ $money2($plan['price_monthly'] ?? 0) }}/month, read three ways.
                        Each line is a full 36-month projection: {{ $money2($plan['contribution_per_customer'] ?? 0) }} kept per customer per month against
                        {{ $money($plan['fixed_monthly'] ?? 0) }} of fixed monthly cost, starting {{ $money($plan['investment'] ?? 0) }} in the red.</p>
                </div>
            </div>

            <div class="plan-lines">
                @foreach ($plan['lines'] as $line)
                    <article @class(['card', 'plan-line', 'is-selected' => ! empty($line['selected'])])>
                        <div class="tier-head">
                            <span class="tier-name">{{ $line['label'] }}</span>
                            @if (! empty($line['selected']))<span class="chip current">selected</span>@endif
                        </div>
                        <div class="tier-price">{{ $money($line['arr_m36']) }}<small>ARR at month 36</small></div>
                        <table class="table">
                            <tbody>
                                <tr><th>Customers<small>month 12 / 24 / 36</small></th><td class="num">{{ $line['customers_m12'] }} / {{ $line['customers_m24'] }} / {{ $line['customers_m36'] }}</td></tr>
                                <tr><th>Growth · churn<small>per month</small></th><td class="num">{{ $line['growth_monthly_pct'] }}% · {{ $line['churn_monthly_pct'] }}%</td></tr>
                                <tr><th>Leads needed<small>at {{ $line['conversion_pct'] }}% conversion</small></th><td class="num">{{ number_format($line['leads_needed'], 0, '.', ',') }}</td></tr>
                                <tr><th>Profitable from<small>first month the product pays its own bills</small></th><td class="num">{{ $line['profitable_month'] ? 'month '.$line['profitable_month'] : 'never in 36' }}</td></tr>
                                <tr><th>Build repaid<small>cumulative profit after tax covers the build</small></th><td class="num">{{ $line['recovered_month'] ? 'month '.$line['recovered_month'] : 'never in 36' }}</td></tr>
                                <tr class="is-total"><th>Cash at month 36</th><td class="num"><strong>{{ $money($line['cumulative_m36']) }}</strong></td></tr>
                            </tbody>
                        </table>
                        @if (! empty($line['shrinking']))
                            <p class="tier-note"><strong>Churn outruns growth here</strong> ({{ $line['churn_monthly_pct'] }}% lost against {{ $line['growth_monthly_pct'] }}% won): the customer base shrinks every month and never reaches {{ $line['target_customers'] }}.</p>
                        @endif
                        @if (! empty($line['note']))<p class="tier-note">{{ $line['note'] }}</p>@endif
                    </article>
                @endforeach
            </div>

            @if ($forecast !== [])
                <section class="card panel">
                    <h4>36 months on the {{ ucfirst((string) ($plan['selected'] ?? '')) }} line</h4>
                    <p class="hint">Each bar is that month's ARR. Bars turn green once cumulative profit after tax has repaid the {{ $money($plan['investment'] ?? 0) }} build.</p>
                    <div class="forecast">
                        @foreach ($forecast as $row)
                            <div class="forecast-bar {{ ! empty($row['recovered']) ? 'is-recovered' : '' }}"
                                style="height: {{ max(4, ((float) ($row['arr'] ?? 0) / $maxArr) * 140) }}px"
                                title="M{{ $row['month'] }} · {{ $row['customers'] }} customers · ARR {{ $money($row['arr']) }} · cumulative {{ $money($row['cumulative']) }}"></div>
                        @endforeach
                    </div>
                    <table class="table" style="margin-top:16px">
                        <thead>
                            <tr>
                                <th>Month</th><th class="num">Customers</th><th class="num">MRR</th><th class="num">ARR</th><th class="num">Net / month</th><th class="num">Cumulative</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (collect($forecast)->whereIn('month', [3, 6, 12, 18, 24, 36]) as $row)
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
                </section>
            @endif

            @if (! empty($plan['notes']))
                <p class="disclaimer">@foreach ($plan['notes'] as $note){{ $note }} @endforeach</p>
            @endif

            @if ($saas && ! empty($saas['server_notes']))
                <section class="card panel">
                    <h4>Running costs to expect</h4>
                    <ul class="notes">
                        @foreach ($saas['server_notes'] as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                    <p class="hint" style="margin:12px 0 0">LTV {{ $money($saas['ltv']) }} · CAC {{ $money($saas['cac']) }} · LTV:CAC {{ $saas['ltv_cac'] ?? '—' }}× — aim above 3×.
                        Gross margin {{ $saas['gross_margin_pct'] }}% per subscription after fees and support.</p>
                </section>
            @endif
        </section>
    @endif

    {{-- MARKET --}}
    <section class="eco-section">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ $isSaas && $packaging ? 6 : 4 }}</span>
            <div>
                <h3>The market</h3>
                <p>{{ ! empty($market['available'])
                    ? 'Researched by Jennifer and Benjamin during /larapilot-economics and stored in the project.'
                    : 'Nothing researched yet — prices below are your own assumptions, not a market position.' }}</p>
            </div>
        </div>

        @if (empty($market['available']))
            <section class="card panel">
                <h4>No competitor data</h4>
                <p class="hint" style="margin:0">{{ $market['hint'] ?? '' }}</p>
            </section>
        @else
            @if (! empty($market['stale']))
                <div class="banner warn">
                    <strong>The research predates the current backlog</strong>
                    <code>{{ $market['path'] }}</code> was written {{ $market['researched_at'] }}. Re-run <code>/larapilot-economics</code> to refresh competitors and demand.
                </div>
            @endif

            <div class="grid-2">
                <section class="card panel">
                    <h4>Competitors</h4>
                    <p class="hint">
                        {{ $market['sector'] ?? 'Sector not stated' }}{{ ! empty($market['segment']) ? ' · '.$market['segment'] : '' }}.
                        @if (($market['trend']['direction'] ?? null) !== null)
                            Prices are trending <strong>{{ $market['trend']['direction'] }}</strong>
                            ({{ $market['trend']['up'] }} up · {{ $market['trend']['flat'] }} flat · {{ $market['trend']['down'] }} down@php echo ($market['trend']['average_change_pct'] ?? null) !== null ? ', average '.e($market['trend']['average_change_pct']).'%' : ''; @endphp).
                        @endif
                    </p>
                    <table class="table">
                        <thead>
                            <tr><th>Product</th><th>Plan</th><th class="num">Price / month</th><th class="num">Trend</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($market['competitors'] as $competitor)
                                <tr>
                                    <th>
                                        @if (! empty($competitor['url']))
                                            <a href="{{ $competitor['url'] }}" rel="noreferrer noopener" target="_blank">{{ $competitor['name'] }}</a>
                                        @else
                                            {{ $competitor['name'] }}
                                        @endif
                                        @if (! empty($competitor['notes']))<small>{{ $competitor['notes'] }}</small>@endif
                                    </th>
                                    <td>{{ $competitor['plan'] ?? '—' }}</td>
                                    <td class="num">{{ ($competitor['price_monthly'] ?? null) !== null ? ($competitor['currency'] ?? $currency).' '.number_format((float) $competitor['price_monthly'], 0, '.', ',') : '—' }}</td>
                                    <td class="num">
                                        @if (($competitor['trend'] ?? null) === 'up')
                                            <span class="trend up">▲ {{ $competitor['change_pct'] !== null ? $competitor['change_pct'].'%' : 'up' }}</span>
                                        @elseif (($competitor['trend'] ?? null) === 'down')
                                            <span class="trend down">▼ {{ $competitor['change_pct'] !== null ? $competitor['change_pct'].'%' : 'down' }}</span>
                                        @elseif (($competitor['trend'] ?? null) === 'flat')
                                            <span class="trend">— flat</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                <section class="card panel">
                    <h4>Where your price sits</h4>
                    @php $position = $packaging['positioning'] ?? null; @endphp
                    @if ($position)
                        <p class="hint">Your {{ $plan['tier_name'] ?? 'selected' }} line at {{ $money2($plan['price_monthly'] ?? ($packaging['anchor_price'] ?? 0)) }} against {{ $position['competitors'] }} researched prices.</p>
                        <table class="table">
                            <tbody>
                                <tr><th>Cheaper than you</th><td class="num">{{ $position['cheaper_than_us'] }}</td></tr>
                                <tr><th>More expensive than you</th><td class="num">{{ $position['pricier_than_us'] }}</td></tr>
                                <tr><th>Market range</th><td class="num">{{ $money($position['min']) }} – {{ $money($position['max']) }}</td></tr>
                                <tr><th>Median</th><td class="num">{{ $money($position['median']) }}</td></tr>
                                <tr class="is-total"><th>You vs median</th><td class="num"><strong>{{ ($position['delta_vs_median_pct'] ?? 0) > 0 ? '+' : '' }}{{ $position['delta_vs_median_pct'] ?? 0 }}%</strong></td></tr>
                            </tbody>
                        </table>
                    @else
                        <p class="hint">No competitor prices in the research, so there is nothing to position against.</p>
                    @endif
                    @if (! empty($market['summary']))<p class="hint" style="margin-top:14px">{{ $market['summary'] }}</p>@endif
                    @if (! empty($market['risks']))
                        <h4 style="margin-top:16px">Risks</h4>
                        <ul class="notes">
                            @foreach ($market['risks'] as $risk)<li>{{ $risk }}</li>@endforeach
                        </ul>
                    @endif
                    @if (! empty($market['sources']))
                        <p class="hint" style="margin-top:14px;margin-bottom:0">Sources: {{ implode(' · ', $market['sources']) }}</p>
                    @endif
                </section>
            </div>
        @endif
    </section>

    {{-- INPUTS --}}
    <details class="card panel eco-details">
        <summary>Inputs, files, and the words on this page</summary>
        <div class="grid-2" style="margin-top:14px">
            <div>
                <h4>Profile in use</h4>
                <div class="chips">
                    <span class="chip current">{{ $account }}</span>
                    <span class="chip current">{{ $country['code'] ?? '' }}</span>
                    <span class="chip current">{{ $regime['id'] ?? '' }}</span>
                    <span class="chip">{{ $money($profile['hourly_rate'] ?? 0) }}/h</span>
                    <span class="chip">margin {{ $profile['margin_target_pct'] ?? 0 }}%</span>
                    <span class="chip">discount {{ $profile['discount_pct'] ?? 0 }}%</span>
                    <span class="chip">team {{ $profile['team_size'] ?? 1 }}</span>
                    <span class="chip">overhead {{ $money($profile['overhead_monthly'] ?? 0) }}/mo</span>
                </div>
                <p class="hint">
                    Profile: <code>{{ $path ?? '.larapilot/economics.yaml' }}</code><br>
                    Snapshot: <code>{{ $snapshot_path ?? '.larapilot/economics.snapshot.yaml' }}</code><br>
                    Market: <code>{{ $market['path'] ?? '.larapilot/economics.market.yaml' }}</code><br>
                    Client quote:
                    @if (($quoteDoc['source'] ?? 'template') === 'document')
                        <code>{{ $quoteDoc['path'] }}</code>{{ ! empty($quoteDoc['stale']) ? ' · outdated' : '' }}
                    @else
                        built-in template ({{ $quoteDoc['lang'] ?? 'en' }})
                    @endif
                </p>
                @if (! empty($simulation['active']))
                    <p class="hint">While a simulation is on screen the download serves the built-in template with these numbers — a written client document is never rewritten around a dropdown.</p>
                @endif
            </div>
            <div>
                <h4>Words used here</h4>
                <dl class="defs">
                    <div><dt>MRR / ARR</dt><dd>Revenue invoiced every month, and the same figure over a year (× 12). Revenue, not profit.</dd></div>
                    <div><dt>Churn</dt><dd>Share of paying customers cancelling each month. At {{ $saas['churn_monthly_pct'] ?? 4 }}% the average customer stays about {{ ((float) ($saas['churn_monthly_pct'] ?? 4)) > 0 ? (int) round(100 / (float) ($saas['churn_monthly_pct'] ?? 4)) : 0 }} months.</dd></div>
                    <div><dt>Contribution per customer</dt><dd>Price minus payment fees and the support that customer costs. What actually pays the fixed bills.</dd></div>
                    <div><dt>LTV : CAC</dt><dd>Revenue one customer brings before cancelling against what it costs to win one. Above 3× is healthy.</dd></div>
                    <div><dt>Break-even customers</dt><dd>Paying accounts needed before the monthly bills are covered. It does not repay the build yet.</dd></div>
                    <div><dt>Person-months</dt><dd>One person working one month. Team size divides the calendar, not the work — which is why the price does not move with it.</dd></div>
                </dl>
            </div>
        </div>
    </details>

    <p class="disclaimer">{{ $disclaimer ?? '' }} Hours follow the backlog and plans; tax follows the FY-{{ $fiscal_year ?? 2026 }} statutory catalogue.
        Talk to a commercialista / accountant before you commit to anything here.</p>
@endif
