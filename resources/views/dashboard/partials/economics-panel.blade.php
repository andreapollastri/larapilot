@php
    use Larapilot\Support\EconomicsStrings;

    $enabled = (bool) ($enabled ?? false);
    $account = $account ?? 'NONE';
    $quote = is_array($quote ?? null) ? $quote : [];
    $tax = is_array($tax ?? null) ? $tax : [];
    $saas = is_array($saas ?? null) ? $saas : null;
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
    // pricing console stays in English — those are operator controls.
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
    $plain = static fn (mixed $value): string => rtrim(rtrim(number_format((float) $value, 1, '.', ','), '0'), '.');

    $overrides = is_array($simulation['overrides'] ?? null) ? $simulation['overrides'] : [];
    $query = http_build_query($overrides);
    $link = static fn (string $route): string => route($route).($query !== '' ? '?'.$query : '');

    $model = (string) ($product['model'] ?? 'fixed');
    $isSaas = $model === 'saas' && $saas !== null;

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

    // ---- the money, once: every block below reads these ----
    $gross = (float) ($quote['gross'] ?? 0);
    $listPrice = (float) ($quote['list_price'] ?? $gross);
    $labour = (float) ($quote['labor'] ?? 0);
    $overhead = (float) ($quote['overhead'] ?? 0);
    $margin = (float) ($quote['margin'] ?? 0);
    $discount = (float) ($quote['discount'] ?? 0);
    $vat = (float) ($quote['vat'] ?? 0);
    $clientTotal = (float) ($quote['client_total'] ?? $gross);
    $net = (float) ($quote['net_to_owner'] ?? 0);
    $taxes = (float) ($tax['total_tax'] ?? 0);
    $paperwork = (float) ($tax['compliance'] ?? 0);
    $reserve = (float) ($tax['legal_reserve'] ?? 0);
    // What is neither tax, nor paperwork, nor yours: the running costs.
    $running = max(0.0, round($gross - $net - $taxes - $paperwork - $reserve, 2));
    $otherTaxes = (float) ($tax['local_tax'] ?? 0) + (float) ($tax['personal_tax'] ?? 0) + (float) ($tax['dividend_tax'] ?? 0);
    $isLoss = $net <= 0;

    $keepShare = $gross > 0 ? (int) round(max(0.0, $net) / $gross * 100) : 0;
    $taxShare = $gross > 0 ? (int) round($taxes / $gross * 100) : 0;
    $costShare = max(0, 100 - $keepShare - $taxShare);
    $billable = (float) ($quote['billable_hours'] ?? ($effort['billable_hours'] ?? 0));
    $netPerHour = $billable > 0 ? $net / $billable : 0.0;

    // ---- the subscription ----
    $selectedLine = null;
    $lines = is_array($plan['lines'] ?? null) ? $plan['lines'] : [];
    foreach ($lines as $line) {
        if (! empty($line['selected'])) {
            $selectedLine = $line;
        }
    }
    $scenarioName = (string) (is_array($selectedLine) ? ($selectedLine['label'] ?? '') : ($saas['scenario_label'] ?? ''));
    $forecast = is_array($selectedLine['forecast'] ?? null)
        ? $selectedLine['forecast']
        : (is_array($saas['forecast'] ?? null) ? $saas['forecast'] : []);
    $recoveredMonth = is_array($selectedLine) ? ($selectedLine['recovered_month'] ?? null) : null;
    $profitableMonth = is_array($selectedLine) ? ($selectedLine['profitable_month'] ?? null) : null;

    $price = (float) ($saas['price_monthly'] ?? 0);
    $left = (float) ($saas['contribution_per_customer'] ?? 0);
    $support = (float) ($saas['support_per_customer'] ?? 0);
    $fees = max(0.0, round($price - $support - $left, 2));
    $bills = (float) ($saas['fixed_monthly'] ?? 0);
    $hosting = (float) ($saas['infrastructure_monthly'] ?? 0);
    $upkeep = (float) ($quote['maintenance_monthly'] ?? 0);
    $office = max(0.0, round($bills - $hosting - $upkeep, 2));
    $needBills = (int) ($saas['break_even_customers'] ?? 0);
    $needBuild = (int) ($saas['customers_to_recover_12m'] ?? 0);

    $sections = [];
    $number = 0;
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
    <header class="page-head eco-top">
        <div>
            <h2>{{ $t('head.title') }}</h2>
            <p class="sub">{{ $t('head.lead') }}</p>
            <div class="chips">
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
        <div class="page-actions eco-actions">
            <a class="btn" href="{{ $link('larapilot.dashboard.economics.quote') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download']){{ $t('head.action.quote') }}</a>
            <a class="btn ghost" href="{{ $link('larapilot.dashboard.economics.report') }}">{{ $t('head.action.report') }}</a>
        </div>
    </header>

    {{-- ============ THE SHORT ANSWER ============ --}}
    <section class="card eco-answer" aria-labelledby="eco-answer-title">
        <span class="eyebrow" id="eco-answer-title">{{ $t('answer.eyebrow') }}</span>
        <p class="eco-answer-lead">
            @if ($isSaas)
                {{ $t('answer.lead.saas', [
                    'price' => $money2($price),
                    'keep' => $money2($left),
                    'bills' => $needBills,
                    'recover' => $needBuild,
                    'build' => $money($gross),
                ]) }}
            @elseif ($model === 'ecommerce')
                {{ $t('answer.lead.ecommerce', [
                    'price' => $money($gross),
                    'net' => $money($net),
                    'orders' => number_format((float) ($payback['orders_per_month_to_recover_12m'] ?? 0), 0, '.', ','),
                ]) }}
            @elseif ($model === 'package')
                {{ $t('answer.lead.package', [
                    'price' => $money($gross),
                    'net' => $money($net),
                    'licences' => number_format((float) ($payback['licenses_to_recover'] ?? 0), 0, '.', ','),
                    'each' => $money($payback['suggested_license_price'] ?? 0),
                ]) }}
            @else
                {{ $t('answer.lead.fixed', [
                    'price' => $money($gross),
                    'net' => $money($net),
                    'months' => $effort['calendar_months'] ?? 0,
                ]) }}
            @endif
        </p>

        <div class="eco-kpis">
            @if ($isSaas)
                <div class="eco-kpi">
                    <span class="eco-kpi-label">{{ $t('kpi.build.label') }}</span>
                    <span class="eco-kpi-value">{{ $money($gross) }}</span>
                    <span class="eco-kpi-sub">{{ $t('kpi.build.sub') }}</span>
                </div>
                <div class="eco-kpi k-keep">
                    <span class="eco-kpi-label"><span class="swatch" aria-hidden="true"></span>{{ $t('saas.step.keep') }}</span>
                    <span class="eco-kpi-value">{{ $money2($left) }}</span>
                    <span class="eco-kpi-sub">{{ $t('kpi.left.sub') }}</span>
                </div>
                <div class="eco-kpi">
                    <span class="eco-kpi-label">{{ $t('saas.step.bills') }}</span>
                    <span class="eco-kpi-value">{{ number_format($needBills) }}</span>
                    <span class="eco-kpi-sub">{{ $t('kpi.bills.sub') }}</span>
                </div>
                <div @class(['eco-kpi', 'is-good' => $recoveredMonth, 'is-warn' => ! $recoveredMonth])>
                    <span class="eco-kpi-label">{{ $t('kpi.back.label') }}</span>
                    <span class="eco-kpi-value">
                        @include('larapilot::dashboard.partials.icon', ['name' => $recoveredMonth ? 'check' : 'info'])
                        {{ $recoveredMonth ? $t('kpi.back.month', ['month' => $recoveredMonth]) : $t('kpi.back.never') }}
                    </span>
                    <span class="eco-kpi-sub">{{ $t('kpi.back.sub', ['scenario' => $scenarioName]) }}</span>
                </div>
            @else
                <div class="eco-kpi">
                    <span class="eco-kpi-label">{{ $t('kpi.client.label') }}</span>
                    <span class="eco-kpi-value">{{ $money($clientTotal) }}</span>
                    <span class="eco-kpi-sub">{{ $vat > 0 ? $t('kpi.client.vat', ['price' => $money($gross), 'vat' => $money($vat)]) : $t('kpi.client.novat') }}</span>
                </div>
                <div @class(['eco-kpi', 'k-keep', 'is-warn' => $isLoss])>
                    <span class="eco-kpi-label"><span class="swatch" aria-hidden="true"></span>{{ $t('kpi.keep.label') }}</span>
                    <span class="eco-kpi-value">{{ $money($net) }}</span>
                    <span class="eco-kpi-sub">{{ $isLoss ? $t('kpi.keep.loss') : $t('kpi.keep.sub', ['share' => $currency.' '.$keepShare, 'hundred' => $currency.' 100']) }}</span>
                </div>
                <div class="eco-kpi">
                    <span class="eco-kpi-label">{{ $t('kpi.time.label') }}</span>
                    <span class="eco-kpi-value">{{ $t('kpi.time.value', ['months' => $plain($effort['calendar_months'] ?? 0)]) }}</span>
                    <span class="eco-kpi-sub">{{ $t('kpi.time.sub', ['hours' => $hours($billable)]) }} · {{ $plain($effort['team_size'] ?? 1) }} {{ ((float) ($effort['team_size'] ?? 1)) == 1.0 ? $t('m.delivery.person') : $t('m.delivery.people') }}</span>
                </div>
                <div class="eco-kpi">
                    <span class="eco-kpi-label">{{ $t('kpi.upkeep.label') }}</span>
                    <span class="eco-kpi-value">{{ $money($quote['maintenance_year'] ?? 0) }}</span>
                    <span class="eco-kpi-sub">{{ $t('kpi.upkeep.sub', ['monthly' => $money($quote['maintenance_monthly'] ?? 0)]) }}</span>
                </div>
            @endif
        </div>
    </section>

    {{-- ============ WHAT TO READ FIRST ============ --}}
    @if ($warnings !== [] || ! empty($quote['below_cost']))
        <div class="banner warn">
            <strong>{{ $t('banner.warnings.title') }}</strong>
            <ul>
                @if (! empty($quote['below_cost']))
                    <li>{{ $t('banner.below_cost', ['gross' => $money($gross), 'direct' => $money($quote['direct'] ?? 0)]) }}</li>
                @endif
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
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

    {{-- ============ THE WAY THROUGH THE PAGE ============ --}}
    <nav class="eco-nav" aria-label="{{ $t('nav.label') }}">
        <span class="eyebrow">{{ $t('nav.label') }}</span>
        <ul>
            @if ($isSaas)
                <li><a href="#eco-subscription">{{ $t('saas.how.title') }}</a></li>
            @endif
            <li><a href="#eco-price">{{ $t('s3.title') }}</a></li>
            <li><a href="#eco-after">{{ $t('after.title') }}</a></li>
            @if ($isSaas && $packaging && $plan)
                <li><a href="#eco-plans">{{ $t('pack.title') }}</a></li>
            @endif
            <li><a href="#eco-hours">{{ $t('s2.title') }}</a></li>
            <li><a href="#eco-market">{{ $t('mkt.title') }}</a></li>
            <li><a href="#eco-words">{{ $t('words.title') }}</a></li>
        </ul>
    </nav>

    {{-- ============ THE PRICING CONSOLE — operator controls, left in English ============ --}}
    <form id="eco-controls" class="card eco-console" method="get" action="{{ route('larapilot.dashboard.economics') }}">
        <div class="eco-console-head">
            <div>
                <h3>{{ $t('console.title') }}</h3>
                <p class="eco-console-hint">{{ $t('console.hint') }}</p>
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

        @if ($productControl)
            <div class="eco-console-row">
                <span class="eco-console-title">Sold as</span>
                <div class="eco-toggle">
                    @foreach ($productControl['options'] as $option)
                        <label @class(['is-on' => (string) $option['value'] === (string) $productControl['value']])>
                            <input type="radio" name="product_model" value="{{ $option['value'] }}"
                                data-eco-default="{{ $productControl['saved'] }}"
                                data-eco-help="{{ $t('models.'.$option['value']) }}"
                                @checked((string) $option['value'] === (string) $productControl['value'])>
                            <span>{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach (['quote' => 'The quote', 'account' => 'Who is selling', 'saas' => 'Subscription'] as $group => $title)
            @php $fields = $grouped[$group] ?? []; @endphp
            @if ($fields !== [] && ($group !== 'saas' || $isSaas))
                @if ($group === 'account')
                <details class="eco-console-group eco-fold" data-fold="console-account">
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
                                <select name="{{ $control['key'] }}" data-eco-default="{{ $control['saved'] }}" data-eco-help="{{ $control['hint'] }}">
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

        <p class="eco-console-help" data-eco-help-line data-eco-help-idle="{{ $t('console.help') }}" role="status">@include('larapilot::dashboard.partials.icon', ['name' => 'info'])<span>{{ $t('console.help') }}</span></p>
    </form>

    @if (! empty($simulation['active']))
        <div class="banner">
            <strong>{{ $t('banner.simulation.title') }}</strong>
            {!! $th('banner.simulation.body') !!}
            <pre class="eco-command" data-eco-command>{{ $simulation['command'] }}</pre>
            <button class="btn ghost small" type="button" data-eco-copy>Copy command</button>
        </div>
    @endif

    @if ($productControl)
        {{-- The four ways a project makes money, and what each one turns on. --}}
        <details class="card panel eco-details eco-models" data-fold="models">
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

    {{-- ============ THE SUBSCRIPTION ============ --}}
    @if ($isSaas)
        <section class="eco-section" id="eco-subscription">
            <div class="eco-section-head">
                <span class="eco-section-num">{{ ++$number }}</span>
                <div>
                    <h3>{{ $t('saas.how.title') }}</h3>
                    <p>{{ $t('sub.lead') }}</p>
                </div>
            </div>

            <div class="eco-pair">
                <article class="card panel eco-sum">
                    <header>
                        <span class="eco-step-k">1</span>
                        <div>
                            <h4>{{ $t('sub.one.title') }}</h4>
                            <p class="hint">{{ $t('sub.one.lead') }}</p>
                        </div>
                    </header>
                    @include('larapilot::dashboard.partials.economics-split', [
                        'label' => $t('sub.bar'),
                        'segments' => [
                            ['key' => 'keep', 'label' => $t('saas.step.keep'), 'value' => max(0, $left), 'amount' => $money2($left)],
                            ['key' => 'costs', 'label' => $t('sub.fees').' + '.$t('sub.support'), 'value' => $fees + $support, 'amount' => $money2($fees + $support)],
                        ],
                    ])
                    @include('larapilot::dashboard.partials.economics-receipt', [
                        'caption' => $t('sub.one.title'),
                        'rows' => [
                            ['sign' => '', 'label' => $t('saas.step.price'), 'how' => $t('sub.price.how'), 'amount' => $money2($price)],
                            ['sign' => '−', 'key' => 'costs', 'label' => $t('sub.fees'), 'how' => $t('sub.fees.how', ['pct' => $plain($saas['payment_fee_pct'] ?? 0)]), 'amount' => $money2($fees)],
                            ['sign' => '−', 'key' => 'costs', 'label' => $t('sub.support'), 'how' => $t('sub.support.how'), 'amount' => $money2($support)],
                            ['sign' => '=', 'key' => 'keep', 'label' => $t('saas.step.keep'), 'how' => $t('sub.left.how'), 'amount' => $money2($left), 'result' => true, 'final' => true],
                        ],
                    ])
                </article>

                <article class="card panel eco-sum">
                    <header>
                        <span class="eco-step-k">2</span>
                        <div>
                            <h4>{{ $t('sub.bills.title') }}</h4>
                            <p class="hint">{{ $t('sub.bills.lead') }}</p>
                        </div>
                    </header>
                    @include('larapilot::dashboard.partials.economics-receipt', [
                        'caption' => $t('sub.bills.title'),
                        'rows' => [
                            ['sign' => '', 'label' => $t('sub.hosting'), 'how' => $t('sub.hosting.how'), 'amount' => $money2($hosting)],
                            ['sign' => '+', 'label' => $t('sub.upkeep'), 'how' => $t('sub.upkeep.how', ['pct' => $plain($profile['maintenance_annual_pct'] ?? 15)]), 'amount' => $money2($upkeep)],
                            ['sign' => '+', 'label' => $t('sub.office'), 'how' => $t('sub.office.how'), 'amount' => $money2($office)],
                            ['sign' => '=', 'label' => $t('sub.bills.total'), 'amount' => $money2($bills), 'result' => true, 'final' => true],
                        ],
                    ])
                </article>
            </div>

            <article class="card panel eco-sum">
                <header>
                    <span class="eco-step-k">3</span>
                    <div>
                        <h4>{{ $t('sub.need.title') }}</h4>
                        <p class="hint">{{ $t('sub.need.lead') }}</p>
                    </div>
                </header>
                <div class="eco-needs">
                    <div class="eco-need">
                        <span class="eco-need-value">{{ number_format($needBills) }} <small>{{ $t('sub.need.unit') }}</small></span>
                        <strong>{{ $t('saas.step.bills') }}</strong>
                        <code class="eco-formula">{{ $t('sub.need.bills.how', ['fixed' => $money2($bills), 'keep' => $money2($left)]) }}</code>
                        <p>{{ $t('sub.need.bills.means') }}</p>
                    </div>
                    <div class="eco-need">
                        <span class="eco-need-value">{{ number_format($needBuild) }} <small>{{ $t('sub.need.unit') }}</small></span>
                        <strong>{{ $t('saas.step.build') }}</strong>
                        <code class="eco-formula">{{ $t('sub.need.build.how', ['build' => $money($gross), 'fixed' => $money2($bills), 'keep' => $money2($left)]) }}</code>
                        <p>{{ $t('sub.need.build.means') }}</p>
                    </div>
                </div>

                @if ($selectedLine)
                    @php
                        $expected = [
                            ['label' => $t('gap.y1'), 'count' => (int) ($selectedLine['customers_m12'] ?? 0)],
                            ['label' => $t('gap.y2'), 'count' => (int) ($selectedLine['customers_m24'] ?? 0)],
                            ['label' => $t('gap.y3'), 'count' => (int) ($selectedLine['customers_m36'] ?? 0)],
                        ];
                        $gapMax = max(1, $needBuild, $needBills, ...array_column($expected, 'count')) * 1.08;
                        $at = static fn (float $value): float => round(min(100, $value / $gapMax * 100), 2);
                    @endphp
                    <div class="eco-gap">
                        <h5>{{ $t('gap.title') }}</h5>
                        <p class="hint">{{ $t('gap.lead', ['scenario' => $scenarioName]) }}</p>
                        <div class="gap-chart" style="--bills: {{ $at($needBills) }}%; --build: {{ $at($needBuild) }}%">
                            {{-- The two marks are named once, by their number; every bar carries both. --}}
                            <ul class="gap-key">
                                <li><span class="gap-tick-key" aria-hidden="true"></span>{{ $t('gap.line.bills', ['count' => number_format($needBills)]) }}</li>
                                <li><span class="gap-tick-key" aria-hidden="true"></span>{{ $t('gap.line.build', ['count' => number_format($needBuild)]) }}</li>
                            </ul>
                            @foreach ($expected as $row)
                                @php
                                    $verdict = match (true) {
                                        $row['count'] >= $needBuild => $t('gap.repaid'),
                                        $row['count'] >= $needBills => $t('gap.covered'),
                                        default => $t('gap.short', ['count' => number_format($needBills - $row['count'])]),
                                    };
                                @endphp
                                <div class="gap-row" title="{{ $row['label'] }}: {{ $t('gap.count', ['count' => number_format($row['count'])]) }} — {{ $verdict }}">
                                    <span class="gap-label">{{ $row['label'] }}</span>
                                    <span class="gap-track">
                                        <span class="gap-bar" style="width: {{ $at($row['count']) }}%"></span>
                                        <span class="gap-tick is-bills" data-n="{{ number_format($needBills) }}" aria-hidden="true"></span>
                                        <span class="gap-tick is-build" data-n="{{ number_format($needBuild) }}" aria-hidden="true"></span>
                                    </span>
                                    <span class="gap-value"><b>{{ $t('gap.count', ['count' => number_format($row['count'])]) }}</b> <small>— {{ $verdict }}</small></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>

            {{-- 4 · when the money comes back --}}
            @if ($forecast !== [])
                @php
                    $series = [];
                    foreach ($lines as $line) {
                        $points = [['month' => 0, 'total' => -1 * (float) ($plan['investment'] ?? $gross)]];
                        foreach (is_array($line['forecast'] ?? null) ? $line['forecast'] : [] as $row) {
                            $points[] = ['month' => (int) $row['month'], 'total' => (float) $row['cumulative']];
                        }
                        $series[] = ['id' => $line['id'], 'label' => $line['label'], 'selected' => ! empty($line['selected']), 'points' => $points];
                    }
                    if ($series === []) {
                        $points = [['month' => 0, 'total' => -1 * $gross]];
                        foreach ($forecast as $row) {
                            $points[] = ['month' => (int) $row['month'], 'total' => (float) $row['cumulative']];
                        }
                        $series[] = ['id' => 'forecast', 'label' => $scenarioName, 'selected' => true, 'points' => $points];
                    }

                    $all = [];
                    foreach ($series as $one) {
                        foreach ($one['points'] as $point) {
                            $all[] = $point['total'];
                        }
                    }
                    $low = min(0.0, ...$all);
                    $high = max(0.0, ...$all);

                    // Round figures on the axis: a step of 1, 2 or 5 times a power of ten.
                    $span = max(1.0, $high - $low);
                    $rough = $span / 4;
                    $power = 10 ** floor(log10($rough));
                    $step = $power * match (true) {
                        $rough / $power <= 1 => 1,
                        $rough / $power <= 2 => 2,
                        $rough / $power <= 5 => 5,
                        default => 10,
                    };
                    $axisLow = floor($low / $step) * $step;
                    $axisHigh = ceil($high / $step) * $step;
                    $axisHigh = $axisHigh === $axisLow ? $axisLow + $step : $axisHigh;
                    $ticks = [];
                    for ($value = $axisLow; $value <= $axisHigh + 0.001; $value += $step) {
                        $ticks[] = round($value, 2);
                    }

                    $px = static fn (int $month): float => round($month / 36 * 100, 3);
                    $py = static fn (float $value): float => round(($axisHigh - $value) / ($axisHigh - $axisLow) * 100, 3);
                    $trace = static fn (array $points): string => implode(' ', array_map(
                        static fn (array $point, int $index): string => ($index === 0 ? 'M' : 'L').$px($point['month']).' '.$py($point['total']),
                        $points,
                        array_keys($points)
                    ));
                    $short = static function (float $value) use ($currency): string {
                        $abs = abs($value);
                        $text = match (true) {
                            $abs >= 1000000 => rtrim(rtrim(number_format($abs / 1000000, 1, '.', ''), '0'), '.').'M',
                            $abs >= 1000 => rtrim(rtrim(number_format($abs / 1000, 1, '.', ''), '0'), '.').'k',
                            default => number_format($abs, 0, '.', ','),
                        };

                        return ($value < 0 ? '−' : '').$text;
                    };

                    $selectedSeries = collect($series)->firstWhere('selected', true) ?? $series[0];
                    $months = [];
                    $months[] = ['month' => 0, 'customers' => 0, 'income' => $money(0), 'left' => $money(0), 'total' => $money($selectedSeries['points'][0]['total']), 'x' => 0, 'y' => $py($selectedSeries['points'][0]['total'])];
                    foreach ($forecast as $row) {
                        $months[] = [
                            'month' => (int) $row['month'],
                            'customers' => (int) $row['customers'],
                            'income' => $money($row['mrr']),
                            'left' => $money($row['net']),
                            'total' => $money($row['cumulative']),
                            'x' => $px((int) $row['month']),
                            'y' => $py((float) $row['cumulative']),
                        ];
                    }
                    $last = end($forecast);
                    $endTotal = (float) ($last['cumulative'] ?? 0);
                    $pointAt = static fn (?int $month): ?array => $month === null ? null : (collect($months)->firstWhere('month', $month));
                    $billsPoint = $pointAt($profitableMonth ? (int) $profitableMonth : null);
                    $backPoint = $pointAt($recoveredMonth ? (int) $recoveredMonth : null);
                @endphp
                <article class="card panel eco-sum">
                    <header>
                        <span class="eco-step-k">4</span>
                        <div>
                            <h4>{{ $t('cash.title') }}</h4>
                            <p class="hint">{{ $t('cash.lead') }}</p>
                        </div>
                    </header>

                    <ul class="cash-legend">
                        @foreach ($series as $one)
                            <li @class(['is-selected' => $one['selected']])>
                                <span class="cash-key" aria-hidden="true"></span>
                                {{ $one['selected'] ? $t('cash.legend.this', ['scenario' => $one['label']]) : $one['label'] }}
                            </li>
                        @endforeach
                    </ul>

                    @php
                        $tipLabels = [
                            'month' => $t('table.month'),
                            'customers' => $t('cash.tip.customers'),
                            'income' => $t('cash.tip.income'),
                            'left' => $t('cash.tip.left'),
                            'total' => $t('cash.tip.total'),
                            'start' => $t('cash.start'),
                        ];
                    @endphp
                    <div class="cash" data-cash data-months="{{ json_encode($months) }}" data-labels="{{ json_encode($tipLabels) }}">
                        <div class="cash-y" aria-hidden="true">
                            <span class="cash-unit">{{ $currency }}</span>
                            @foreach ($ticks as $tick)
                                <span style="top: {{ $py($tick) }}%" @class(['is-zero' => (float) $tick === 0.0])>{{ (float) $tick === 0.0 ? '0' : $short($tick) }}</span>
                            @endforeach
                        </div>
                        <div class="cash-plot" data-cash-plot tabindex="0" role="img"
                            aria-label="{{ $t('cash.chart') }}. {{ $t('cash.start') }}: {{ $money($selectedSeries['points'][0]['total']) }}. {{ $t('cash.end') }}: {{ $money($endTotal) }}. {{ $recoveredMonth ? $t('kpi.back.label').': '.$t('plan.month', ['month' => $recoveredMonth]) : $t('kpi.back.label').': '.$t('kpi.back.never') }}.">
                            @foreach ($ticks as $tick)
                                <span @class(['cash-grid', 'is-zero' => (float) $tick === 0.0]) style="top: {{ $py($tick) }}%"></span>
                            @endforeach
                            <span class="cash-zero-label" style="top: {{ $py(0) }}%">{{ $t('cash.zero') }}</span>
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                @foreach ($series as $one)
                                    @unless ($one['selected'])
                                        <path class="cash-line is-other" d="{{ $trace($one['points']) }}"/>
                                    @endunless
                                @endforeach
                                <path class="cash-area" d="{{ $trace($selectedSeries['points']) }} L100 {{ $py(0) }} L0 {{ $py(0) }} Z"/>
                                <path class="cash-line" d="{{ $trace($selectedSeries['points']) }}"/>
                            </svg>
                            <span class="cash-dot is-start" style="left: 0%; top: {{ $months[0]['y'] }}%"></span>
                            @if ($billsPoint)
                                <span class="cash-dot is-bills" style="left: {{ $billsPoint['x'] }}%; top: {{ $billsPoint['y'] }}%"></span>
                            @endif
                            @if ($backPoint)
                                <span class="cash-dot is-back" style="left: {{ $backPoint['x'] }}%; top: {{ $backPoint['y'] }}%"></span>
                            @endif
                            <span class="cash-dot is-end" style="left: 100%; top: {{ $py($endTotal) }}%"></span>
                            <span class="cash-cursor" data-cash-cursor hidden></span>
                            <span class="cash-dot is-cursor" data-cash-dot hidden></span>
                            <div class="cash-tip" data-cash-tip hidden></div>
                        </div>
                        <div class="cash-x" aria-hidden="true">
                            @foreach ([0, 6, 12, 18, 24, 30, 36] as $month)
                                <span style="left: {{ $px($month) }}%">{{ $month }}</span>
                            @endforeach
                        </div>
                        <p class="cash-axis">{{ $t('cash.axis') }}</p>
                    </div>
                    <p class="hint cash-hint">{{ $t('cash.hint') }}</p>

                    <ol class="cash-story">
                        <li class="is-start">
                            <span class="cash-when">{{ $t('cash.start') }}</span>
                            <span>{{ $t('cash.start.text', ['amount' => $money(abs($selectedSeries['points'][0]['total']))]) }}</span>
                        </li>
                        <li @class(['is-bills' => $profitableMonth, 'is-never' => ! $profitableMonth])>
                            <span class="cash-when">{{ $profitableMonth ? $t('kpi.back.month', ['month' => $profitableMonth]) : '—' }}</span>
                            <span>{{ $profitableMonth ? $t('cash.bills.text') : $t('cash.bills.never') }}</span>
                        </li>
                        @if ($recoveredMonth)
                            <li class="is-back">
                                <span class="cash-when">{{ $t('kpi.back.month', ['month' => $recoveredMonth]) }}</span>
                                <span>{{ $t('cash.back.text') }}</span>
                            </li>
                        @endif
                        <li class="is-end">
                            <span class="cash-when">{{ $t('cash.end') }}</span>
                            <span>{{ $endTotal >= 0 ? $t('cash.end.ahead', ['amount' => $money($endTotal)]) : $t('cash.end.behind', ['amount' => $money(abs($endTotal))]) }}</span>
                        </li>
                    </ol>

                    <details class="eco-fold is-inner" data-fold="months">
                        <summary>{{ $t('show.months') }}</summary>
                        <div class="table-wrap">
                            <table class="table" style="margin-top:12px">
                                <thead>
                                    <tr>
                                        <th>{{ $t('table.month') }}</th>
                                        <th class="num">{{ $t('table.customers') }}</th>
                                        <th class="num">{{ $t('table.income') }}</th>
                                        <th class="num">{{ $t('table.left') }}</th>
                                        <th class="num">{{ $t('table.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (collect($forecast)->filter(fn (array $row): bool => (int) $row['month'] % 3 === 0 || (int) $row['month'] === 1) as $row)
                                        <tr>
                                            <td>{{ $row['month'] }}</td>
                                            <td class="num">{{ $row['customers'] }}</td>
                                            <td class="num">{{ $money($row['mrr']) }}</td>
                                            <td class="num">{{ $money($row['net']) }}</td>
                                            <td class="num">{{ $money($row['cumulative']) }}{{ ! empty($row['recovered']) ? ' ✓' : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </article>
            @endif

            {{-- the three forecasts --}}
            @if ($lines !== [])
                <article class="card panel eco-sum">
                    <header>
                        <span class="eco-step-k">5</span>
                        <div>
                            <h4>{{ $t('scen.title') }}</h4>
                            <p class="hint">{{ $t('scen.lead') }}</p>
                        </div>
                    </header>
                    <div class="plan-lines">
                        @foreach ($lines as $line)
                            @php
                                $verdict = match (true) {
                                    ! empty($line['recovered_month']) => ['good', 'check', $t('scen.verdict.back', ['month' => $line['recovered_month']])],
                                    ! empty($line['profitable_month']) => ['mid', 'info', $t('scen.verdict.bills', ['month' => $line['profitable_month']])],
                                    default => ['bad', 'info', $t('scen.verdict.loss')],
                                };
                            @endphp
                            <section @class(['plan-line', 'is-selected' => ! empty($line['selected'])])>
                                <div class="tier-head">
                                    <span class="tier-name">{{ $line['label'] }}</span>
                                    @if (! empty($line['selected']))<span class="chip current">{{ $t('scen.inuse') }}</span>@endif
                                </div>
                                <p class="verdict is-{{ $verdict[0] }}">@include('larapilot::dashboard.partials.icon', ['name' => $verdict[1]])<span>{{ $verdict[2] }}</span></p>
                                <table class="table">
                                    <tbody>
                                        <tr><th>{{ $t('scen.customers') }}</th><td class="num">{{ $line['customers_m12'] }} · {{ $line['customers_m24'] }} · {{ $line['customers_m36'] }}</td></tr>
                                        <tr><th>{{ $t('scen.arrive') }}</th><td class="num">+{{ $plain($line['growth_monthly_pct']) }}%</td></tr>
                                        <tr><th>{{ $t('scen.leave') }}</th><td class="num">−{{ $plain($line['churn_monthly_pct']) }}%</td></tr>
                                        <tr><th>{{ $t('scen.income') }}</th><td class="num">{{ $money($line['mrr_m36']) }}</td></tr>
                                        <tr><th>{{ $t('scen.leads') }}<small>{{ $t('scen.leads.how', ['pct' => $plain($line['conversion_pct'])]) }}</small></th><td class="num">{{ number_format($line['leads_needed'], 0, '.', ',') }}</td></tr>
                                        <tr class="is-total"><th>{{ $t('scen.cash') }}</th><td class="num"><strong>{{ $money($line['cumulative_m36']) }}</strong></td></tr>
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
                            </section>
                        @endforeach
                    </div>
                    @if (! empty($plan['notes']))
                        <p class="disclaimer" style="margin-top:14px">@foreach ($plan['notes'] as $note){{ $note }} @endforeach</p>
                    @endif
                </article>
            @endif

            {{-- is a customer worth it --}}
            @php
                $ltv = (float) ($saas['ltv'] ?? 0);
                $cac = (float) ($saas['cac'] ?? 0);
                $ratio = $saas['ltv_cac'] ?? null;
                $stay = ((float) ($saas['churn_monthly_pct'] ?? 0)) > 0 ? (int) round(100 / (float) $saas['churn_monthly_pct']) : 0;
            @endphp
            @if ($ltv > 0 && $cac > 0)
                <details class="card eco-fold" data-fold="worth">
                    <summary>{{ $t('worth.title') }}</summary>
                    <div class="eco-pair" style="margin-top:14px">
                        <div>
                            @include('larapilot::dashboard.partials.economics-receipt', [
                                'caption' => $t('worth.title'),
                                'rows' => [
                                    ['sign' => '', 'label' => $t('worth.brings'), 'how' => $t('worth.brings.how', ['price' => $money2($price), 'months' => $stay]), 'amount' => $money($ltv)],
                                    ['sign' => '÷', 'label' => $t('worth.costs'), 'how' => $t('worth.costs.how'), 'amount' => $money($cac)],
                                    ['sign' => '=', 'label' => $t('worth.ratio', ['one' => $currency.' 1']), 'amount' => $money2((float) $ratio), 'result' => true, 'final' => true],
                                ],
                            ])
                            <p @class(['verdict', 'is-good' => (float) $ratio >= 3, 'is-mid' => (float) $ratio < 3]) style="margin-top:12px">@include('larapilot::dashboard.partials.icon', ['name' => (float) $ratio >= 3 ? 'check' : 'info'])<span>{{ (float) $ratio >= 3 ? $t('worth.ok') : $t('worth.low') }}</span></p>
                        </div>
                        @if (! empty($saas['server_notes']))
                            <div>
                                <h4>{{ $t('plan.running.title') }}</h4>
                                <ul class="notes">
                                    @foreach ($saas['server_notes'] as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </details>
            @endif
        </section>
    @endif

    {{-- ============ THE PRICE, AND WHERE IT GOES ============ --}}
    <section class="eco-section" id="eco-price">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ ++$number }}</span>
            <div>
                <h3>{{ $t('s3.title') }}</h3>
                <p>{{ $isSaas ? $t('s1.build.note').' ' : '' }}{{ $t('s3.sub', [
                    'country' => $country['name'] ?? '',
                    'year' => $fiscal_year ?? 2026,
                    'regime' => $regime['label'] ?? $t('s3.regime.fallback'),
                    'account' => strtolower($account),
                ]) }}</p>
            </div>
        </div>

        <div class="eco-pair">
            <article class="card panel eco-sum">
                <header>
                    <div>
                        <h4>{{ $t('s3.build.title') }}</h4>
                        <p class="hint">{{ $t('price.lead') }}</p>
                    </div>
                </header>
                @include('larapilot::dashboard.partials.economics-split', [
                    'label' => $t('price.bar'),
                    'segments' => [
                        ['key' => 'work', 'label' => $t('price.work'), 'value' => $labour, 'amount' => $money($labour)],
                        ['key' => 'costs', 'label' => $t('price.costs'), 'value' => $overhead, 'amount' => $money($overhead)],
                        ['key' => 'margin', 'label' => $t('price.margin'), 'value' => $margin, 'amount' => $money($margin)],
                    ],
                ])
                @php
                    $priceRows = [
                        ['sign' => '', 'key' => 'work', 'label' => $t('price.work'), 'how' => $t('price.work.how', ['hours' => $hours($billable), 'rate' => $money($quote['hourly_rate'] ?? 0)]), 'amount' => $money($labour)],
                        ['sign' => '+', 'key' => 'costs', 'label' => $t('price.costs'), 'how' => $t('price.costs.how'), 'amount' => $money($overhead)],
                        ['sign' => '+', 'key' => 'margin', 'label' => $t('price.margin'), 'how' => $t('price.margin.how', ['pct' => $plain($quote['margin_pct'] ?? 0)]), 'amount' => $money($margin)],
                    ];

                    if ($discount > 0) {
                        $priceRows[] = ['sign' => '=', 'label' => $t('price.list'), 'amount' => $money($listPrice), 'result' => true];
                        $priceRows[] = ['sign' => '−', 'label' => $t('price.discount'), 'how' => $t('price.discount.how', ['pct' => $plain($quote['discount_pct'] ?? 0)]), 'amount' => $money($discount)];
                    }

                    $priceRows[] = ['sign' => '=', 'label' => $t('m.price.label'), 'how' => $t('price.client.how'), 'amount' => $money($gross), 'result' => true, 'final' => $vat <= 0];

                    if ($vat > 0) {
                        $priceRows[] = ['sign' => '+', 'label' => $t('price.vat'), 'how' => $t('price.vat.how', ['rate' => $plain(((float) ($quote['vat_rate'] ?? 0)) <= 1 ? ((float) ($quote['vat_rate'] ?? 0)) * 100 : ($quote['vat_rate'] ?? 0))]), 'amount' => $money($vat)];
                        $priceRows[] = ['sign' => '=', 'label' => $t('price.total'), 'how' => $t('price.total.how'), 'amount' => $money($clientTotal), 'result' => true, 'final' => true];
                    }
                @endphp
                @include('larapilot::dashboard.partials.economics-receipt', ['caption' => $t('s3.build.title'), 'rows' => $priceRows])
                @if ($vat <= 0)
                    <p class="hint eco-after">{{ $t('price.novat') }}</p>
                @endif
            </article>

            <article class="card panel eco-sum">
                <header>
                    <div>
                        <h4>{{ $t('flow.title') }}</h4>
                        <p class="hint">{{ $t('flow.lead') }}</p>
                    </div>
                </header>
                @include('larapilot::dashboard.partials.economics-split', [
                    'label' => $t('flow.bar'),
                    'segments' => [
                        ['key' => 'keep', 'label' => $t('flow.keep'), 'value' => max(0, $net), 'amount' => $money($net)],
                        ['key' => 'tax', 'label' => $t('flow.tax'), 'value' => $taxes, 'amount' => $money($taxes)],
                        ['key' => 'costs', 'label' => $t('flow.costs.group'), 'value' => $running + $paperwork + $reserve, 'amount' => $money($running + $paperwork + $reserve)],
                    ],
                ])
                @php
                    $flowRows = [
                        ['sign' => '', 'label' => $t('m.price.label'), 'how' => $t('flow.price.how'), 'amount' => $money($gross)],
                        ['sign' => '−', 'key' => 'costs', 'label' => $t('price.costs'), 'how' => $t('flow.costs.how'), 'amount' => $money($running)],
                        ['sign' => '−', 'key' => 'tax', 'label' => $t('flow.tax'), 'how' => $otherTaxes > 0
                            ? $t('flow.tax.how.other', ['income' => $money($tax['income_tax'] ?? 0), 'social' => $money($tax['social'] ?? 0), 'other' => $money($otherTaxes)])
                            : $t('flow.tax.how', ['income' => $money($tax['income_tax'] ?? 0), 'social' => $money($tax['social'] ?? 0)]), 'amount' => $money($taxes)],
                        ['sign' => '−', 'key' => 'costs', 'label' => $t('flow.paperwork'), 'how' => $t('flow.paperwork.how'), 'amount' => $money($paperwork)],
                    ];

                    if ($reserve > 0) {
                        $flowRows[] = ['sign' => '−', 'key' => 'costs', 'label' => $t('flow.reserve'), 'how' => $t('flow.reserve.how'), 'amount' => $money($reserve)];
                    }

                    $flowRows[] = ['sign' => '=', 'key' => 'keep', 'label' => $t('flow.keep'), 'how' => $t('flow.keep.how'), 'amount' => $money($net), 'result' => true, 'final' => true];
                @endphp
                @include('larapilot::dashboard.partials.economics-receipt', ['caption' => $t('flow.title'), 'rows' => $flowRows])
                <p class="eco-plain">
                    @if ($isLoss)
                        <strong>{{ $t('flow.loss') }}</strong>
                    @else
                        {{ $t('flow.hundred', [
                            'hundred' => $currency.' 100',
                            'keep' => $currency.' '.$keepShare,
                            'tax' => $currency.' '.$taxShare,
                            'costs' => $currency.' '.$costShare,
                        ]) }}
                        {{ $t('flow.hour', ['net' => $money2($netPerHour), 'rate' => $money($quote['hourly_rate'] ?? 0)]) }}
                    @endif
                </p>
            </article>
        </div>

        <details class="card eco-fold" data-fold="tax">
            <summary>{{ $t('show.tax') }}</summary>
            <section class="panel">
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
    </section>

    {{-- ============ AFTER DELIVERY ============ --}}
    <section class="eco-section" id="eco-after">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ ++$number }}</span>
            <div>
                <h3>{{ $t('after.title') }}</h3>
                <p>{{ $t('after.lead') }}</p>
            </div>
        </div>

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
            <article class="card metric">
                <div class="metric-label">{{ $t('m.capacity.label') }}</div>
                <div class="metric-value">{{ $payback['utilization_pct'] ?? 0 }}%</div>
                <div class="metric-sub">{{ $t('m.capacity.sub', ['hours' => $hours($payback['capacity_hours_year'] ?? 0)]) }}</div>
                <div class="bar-track eco-meter" role="img" aria-label="{{ $payback['utilization_pct'] ?? 0 }}%"><div class="bar-fill" style="width: {{ min(100, (float) ($payback['utilization_pct'] ?? 0)) }}%"></div></div>
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
            <details class="card eco-fold" data-fold="maintenance">
                <summary>{{ $t('maint.title') }}</summary>
                <section class="panel">
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
            </details>
        @endif
    </section>

    {{-- ============ THE PLANS ============ --}}
    @if ($isSaas && $packaging && $plan)
        <section class="eco-section" id="eco-plans">
            <div class="eco-section-head">
                <span class="eco-section-num">{{ ++$number }}</span>
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
                                <tr><th>{{ $t('saas.step.keep') }}<small>{{ $t('kpi.left.sub') }}</small></th><td class="num">{{ $money2($tier['contribution_per_customer']) }}</td></tr>
                                <tr><th>{{ $t('saas.step.bills') }}</th><td class="num">{{ $tier['break_even_customers'] }}</td></tr>
                                <tr><th>{{ $t('saas.step.build') }}</th><td class="num">{{ $tier['customers_to_recover_12m'] }}</td></tr>
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

            <details class="card eco-fold" data-fold="mix">
                <summary>{{ $t('pack.mix.title') }}</summary>
                <p class="hint">{!! $th('pack.mix.hint', [
                    'mix' => collect($packaging['tiers'])->map(fn ($tier) => $tier['name'].' '.$tier['share_pct'].'%')->implode(' · '),
                    'arpu' => $money2($packaging['blended_arpu'] ?? 0),
                    'arr' => $money($packaging['blended_arr_per_100'] ?? 0),
                ]) !!}</p>
            </details>
        </section>
    @endif

    {{-- ============ THE HOURS ============ --}}
    <section class="eco-section" id="eco-hours">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ ++$number }}</span>
            <div>
                <h3>{{ $t('s2.title') }}</h3>
                <p>{{ $t('s2.lead', ['hours' => $hours($effort['billable_hours'] ?? 0)]) }}
                    {{ $t('s2.sub', [
                        'source' => $effort['source_label'] ?? 'Backlog',
                        'base' => $hours($effort['base_hours'] ?? 0),
                        'buffer' => (int) round(((float) ($effort['buffer'] ?? 1.15) - 1) * 100),
                        'billable' => $hours($effort['billable_hours'] ?? 0),
                        'hpp' => $effort['hours_per_point'] ?? 4,
                        'calibration' => ($effort['hours_per_point_source'] ?? 'settings') === 'plans'
                            ? $t('s2.calibration.plans')
                            : $t('s2.calibration.settings'),
                    ]) }}</p>
            </div>
        </div>

        @php
            $byRelease = is_array($effort['by_release'] ?? null) && $effort['by_release'] !== [];
            $groupRows = $byRelease ? $effort['by_release'] : (is_array($effort['by_epic'] ?? null) ? $effort['by_epic'] : []);
            $groupKey = $byRelease ? 'release' : 'epic';
            $groupMax = max(1.0, ...array_map(static fn (array $row): float => (float) $row['billable_hours'], $groupRows ?: [['billable_hours' => 1]]));
        @endphp

        @if ($groupRows !== [])
            <section class="card panel">
                <h4>{{ $t('s2.group.title.'.$groupKey) }}</h4>
                <p class="hint">{{ $t('s2.group.hint') }}</p>
                <div class="bars">
                    @foreach ($groupRows as $row)
                        <div class="bar-row" title="{{ $row['label'] }}: {{ $hours($row['billable_hours']) }} · {{ $money($row['labor']) }}">
                            <span class="bar-label">{{ $row['label'] }}<small>{{ $t('s2.row.delivered', ['done' => $row['done'], 'total' => $row['specs']]) }} · {{ $row['points'] }} {{ mb_strtolower($t('s2.th.points')) }}</small></span>
                            <div class="bar-track"><div class="bar-fill k-work" style="width: {{ min(100, (float) $row['billable_hours'] / $groupMax * 100) }}%"></div></div>
                            <span class="num">{{ $hours($row['billable_hours']) }} · {{ $money($row['labor']) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (is_array($effort['built'] ?? null))
            <section class="card panel" id="eco-built">
                <h4>{{ $t('s2.built.title') }}</h4>
                <p class="hint" style="margin:0">
                    {{ $t('s2.built.line', [
                        'specs' => $effort['built']['specs'] ?? 0,
                        'quoted' => $hours($effort['built']['quoted_hours'] ?? 0),
                        'build' => $effort['built']['build_display'] ?? '',
                    ]) }}
                    <a href="{{ route('larapilot.dashboard.usage') }}#actuals-panel">{{ $t('s2.built.link') }}</a>
                </p>
            </section>
        @endif

        <details class="card eco-fold" data-fold="hours">
            <summary>{{ $t('show.hours') }}</summary>
            <section class="panel">
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

    {{-- ============ THE MARKET ============ --}}
    <section class="eco-section" id="eco-market">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ ++$number }}</span>
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
                    <div class="table-wrap">
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
                    </div>
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
        @endif
    </section>

    {{-- ============ THE WORDS ============ --}}
    <section class="eco-section" id="eco-words">
        <div class="eco-section-head">
            <span class="eco-section-num">{{ ++$number }}</span>
            <div>
                <h3>{{ $t('words.title') }}</h3>
                <p>{{ $t('words.lead') }}</p>
            </div>
        </div>

        @php
            $words = [
                ['vat', [], $vat > 0 ? $money($vat) : $t('kpi.client.novat')],
                ['margin', [], $money($margin).' · '.$plain($quote['margin_pct'] ?? 0).'%'],
                ['net', [], $money($net)],
            ];

            if ($isSaas) {
                $words[] = ['fixed', [], $money2($bills)];
                $words[] = ['mrr', [], $money($saas['mrr_at_planning'] ?? 0).' · '.$money($saas['arr_at_planning'] ?? 0)];
                $words[] = ['churn', [
                    'pct' => $saas['churn_monthly_pct'] ?? 4,
                    'months' => ((float) ($saas['churn_monthly_pct'] ?? 4)) > 0 ? (int) round(100 / (float) ($saas['churn_monthly_pct'] ?? 4)) : 0,
                ], $plain($saas['churn_monthly_pct'] ?? 0).'%'];
                $words[] = ['contribution', [], $money2($left)];
                $words[] = ['breakeven', [], number_format($needBills)];
                $words[] = ['ltv', [], ($saas['ltv_cac'] ?? null) !== null ? $plain($saas['ltv_cac']).'×' : '—'];
            }

            $words[] = ['personmonths', [], $plain($effort['person_months'] ?? 0)];
        @endphp
        <dl class="card panel eco-words">
            @foreach ($words as [$word, $replace, $here])
                <div>
                    <dt>{{ $t('words.'.$word) }}</dt>
                    <dd>{{ $t('words.'.$word.'.def', $replace) }} <span class="eco-here">{{ $t('words.here', ['value' => $here]) }}</span></dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ============ INPUTS ============ --}}
    <details class="card panel eco-details" data-fold="inputs">
        <summary>{{ $t('inputs.profile.title') }}</summary>
        <div style="margin-top:14px">
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
    </details>

    <p class="disclaimer">{{ $disclaimer ?? '' }} {{ $t('footer.disclaimer', ['year' => $fiscal_year ?? 2026]) }}</p>
@endif
