@php
    use Larapilot\Support\EconomicsStrings;

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

    // The page speaks the PRD language, like the downloadable quote. Only the
    // pricing console below stays in English — those are operator controls.
    $lang = (string) ($language ?? 'en');
    $t = static fn (string $key, array $replace = []): string => EconomicsStrings::line($lang, $key, $replace);
    $th = static fn (string $key, array $replace = []): string => EconomicsStrings::line(
        $lang,
        $key,
        array_map(static fn (mixed $value): string => e((string) $value), $replace)
    );

    $currency = $country['currency'] ?? ($profile['currency'] ?? 'EUR');
    $money = static fn (mixed $amount): string => $currency.' '.number_format((float) $amount, 0, '.', ',');
    $money2 = static fn (mixed $amount): string => $currency.' '.number_format((float) $amount, 2, '.', ',');
    $hours = static fn (mixed $value): string => rtrim(rtrim(number_format((float) $value, 1, '.', ','), '0'), '.').'h';

    $overrides = is_array($simulation['overrides'] ?? null) ? $simulation['overrides'] : [];
    $query = http_build_query($overrides);
    $link = static fn (string $route): string => route($route).($query !== '' ? '?'.$query : '');

    $model = (string) ($product['model'] ?? 'fixed');
    $isSaas = $model === 'saas';
    $yearKeep = $isSaas && $saas ? (float) ($saas['contribution_per_customer'] ?? 0) * 12 : 0.0;
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
    $recoveredMonth = is_array($selectedLine) ? ($selectedLine['recovered_month'] ?? null) : null;
    $scenarioName = (string) (is_array($selectedLine) ? ($selectedLine['label'] ?? '') : ($saas['scenario_label'] ?? ''));
    $forecast = is_array($selectedLine['forecast'] ?? null) ? $selectedLine['forecast'] : [];
    $maxArr = 1.0;
    foreach ($forecast as $row) {
        $maxArr = max($maxArr, (float) ($row['arr'] ?? 0));
    }
    $barMax = max((float) ($quote['list_price'] ?? 0), (float) ($quote['client_total'] ?? 0), 1);
@endphp

@if (! $enabled)
    <section class="card empty-card">
        <h2>{{ $t('off.title') }}</h2>
        <p>{!! $th('off.body') !!}</p>
        <p><code>php artisan larapilot:settings-set --account=FREELANCE</code><br>
        <code>php artisan larapilot:settings-set --account=COMPANY</code></p>
        <p>{!! $th('off.calibrate') !!}</p>
    </section>
@else
    <div class="eco-top">
        <div>
            <h2>{{ $t('head.title') }}</h2>
            <p class="sub">{{ $t('head.sub') }}</p>
            <div class="chips" style="margin:10px 0 0">
                @if (($quoteDoc['source'] ?? 'template') === 'document')
                    <span class="chip {{ ! empty($quoteDoc['stale']) ? 'stale' : 'current' }}">
                        {{ $t('head.chip.written') }}{{ ! empty($quoteDoc['lang']) ? ' ('.$quoteDoc['lang'].')' : '' }}{{ ! empty($quoteDoc['stale']) ? ' · '.$t('head.chip.outdated') : '' }}
                    </span>
                @else
                    <span class="chip">{{ $t('head.chip.template', ['lang' => $quoteDoc['lang'] ?? 'en']) }}</span>
                @endif
                <span class="chip">{{ $regime['label'] ?? '' }} · {{ $country['name'] ?? '' }}</span>
                <span class="chip">{{ $t('head.chip.rates', ['year' => $fiscal_year ?? 2026]) }}</span>
            </div>
        </div>
        <div class="eco-actions">
            <a class="btn" href="{{ $link('larapilot.dashboard.economics.quote') }}">{{ $t('head.action.quote') }}</a>
            <a class="btn ghost" href="{{ $link('larapilot.dashboard.economics.report') }}">{{ $t('head.action.report') }}</a>
        </div>
    </div>

    {{-- THE PRICING CONSOLE — operator controls, deliberately left in English --}}
    <form id="eco-controls" class="card eco-console" method="get" action="{{ route('larapilot.dashboard.economics') }}">
        <p class="eco-console-hint">{{ $t('console.hint') }}</p>
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
                @if ($group === 'account')
                <details class="eco-console-group eco-fold">
                    <summary>{{ $title }} — country, tax, VAT</summary>
                    <div class="eco-fields">
                @else
                <div class="eco-console-group">
                    <span class="eco-console-title">{{ $title }}</span>
                    <div class="eco-fields">
                @endif
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
                @if ($group === 'account')
                </details>
                @else
                </div>
                @endif
            @endif
        @endforeach
    </form>

    @if ($productControl)
        {{-- The four ways a project makes money, and what each one turns on. --}}
        <details class="card panel eco-details eco-models">
            <summary>{{ $t('models.summary') }}</summary>
            <p class="hint" style="margin:12px 0 0">{{ $t('models.intro') }}</p>
            <dl class="defs" style="margin-top:12px">
                @foreach ($productControl['options'] as $option)
                    <div>
                        <dt>{{ $option['label'] }}
                            @if ((string) $option['value'] === (string) $productControl['value'])
                                <span class="chip current">{{ $t('models.current') }}</span>
                            @endif
                        </dt>
                        <dd>{{ $t('models.'.$option['value']) }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="hint" style="margin:14px 0 0">{{ $t('models.footnote') }}</p>
        </details>
    @endif

    @if (! empty($simulation['active']))
        <div class="banner">
            <strong>{{ $t('banner.simulation.title') }}</strong>
            {!! $th('banner.simulation.body') !!}
            <pre class="eco-command" data-eco-command>{{ $simulation['command'] }}</pre>
            <button class="btn ghost small" type="button" data-eco-copy>Copy command</button>
        </div>
    @endif

    @if (($quoteDoc['source'] ?? 'template') === 'document' && ! empty($quoteDoc['stale']))
        <div class="banner warn">
            <strong>{{ $t('banner.stale.title') }}</strong>
            {!! $th('banner.stale.body', ['path' => $quoteDoc['path'], 'date' => $quoteDoc['generated_at']]) !!}
        </div>
    @endif

    @if (empty($profile['configured']))
        <div class="banner warn">
            <strong>{{ $t('banner.defaults.title') }}</strong>
            {!! $th('banner.defaults.body', ['country' => $country['name'] ?? $t('banner.defaults.catalogue')]) !!}
        </div>
    @endif

    @if ($warnings !== [] || ! empty($quote['below_cost']))
        <div class="banner warn">
            <strong>{{ $t('banner.warnings.title') }}</strong>
            <ul>
                @if (! empty($quote['below_cost']))
                    <li>{{ $t('banner.below_cost', ['gross' => $money($quote['gross'] ?? 0), 'direct' => $money($quote['direct'] ?? 0)]) }}</li>
                @endif
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card eco-answer">
        <p>
            @if ($isSaas && $saas)
                {{ $t('answer.lead.saas', [
                    'price' => $money2($saas['price_monthly'] ?? 0),
                    'keep' => $money2($saas['contribution_per_customer'] ?? 0),
                    'bills' => $saas['break_even_customers'] ?? 0,
                    'recover' => $saas['customers_to_recover_12m'] ?? 0,
                    'build' => $money($quote['gross'] ?? 0),
                ]) }}
            @elseif ($model === 'ecommerce')
                {{ $t('answer.lead.ecommerce', [
                    'price' => $money($quote['gross'] ?? 0),
                    'net' => $money($quote['net_to_owner'] ?? 0),
                    'orders' => number_format((float) ($payback['orders_per_month_to_recover_12m'] ?? 0), 0, '.', ','),
                ]) }}
            @elseif ($model === 'package')
                {{ $t('answer.lead.package', [
                    'price' => $money($quote['gross'] ?? 0),
                    'net' => $money($quote['net_to_owner'] ?? 0),
                    'licences' => number_format((float) ($payback['licenses_to_recover'] ?? 0), 0, '.', ','),
                    'each' => $money($payback['suggested_license_price'] ?? 0),
                ]) }}
            @else
                {{ $t('answer.lead.fixed', [
                    'price' => $money($quote['gross'] ?? 0),
                    'net' => $money($quote['net_to_owner'] ?? 0),
                    'months' => $effort['calendar_months'] ?? 0,
                ]) }}
            @endif
        </p>
    </section>

    @if ($isSaas && $saas)
        <section class="eco-section">
            <div class="eco-section-head">
                <span class="eco-section-num">1</span>
                <div>
                    <h3>{{ $t('saas.how.title') }}</h3>
                    <p class="eco-how">{{ $t('saas.how.body') }}</p>
                </div>
            </div>
            <div class="eco-steps">
                <article class="card eco-step">
                    <span class="eco-step-k">1 · {{ $t('saas.step.price') }}</span>
                    <div class="metric-value">{{ $money2($saas['price_monthly'] ?? 0) }}</div>
                    <p>{{ $t('saas.step.price.hint') }}</p>
                </article>
                <article class="card eco-step">
                    <span class="eco-step-k">2 · {{ $t('saas.step.keep') }}</span>
                    <div class="metric-value">{{ $money2($saas['contribution_per_customer'] ?? 0) }}</div>
                    <p>{{ $t('saas.step.keep.hint', ['price' => $money2($saas['price_monthly'] ?? 0)]) }}</p>
                </article>
                <article class="card eco-step">
                    <span class="eco-step-k">3 · {{ $t('saas.step.bills') }}</span>
                    <div class="metric-value">{{ $saas['break_even_customers'] ?? 0 }}</div>
                    <p>{{ $t('saas.step.bills.hint', [
                        'fixed' => $money($saas['fixed_monthly'] ?? 0),
                        'keep' => $money2($saas['contribution_per_customer'] ?? 0),
                    ]) }}</p>
                </article>
                <article class="card eco-step">
                    <span class="eco-step-k">4 · {{ $t('saas.step.build') }}</span>
                    <div class="metric-value">{{ $saas['customers_to_recover_12m'] ?? 0 }}</div>
                    <p>{{ $t('saas.step.build.hint', [
                        'build' => $money($quote['gross'] ?? 0),
                        'year' => $money($yearKeep),
                    ]) }}</p>
                </article>
            </div>
            <article class="card eco-step">
                <span class="eco-step-k">{{ $t('saas.step.when') }}</span>
                <div class="metric-value">{{ $recoveredMonth ? $t('plan.month', ['month' => $recoveredMonth]) : $t('plan.never') }}</div>
                <p>{{ $recoveredMonth
                    ? $t('saas.step.when.month', ['month' => $recoveredMonth, 'scenario' => $scenarioName])
                    : $t('saas.step.when.never', ['scenario' => $scenarioName]) }}</p>
            </article>
        </section>
    @endif

    {{-- BUILD COST --}}
    <section class="eco-section">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ $isSaas ? 2 : 1 }}</span>
            <div>
                <h3>{{ $t('s1.title') }}</h3>
                <p>{{ $isSaas ? $t('s1.build.note') : $t('s1.sub.fixed') }}</p>
            </div>
        </div>

        <div class="metrics">
            <article class="card metric">
                <div class="metric-label">{{ $t('m.price.label') }}</div>
                <div class="metric-value">{{ $money($quote['gross'] ?? 0) }}</div>
                <div class="metric-sub">
                    @if ((float) ($quote['discount_pct'] ?? 0) > 0)
                        {{ $t('m.price.sub.discount', ['list' => $money($quote['list_price'] ?? 0), 'pct' => $quote['discount_pct']]) }}
                    @else
                        {{ $t('m.price.sub.exvat', ['hours' => $hours($quote['billable_hours'] ?? 0), 'rate' => $money($quote['hourly_rate'] ?? 0)]) }}
                    @endif
                </div>
                <div class="metric-hint">{{ $t('m.price.hint', ['total' => $money($quote['client_total'] ?? 0)]) }}</div>
            </article>
            <article class="card metric">
                <div class="metric-label">{{ $t('m.net.label') }}</div>
                <div class="metric-value">{{ $money($quote['net_to_owner'] ?? 0) }}</div>
                <div class="metric-sub">{{ $t('m.net.sub', ['pct' => $tax['withheld_rate_pct'] ?? 0, 'net' => $money($payback['effective_hourly_net'] ?? 0)]) }}</div>
                <div class="metric-hint">{{ $t('m.net.hint') }}
                    @if (! empty($tax['loss']))<strong>{{ $t('m.net.loss') }}</strong>@endif
                </div>
            </article>
            <article class="card metric">
                <div class="metric-label">{{ $t('m.margin.label') }}</div>
                <div class="metric-value">{{ $money($quote['margin_after_discount'] ?? $quote['margin'] ?? 0) }}</div>
                <div class="metric-sub">{{ $t('m.margin.sub', ['pct' => $quote['margin_after_discount_pct'] ?? $quote['margin_pct'] ?? 0]) }}@if ((float) ($quote['discount'] ?? 0) > 0) · {{ $t('m.margin.sub.discount', ['amount' => $money($quote['discount'])]) }} @endif</div>
                <div class="metric-hint">{{ $t('m.margin.hint') }}</div>
            </article>
            <article class="card metric">
                <div class="metric-label">{{ $t('m.delivery.label') }}</div>
                <div class="metric-value">{{ $t('m.delivery.value', ['months' => $effort['calendar_months'] ?? 0]) }}</div>
                <div class="metric-sub">{{ $t('m.delivery.sub', [
                    'team' => $effort['team_size'] ?? 1,
                    'people' => ((float) ($effort['team_size'] ?? 1)) == 1.0 ? $t('m.delivery.person') : $t('m.delivery.people'),
                    'hours' => $hours($effort['billable_hours'] ?? 0),
                ]) }}</div>
                <div class="metric-hint">{{ $t('m.delivery.hint', [
                    'hpd' => $profile['hours_per_day'] ?? 6,
                    'solo' => ((float) ($effort['solo_months'] ?? 0)) == 1.0
                        ? $t('m.delivery.solo.one')
                        : $t('m.delivery.solo.many', ['months' => $effort['solo_months'] ?? 0]),
                ]) }}</div>
            </article>
        </div>
    </section>

    {{-- HOURS --}}
    <section class="eco-section">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ $isSaas ? 3 : 2 }}</span>
            <div>
                <h3>{{ $t('s2.title') }}</h3>
                <p>{{ $t('s2.lead', ['hours' => $hours($effort['billable_hours'] ?? 0)]) }}</p>
            </div>
        </div>

        <details class="card eco-fold">
            <summary>{{ $t('show.hours') }}</summary>
            <p class="hint">{{ $t('s2.sub', [
                    'source' => $effort['source_label'] ?? 'Backlog',
                    'base' => $hours($effort['base_hours'] ?? 0),
                    'buffer' => (int) round(((float) ($effort['buffer'] ?? 1.15) - 1) * 100),
                    'billable' => $hours($effort['billable_hours'] ?? 0),
                    'hpp' => $effort['hours_per_point'] ?? 4,
                    'calibration' => ($effort['hours_per_point_source'] ?? 'settings') === 'plans'
                        ? $t('s2.calibration.plans')
                        : $t('s2.calibration.settings'),
                ]) }}</p>

        @php
            $byRelease = is_array($effort['by_release'] ?? null) && $effort['by_release'] !== [];
            $groupRows = $byRelease ? $effort['by_release'] : (is_array($effort['by_epic'] ?? null) ? $effort['by_epic'] : []);
            $groupKey = $byRelease ? 'release' : 'epic';
        @endphp

        @if ($groupRows !== [])
            <section class="card panel">
                <h4>{{ $t('s2.group.title.'.$groupKey) }}</h4>
                <p class="hint">{{ $t('s2.group.hint') }}</p>
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ $t('s2.group.'.$groupKey) }}</th>
                            <th class="num">{{ $t('s2.th.stories') }}</th>
                            <th class="num">{{ $t('s2.th.points') }}</th>
                            <th class="num">{{ $t('s2.th.hours') }}</th>
                            <th class="num">{{ $t('s2.th.labour') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupRows as $row)
                            <tr>
                                <th>{{ $row['label'] }}<small>{{ $t('s2.row.delivered', ['done' => $row['done'], 'total' => $row['specs']]) }}</small></th>
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
            <h4>{{ $t('s2.story.title') }}</h4>
            @if ($breakdown === [])
                <p class="hint" style="margin:0">{!! $th('s2.story.empty', [
                    'kind' => $inception['project_kind'] ?? $t('s2.story.kind_unset'),
                    'target' => $inception['delivery_target'] ?? $t('s2.story.target_unset'),
                    'type' => $inception['website_type'] ?? $t('s2.story.type_unset'),
                ]) !!}</p>
            @else
                <p class="hint">{{ $t('s2.story.hint') }}</p>
                <div class="scroll-y">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ $t('s2.th.story') }}</th>
                                <th>{{ $t('s2.th.status') }}</th>
                                <th>{{ $t('s2.th.from') }}</th>
                                <th class="num">{{ $t('s2.th.points') }}</th>
                                <th class="num">{{ $t('s2.th.hours') }}</th>
                                <th class="num">{{ $t('s2.th.labour') }}</th>
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
                                            <span class="tag plan">{{ $t('s2.tag.plan') }}</span>
                                        @elseif (($row['from'] ?? '') === 'points')
                                            <span class="tag points">{{ $t('s2.tag.points') }}</span>
                                        @else
                                            <span class="tag unsized">{{ $t('s2.tag.unsized') }}</span>
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
                                <th colspan="4">{{ $t('s2.total') }}</th>
                                <td class="num"><strong>{{ $hours($effort['billable_hours'] ?? 0) }}</strong></td>
                                <td class="num"><strong>{{ $money($quote['labor'] ?? 0) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            </section>
        </details>
    </section>

    {{-- PRICE AND TAX --}}
    <section class="eco-section">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ $isSaas ? 4 : 3 }}</span>
            <div>
                <h3>{{ $t('s3.title') }}</h3>
                <p>{{ $t('s3.sub', [
                    'country' => $country['name'] ?? '',
                    'year' => $fiscal_year ?? 2026,
                    'regime' => $regime['label'] ?? $t('s3.regime.fallback'),
                    'account' => strtolower($account),
                ]) }}</p>
            </div>
        </div>

        <div class="grid-2">
            <section class="card panel">
                <h4>{{ $t('s3.build.title') }}</h4>
                <p class="hint">{{ $t('s3.build.hint') }}</p>
                <div class="bars">
                    @foreach ([
                        ['labour', $t('s3.bar.labour.explain', ['hours' => $hours($quote['billable_hours'] ?? 0), 'rate' => $money($quote['hourly_rate'] ?? 0)]), $quote['labor'] ?? 0, ''],
                        ['overhead', $t('s3.bar.overhead.explain', ['months' => $effort['person_months'] ?? 0]), $quote['overhead'] ?? 0, ''],
                        ['margin', $t('s3.bar.margin.explain', ['pct' => $quote['margin_pct'] ?? 0]), $quote['margin'] ?? 0, 'is-margin'],
                        ['discount', $t('s3.bar.discount.explain'), -1 * (float) ($quote['discount'] ?? 0), 'is-tax'],
                        ['vat', $t('s3.bar.vat.explain'), $quote['vat'] ?? 0, 'is-tax'],
                        ['net', $t('s3.bar.net.explain'), $quote['net_to_owner'] ?? 0, 'is-net'],
                    ] as [$key, $explain, $value, $mod])
                        @if ($key !== 'discount' || (float) $value !== 0.0)
                            <div class="bar-row">
                                <span class="bar-label">{{ $t('s3.bar.'.$key) }}<small>{{ $explain }}</small></span>
                                <div class="bar-track"><div class="bar-fill {{ $mod }}" style="width: {{ min(100, (abs((float) $value) / $barMax) * 100) }}%"></div></div>
                                <span class="num">{{ $money($value) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        </div>

        <details class="card eco-fold">
            <summary>{{ $t('show.tax') }}</summary>
            <section class="card panel">
                <h4>{{ $t('s3.tax.title') }}</h4>
                <p class="hint">{{ $regime['notes'] ?? $t('s3.tax.fallback') }}</p>
                <table class="table">
                    <tbody>
                        <tr>
                            <th>{{ $t('s3.tax.taxable') }}<small>{{ $t('s3.tax.taxable.small') }}</small></th>
                            <td class="num">{{ $money($tax['taxable'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th>{{ $t('s3.tax.income') }}</th>
                            <td class="num">{{ $money($tax['income_tax'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th>{{ $t('s3.tax.social') }}<small>{{ $t('s3.tax.social.small') }}</small></th>
                            <td class="num">{{ $money($tax['social'] ?? 0) }}</td>
                        </tr>
                        @if ((float) ($tax['local_tax'] ?? 0) > 0)
                            <tr><th>{{ $t('s3.tax.local') }}</th><td class="num">{{ $money($tax['local_tax']) }}</td></tr>
                        @endif
                        @if ((float) ($tax['dividend_tax'] ?? 0) > 0)
                            <tr><th>{{ $t('s3.tax.dividend') }}</th><td class="num">{{ $money($tax['dividend_tax']) }}</td></tr>
                        @endif
                        @if ((float) ($tax['legal_reserve'] ?? 0) > 0)
                            <tr><th>{{ $t('s3.tax.reserve') }}<small>{{ $t('s3.tax.reserve.small') }}</small></th><td class="num">{{ $money($tax['legal_reserve']) }}</td></tr>
                        @endif
                        <tr>
                            <th>{{ $t('s3.tax.compliance') }}<small>{{ $t('s3.tax.compliance.small') }}</small></th>
                            <td class="num">{{ $money($tax['compliance'] ?? 0) }}</td>
                        </tr>
                        <tr class="is-total">
                            <th>{{ $t('s3.tax.total') }}</th>
                            <td class="num"><strong>{{ $money($tax['total_withheld'] ?? $tax['total_tax'] ?? 0) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
                @if (! empty($alternate) && ($alternate['applicable'] ?? false))
                    <p class="hint" style="margin:12px 0 0">{{ $t('s3.tax.alternate', [
                        'account' => strtolower((string) $alternate['account']),
                        'regime' => $alternate['regime'],
                        'net' => $money($alternate['net_to_owner']),
                        'pct' => $alternate['effective_rate_pct'],
                    ]) }}</p>
                @endif
            </section>
        </details>

        <div class="metrics">
            <article class="card metric">
                <div class="metric-label">{{ $t('m.maint.label') }}</div>
                <div class="metric-value">{{ $money($quote['maintenance_year'] ?? 0) }}</div>
                <div class="metric-sub">{{ $t('m.maint.sub', ['monthly' => $money($quote['maintenance_monthly'] ?? 0), 'pct' => $profile['maintenance_annual_pct'] ?? 15]) }}</div>
                <div class="metric-hint">
                    @if ($maintenance && ! empty($maintenance['adopted']))
                        {{ $t('m.maint.hint.adopted', ['pct' => $maintenance['recommended_pct']]) }}
                    @elseif ($maintenance && ! $maintenance['follows_inception'])
                        {!! $th('m.maint.hint.differs', ['pct' => $maintenance['recommended_pct'], 'amount' => $money($maintenance['recommended_annual'] ?? 0)]) !!}
                    @else
                        {{ $t('m.maint.hint.inline', ['pct' => $maintenance['recommended_pct'] ?? 15]) }}
                    @endif
                </div>
            </article>
            @if (($payback['orders_per_month_to_recover_12m'] ?? null) !== null)
                <article class="card metric">
                    <div class="metric-label">{{ $t('m.orders.label') }}</div>
                    <div class="metric-value">{{ number_format((float) $payback['orders_per_month_to_recover_12m'], 0, '.', ',') }}</div>
                    <div class="metric-sub">{{ $t('m.orders.sub') }}</div>
                    <div class="metric-hint">{{ $t('m.orders.hint', [
                        'aov' => $money($payback['assumed_aov'] ?? 0),
                        'rate' => $payback['assumed_take_rate_pct'] ?? 0,
                    ]) }}</div>
                </article>
            @endif
            @if (($payback['licenses_to_recover'] ?? null) !== null)
                <article class="card metric">
                    <div class="metric-label">{{ $t('m.licences.label') }}</div>
                    <div class="metric-value">{{ number_format((float) $payback['licenses_to_recover'], 0, '.', ',') }}</div>
                    <div class="metric-sub">{{ $t('m.licences.sub', ['price' => $money($payback['suggested_license_price'] ?? 0)]) }}</div>
                    <div class="metric-hint">{{ ($payback['license_price_source'] ?? '') === 'configured_annual_price'
                        ? $t('m.licences.source.configured')
                        : $t('m.licences.source.heuristic') }}</div>
                </article>
            @endif
        </div>

        <details class="card eco-fold">
            <summary>{{ $t('show.more') }}</summary>
            <div class="metrics" style="margin-top:14px">
            <article class="card metric">
                <div class="metric-label">{{ $t('m.capacity.label') }}</div>
                <div class="metric-value">{{ $payback['utilization_pct'] ?? 0 }}%</div>
                <div class="metric-sub">{{ $t('m.capacity.sub', ['hours' => $hours($payback['capacity_hours_year'] ?? 0)]) }}</div>
                <div class="metric-hint">{{ $t('m.capacity.hint') }}
                    @if (! empty($payback['over_capacity']))<strong>{{ $t('m.capacity.over') }}</strong>@endif
                </div>
            </article>
            <article class="card metric">
                <div class="metric-label">{{ $t('m.annual.label') }}</div>
                <div class="metric-value">{{ $money($payback['annual_net_at_capacity'] ?? 0) }}</div>
                <div class="metric-sub">{{ $t('m.annual.sub', ['projects' => $payback['projects_per_year'] ?? 0, 'gross' => $money($payback['annual_gross_at_capacity'] ?? 0)]) }}</div>
                <div class="metric-hint">{{ $t('m.annual.hint') }}</div>
            </article>
            </div>

        @if ($maintenance)
            <section class="card panel">
                <h4>{{ $t('maint.title') }}</h4>
                <p class="hint">{!! $th('maint.hint', [
                    'pct' => $maintenance['recommended_pct'],
                    'amount' => $money($maintenance['recommended_annual'] ?? 0),
                ]) !!}</p>
                <div class="grid-2">
                    <div>
                        <table class="table">
                            <thead>
                                <tr><th>{{ $t('maint.th.decided') }}</th><th class="num">{{ $t('maint.th.effect') }}</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>{{ $t('maint.start') }}<small>{{ $t('maint.start.small') }}</small></th>
                                    <td class="num">12%</td>
                                </tr>
                                @foreach ($maintenance['drivers'] as $driver)
                                    <tr>
                                        <th>{{ $driver['reason'] }}</th>
                                        <td class="num">{{ $driver['delta'] > 0 ? '+' : '' }}{{ $driver['delta'] == 0.0 ? '—' : $driver['delta'].'%' }}</td>
                                    </tr>
                                @endforeach
                                <tr class="is-total">
                                    <th>{{ $t('maint.recommended') }}</th>
                                    <td class="num"><strong>{{ $maintenance['recommended_pct'] }}%</strong></td>
                                </tr>
                                <tr>
                                    <th>{{ $t('maint.inquote') }}<small>{{ $maintenance['follows_inception'] ? $t('maint.inquote.matches') : $t('maint.inquote.differs') }}</small></th>
                                    <td class="num">{{ $maintenance['configured_pct'] }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <h4>{{ $t('maint.covers.title') }}</h4>
                        <ul class="notes">
                            @foreach ($maintenance['covers'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        @if (! empty($maintenance['gaps']))
                            <h4 style="margin-top:16px">{{ $t('maint.gaps.title') }}</h4>
                            <ul class="notes">
                                @foreach ($maintenance['gaps'] as $gap)
                                    <li>{{ $gap }}</li>
                                @endforeach
                            </ul>
                            <p class="hint" style="margin:10px 0 0">{!! $th('maint.gaps.hint') !!}</p>
                        @endif
                    </div>
                </div>
            </section>
        @endif
        </details>
    </section>

    {{-- PACKAGING & SCENARIOS — the working, after the calculator --}}
    @if ($isSaas && $packaging && $plan)
        <section class="eco-section">
            <div class="eco-section-head">
                <span class="eco-section-num">5</span>
                <div>
                    <h3>{{ $t('pack.title') }}</h3>
                    <p>{{ $t('pack.sub', [
                        'price' => $money2($packaging['anchor_price'] ?? 0),
                        'source' => ($packaging['source'] ?? 'derived') === 'research'
                            ? $t('pack.source.research')
                            : $t('pack.source.derived'),
                    ]) }}</p>
                </div>
            </div>

            <details class="card eco-fold">
                <summary>{{ $t('show.plans') }}</summary>
            <div class="tiers">
                @foreach ($packaging['tiers'] as $tier)
                    <article @class(['card', 'tier', 'is-selected' => ! empty($tier['selected'])])>
                        <div class="tier-head">
                            <span class="tier-name">{{ $tier['name'] }}</span>
                            @if (! empty($tier['selected']))<span class="chip current">{{ $t('pack.tier.selected') }}</span>@endif
                        </div>
                        <div class="tier-price">{{ $money2($tier['price_monthly']) }}<small>{{ $t('pack.tier.month') }}</small></div>
                        <div class="tier-annual">{{ $t('pack.tier.annual', ['annual' => $money($tier['price_annual']), 'pct' => $tier['annual_discount_pct']]) }}</div>
                        <p class="tier-note">{{ $tier['note'] }}</p>
                        <table class="table">
                            <tbody>
                                <tr><th>{{ $t('pack.tier.contribution') }}<small>{{ $t('pack.tier.contribution.small') }}</small></th><td class="num">{{ $money2($tier['contribution_per_customer']) }}</td></tr>
                                <tr><th>{{ $t('pack.tier.breakeven') }}</th><td class="num">{{ $tier['break_even_customers'] }}</td></tr>
                                <tr><th>{{ $t('pack.tier.recover12') }}</th><td class="num">{{ $tier['customers_to_recover_12m'] }}</td></tr>
                                <tr><th>{{ $t('pack.tier.share') }}</th><td class="num">{{ $tier['share_pct'] }}%</td></tr>
                            </tbody>
                        </table>
                        @if (! empty($tier['features']))
                            <div class="tier-features">
                                <span>{{ $loop->first ? $t('pack.tier.includes') : $t('pack.tier.includes_more') }} {{ $t('pack.tier.of_backlog', ['count' => $tier['includes'] ?? count($tier['features'])]) }}</span>
                                <ul>
                                    @foreach (array_slice($tier['features'], 0, 8) as $feature)
                                        <li>{{ $feature }}</li>
                                    @endforeach
                                    @if (count($tier['features']) > 8)
                                        <li class="more">{{ $t('pack.tier.more', ['count' => count($tier['features']) - 8]) }}</li>
                                    @endif
                                </ul>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            <section class="card panel">
                <h4>{{ $t('pack.mix.title') }}</h4>
                <p class="hint">{!! $th('pack.mix.hint', [
                    'mix' => collect($packaging['tiers'])->map(fn ($tier) => $tier['name'].' '.$tier['share_pct'].'%')->implode(' · '),
                    'arpu' => $money2($packaging['blended_arpu'] ?? 0),
                    'arr' => $money($packaging['blended_arr_per_100'] ?? 0),
                ]) !!}</p>
            </section>
            </details>

            <details class="card eco-fold">
                <summary>{{ $t('show.scenarios') }}</summary>
                <p class="hint">{{ $t('plan.sub', [
                        'tier' => $plan['tier_name'] ?? '',
                        'price' => $money2($plan['price_monthly'] ?? 0),
                        'contribution' => $money2($plan['contribution_per_customer'] ?? 0),
                        'fixed' => $money($plan['fixed_monthly'] ?? 0),
                        'investment' => $money($plan['investment'] ?? 0),
                    ]) }}</p>
            <div class="plan-lines">
                @foreach ($plan['lines'] as $line)
                    <article @class(['card', 'plan-line', 'is-selected' => ! empty($line['selected'])])>
                        <div class="tier-head">
                            <span class="tier-name">{{ $line['label'] }}</span>
                            @if (! empty($line['selected']))<span class="chip current">{{ $t('plan.selected') }}</span>@endif
                        </div>
                        <div class="tier-price">{{ $money($line['arr_m36']) }}<small>{{ $t('plan.arr36') }}</small></div>
                        <table class="table">
                            <tbody>
                                <tr><th>{{ $t('plan.customers') }}<small>{{ $t('plan.customers.small') }}</small></th><td class="num">{{ $line['customers_m12'] }} / {{ $line['customers_m24'] }} / {{ $line['customers_m36'] }}</td></tr>
                                <tr><th>{{ $t('plan.growth') }}<small>{{ $t('plan.growth.small') }}</small></th><td class="num">{{ $line['growth_monthly_pct'] }}% · {{ $line['churn_monthly_pct'] }}%</td></tr>
                                <tr><th>{{ $t('plan.leads') }}<small>{{ $t('plan.leads.small', ['pct' => $line['conversion_pct']]) }}</small></th><td class="num">{{ number_format($line['leads_needed'], 0, '.', ',') }}</td></tr>
                                <tr><th>{{ $t('plan.profitable') }}<small>{{ $t('plan.profitable.small') }}</small></th><td class="num">{{ $line['profitable_month'] ? $t('plan.month', ['month' => $line['profitable_month']]) : $t('plan.never') }}</td></tr>
                                <tr><th>{{ $t('plan.repaid') }}<small>{{ $t('plan.repaid.small') }}</small></th><td class="num">{{ $line['recovered_month'] ? $t('plan.month', ['month' => $line['recovered_month']]) : $t('plan.never') }}</td></tr>
                                <tr class="is-total"><th>{{ $t('plan.cash36') }}</th><td class="num"><strong>{{ $money($line['cumulative_m36']) }}</strong></td></tr>
                            </tbody>
                        </table>
                        @if (! empty($line['shrinking']))
                            <p class="tier-note">{!! $th('plan.shrinking', [
                                'churn' => $line['churn_monthly_pct'],
                                'growth' => $line['growth_monthly_pct'],
                                'target' => $line['target_customers'],
                            ]) !!}</p>
                        @endif
                        @if (! empty($line['note']))<p class="tier-note">{{ $line['note'] }}</p>@endif
                    </article>
                @endforeach
            </div>
            </details>

            <details class="card eco-fold">
                <summary>{{ $t('show.months') }}</summary>
            @if ($forecast !== [])
                <section class="card panel">
                    <h4>{{ $t('plan.forecast.title', ['line' => $selectedLine['label'] ?? ucfirst((string) ($plan['selected'] ?? ''))]) }}</h4>
                    <p class="hint">{{ $t('plan.forecast.hint', ['investment' => $money($plan['investment'] ?? 0)]) }}</p>
                    <div class="forecast">
                        @foreach ($forecast as $row)
                            <div class="forecast-bar {{ ! empty($row['recovered']) ? 'is-recovered' : '' }}"
                                style="height: {{ max(4, ((float) ($row['arr'] ?? 0) / $maxArr) * 140) }}px"
                                title="{{ $t('plan.bar.title', [
                                    'month' => $row['month'],
                                    'customers' => $row['customers'],
                                    'arr' => $money($row['arr']),
                                    'cumulative' => $money($row['cumulative']),
                                ]) }}"></div>
                        @endforeach
                    </div>
                    <table class="table" style="margin-top:16px">
                        <thead>
                            <tr>
                                <th>{{ $t('plan.th.month') }}</th><th class="num">{{ $t('plan.th.customers') }}</th><th class="num">{{ $t('plan.th.mrr') }}</th><th class="num">{{ $t('plan.th.arr') }}</th><th class="num">{{ $t('plan.th.net') }}</th><th class="num">{{ $t('plan.th.cumulative') }}</th>
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
                    <h4>{{ $t('plan.running.title') }}</h4>
                    <ul class="notes">
                        @foreach ($saas['server_notes'] as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                    <p class="hint" style="margin:12px 0 0">{{ $t('plan.running.hint', [
                        'ltv' => $money($saas['ltv']),
                        'cac' => $money($saas['cac']),
                        'ratio' => $saas['ltv_cac'] ?? '—',
                        'margin' => $saas['gross_margin_pct'],
                    ]) }}</p>
                </section>
            @endif
            </details>
        </section>
    @endif

    {{-- MARKET --}}
    <section class="eco-section">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ $isSaas ? ($packaging && $plan ? 6 : 5) : 4 }}</span>
            <div>
                <h3>{{ $t('mkt.title') }}</h3>
                <p>{{ ! empty($market['available']) ? $t('mkt.sub.available') : $t('mkt.sub.missing') }}</p>
            </div>
        </div>

        @if (empty($market['available']))
            <section class="card panel">
                <h4>{{ $t('mkt.none.title') }}</h4>
                <p class="hint" style="margin:0">{{ $market['hint'] ?? '' }}</p>
            </section>
        @else
            @if (! empty($market['stale']))
                <div class="banner warn">
                    <strong>{{ $t('mkt.stale.title') }}</strong>
                    {!! $th('mkt.stale.body', ['path' => $market['path'], 'date' => $market['researched_at']]) !!}
                </div>
            @endif

            <details class="card eco-fold">
                <summary>{{ $t('show.market') }}</summary>
            <div class="grid-2">
                <section class="card panel">
                    <h4>{{ $t('mkt.competitors.title') }}</h4>
                    <p class="hint">
                        {{ $market['sector'] ?? $t('mkt.sector.unset') }}{{ ! empty($market['segment']) ? ' · '.$market['segment'] : '' }}.
                        @if (($market['trend']['direction'] ?? null) !== null)
                            {!! $th('mkt.trend', [
                                'direction' => $t('mkt.trend.'.$market['trend']['direction']),
                                'up' => $market['trend']['up'],
                                'flat' => $market['trend']['flat'],
                                'down' => $market['trend']['down'],
                                'average' => ($market['trend']['average_change_pct'] ?? null) !== null
                                    ? $t('mkt.trend.average', ['pct' => $market['trend']['average_change_pct']])
                                    : '',
                            ]) !!}
                        @endif
                    </p>
                    <table class="table">
                        <thead>
                            <tr><th>{{ $t('mkt.th.product') }}</th><th>{{ $t('mkt.th.plan') }}</th><th class="num">{{ $t('mkt.th.price') }}</th><th class="num">{{ $t('mkt.th.trend') }}</th></tr>
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
                                            <span class="trend up">▲ {{ $competitor['change_pct'] !== null ? $competitor['change_pct'].'%' : $t('mkt.trend.up') }}</span>
                                        @elseif (($competitor['trend'] ?? null) === 'down')
                                            <span class="trend down">▼ {{ $competitor['change_pct'] !== null ? $competitor['change_pct'].'%' : $t('mkt.trend.down') }}</span>
                                        @elseif (($competitor['trend'] ?? null) === 'flat')
                                            <span class="trend">— {{ $t('mkt.trend.flat') }}</span>
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
                    <h4>{{ $t('mkt.position.title') }}</h4>
                    @php $position = $packaging['positioning'] ?? null; @endphp
                    @if ($position)
                        <p class="hint">{{ $t('mkt.position.hint', [
                            'tier' => $plan['tier_name'] ?? $t('mkt.position.selected'),
                            'price' => $money2($plan['price_monthly'] ?? ($packaging['anchor_price'] ?? 0)),
                            'count' => $position['competitors'],
                        ]) }}</p>
                        <table class="table">
                            <tbody>
                                <tr><th>{{ $t('mkt.position.cheaper') }}</th><td class="num">{{ $position['cheaper_than_us'] }}</td></tr>
                                <tr><th>{{ $t('mkt.position.pricier') }}</th><td class="num">{{ $position['pricier_than_us'] }}</td></tr>
                                <tr><th>{{ $t('mkt.position.range') }}</th><td class="num">{{ $money($position['min']) }} – {{ $money($position['max']) }}</td></tr>
                                <tr><th>{{ $t('mkt.position.median') }}</th><td class="num">{{ $money($position['median']) }}</td></tr>
                                <tr class="is-total"><th>{{ $t('mkt.position.vs') }}</th><td class="num"><strong>{{ ($position['delta_vs_median_pct'] ?? 0) > 0 ? '+' : '' }}{{ $position['delta_vs_median_pct'] ?? 0 }}%</strong></td></tr>
                            </tbody>
                        </table>
                    @else
                        <p class="hint">{{ $t('mkt.position.none') }}</p>
                    @endif
                    @if (! empty($market['summary']))<p class="hint" style="margin-top:14px">{{ $market['summary'] }}</p>@endif
                    @if (! empty($market['risks']))
                        <h4 style="margin-top:16px">{{ $t('mkt.risks') }}</h4>
                        <ul class="notes">
                            @foreach ($market['risks'] as $risk)<li>{{ $risk }}</li>@endforeach
                        </ul>
                    @endif
                    @if (! empty($market['sources']))
                        <p class="hint" style="margin-top:14px;margin-bottom:0">{{ $t('mkt.sources', ['list' => implode(' · ', $market['sources'])]) }}</p>
                    @endif
                </section>
            </div>
            </details>
        @endif
    </section>

    {{-- INPUTS --}}
    <details class="card panel eco-details">
        <summary>{{ $t('inputs.summary') }}</summary>
        <div class="grid-2" style="margin-top:14px">
            <div>
                <h4>{{ $t('inputs.profile.title') }}</h4>
                <div class="chips">
                    <span class="chip current">{{ $account }}</span>
                    <span class="chip current">{{ $country['code'] ?? '' }}</span>
                    <span class="chip current">{{ $regime['id'] ?? '' }}</span>
                    <span class="chip">{{ $money($profile['hourly_rate'] ?? 0) }}/h</span>
                    <span class="chip">{{ $t('inputs.chip.margin', ['pct' => $profile['margin_target_pct'] ?? 0]) }}</span>
                    <span class="chip">{{ $t('inputs.chip.discount', ['pct' => $profile['discount_pct'] ?? 0]) }}</span>
                    <span class="chip">{{ $t('inputs.chip.team', ['count' => $profile['team_size'] ?? 1]) }}</span>
                    <span class="chip">{{ $t('inputs.chip.overhead', ['amount' => $money($profile['overhead_monthly'] ?? 0)]) }}</span>
                </div>
                <p class="hint">
                    {{ $t('inputs.profile_path') }}: <code>{{ $path ?? '.larapilot/economics.yaml' }}</code><br>
                    {{ $t('inputs.snapshot_path') }}: <code>{{ $snapshot_path ?? '.larapilot/economics.snapshot.yaml' }}</code><br>
                    {{ $t('inputs.market_path') }}: <code>{{ $market['path'] ?? '.larapilot/economics.market.yaml' }}</code><br>
                    {{ $t('inputs.quote_path') }}:
                    @if (($quoteDoc['source'] ?? 'template') === 'document')
                        <code>{{ $quoteDoc['path'] }}</code>{{ ! empty($quoteDoc['stale']) ? ' · '.$t('head.chip.outdated') : '' }}
                    @else
                        {{ $t('inputs.template', ['lang' => $quoteDoc['lang'] ?? 'en']) }}
                    @endif
                </p>
                @if (! empty($simulation['active']))
                    <p class="hint">{{ $t('inputs.simulation') }}</p>
                @endif
            </div>
            <div>
                <h4>{{ $t('words.title') }}</h4>
                <dl class="defs">
                    <div><dt>{{ $t('words.mrr') }}</dt><dd>{{ $t('words.mrr.def') }}</dd></div>
                    <div><dt>{{ $t('words.churn') }}</dt><dd>{{ $t('words.churn.def', [
                        'pct' => $saas['churn_monthly_pct'] ?? 4,
                        'months' => ((float) ($saas['churn_monthly_pct'] ?? 4)) > 0 ? (int) round(100 / (float) ($saas['churn_monthly_pct'] ?? 4)) : 0,
                    ]) }}</dd></div>
                    <div><dt>{{ $t('words.contribution') }}</dt><dd>{{ $t('words.contribution.def') }}</dd></div>
                    <div><dt>{{ $t('words.ltv') }}</dt><dd>{{ $t('words.ltv.def') }}</dd></div>
                    <div><dt>{{ $t('words.breakeven') }}</dt><dd>{{ $t('words.breakeven.def') }}</dd></div>
                    <div><dt>{{ $t('words.personmonths') }}</dt><dd>{{ $t('words.personmonths.def') }}</dd></div>
                </dl>
            </div>
        </div>
    </details>

    <p class="disclaimer">{{ $disclaimer ?? '' }} {{ $t('footer.disclaimer', ['year' => $fiscal_year ?? 2026]) }}</p>
@endif
