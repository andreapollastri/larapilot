<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\TaxCatalog;
use Symfony\Component\Yaml\Yaml;

class EconomicsService
{
    public const PRODUCT_MODELS = ['auto', 'fixed', 'saas', 'ecommerce', 'package'];

    public function __construct(
        protected ConfigService $config,
        protected ChoicesService $choices,
        protected SpecService $specs,
        protected PlanService $plans,
        protected PrdService $prd,
        protected UsageService $usage,
    ) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['economics'] ?? '.larapilot/economics.yaml');
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $account = $this->accountMode();
        $country = TaxCatalog::defaultCountry();
        $regime = $account === 'NONE'
            ? null
            : TaxCatalog::defaultRegime($country, $account);

        return [
            'country' => $country,
            'regime' => $regime,
            'currency' => TaxCatalog::defaultCurrency($country),
            'hourly_rate' => $account === 'NONE' ? 55.0 : TaxCatalog::defaultHourlyRate($country, $account),
            'billable_days_per_year' => 220,
            'hours_per_day' => 6.0,
            'margin_target_pct' => $account === 'COMPANY' ? 35.0 : 30.0,
            'maintenance_annual_pct' => 15.0,
            'overhead_monthly' => $account === 'COMPANY' ? 800.0 : 250.0,
            'vat_registered' => null,
            'product_model' => 'auto',
            'saas' => [
                'price_monthly' => 29.0,
                'price_annual' => 290.0,
                'churn_monthly_pct' => 4.0,
                'conversion_pct' => 3.0,
                'trial_to_paid_pct' => 20.0,
                'target_customers' => 0,
                'growth_monthly_pct' => 12.0,
                'starting_customers' => 0,
                'infrastructure_monthly' => 0.0,
                'support_cost_per_customer_monthly' => 3.0,
                'payment_fee_pct' => 2.9,
                'cac' => 0.0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return $this->defaults() + [
                'configured' => false,
                'updated_at' => null,
            ];
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            return $this->defaults() + [
                'configured' => false,
                'updated_at' => null,
            ];
        }

        $defaults = $this->defaults();
        $saasDefaults = is_array($defaults['saas'] ?? null) ? $defaults['saas'] : [];
        $saas = array_replace($saasDefaults, is_array($parsed['saas'] ?? null) ? array_intersect_key($parsed['saas'], $saasDefaults) : []);

        $merged = array_replace($defaults, array_intersect_key($parsed, $defaults));
        $merged['saas'] = $saas;
        $merged['configured'] = true;
        $merged['updated_at'] = is_string($parsed['updated_at'] ?? null) ? $parsed['updated_at'] : null;

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $partial
     * @return array<string, mixed>
     */
    public function update(array $partial): array
    {
        $current = $this->read();
        unset($current['configured']);

        $defaults = $this->defaults();
        $saasDefaults = is_array($defaults['saas'] ?? null) ? $defaults['saas'] : [];

        if (isset($partial['saas']) && is_array($partial['saas'])) {
            $current['saas'] = array_replace(
                $saasDefaults,
                is_array($current['saas'] ?? null) ? $current['saas'] : [],
                array_intersect_key($partial['saas'], $saasDefaults)
            );
            unset($partial['saas']);
        }

        foreach ($partial as $key => $value) {
            if (! array_key_exists($key, $defaults) || $key === 'saas') {
                continue;
            }

            $current[$key] = $value;
        }

        $account = $this->accountMode();

        if (isset($current['country'])) {
            $current['country'] = TaxCatalog::normalizeCountry((string) $current['country']);
            TaxCatalog::country($current['country']);
            $current['currency'] = $current['currency'] ?: TaxCatalog::defaultCurrency($current['country']);
        }

        if (isset($current['regime']) && is_string($current['regime']) && $current['regime'] !== '' && $account !== 'NONE') {
            $allowed = array_keys(TaxCatalog::regimesFor((string) $current['country'], $account));
            $current['regime'] = TaxCatalog::normalizeRegime((string) $current['regime'], $allowed);
            TaxCatalog::regime((string) $current['country'], $account, (string) $current['regime']);
        }

        if (isset($current['product_model'])) {
            $model = strtolower(trim((string) $current['product_model']));
            if (! in_array($model, self::PRODUCT_MODELS, true)) {
                throw new \InvalidArgumentException('Invalid product_model. Allowed: '.implode(', ', self::PRODUCT_MODELS).'.');
            }
            $current['product_model'] = $model;
        }

        foreach (['hourly_rate', 'hours_per_day', 'margin_target_pct', 'maintenance_annual_pct', 'overhead_monthly'] as $numeric) {
            if (array_key_exists($numeric, $current)) {
                $current[$numeric] = max(0, (float) $current[$numeric]);
            }
        }

        if (array_key_exists('billable_days_per_year', $current)) {
            $current['billable_days_per_year'] = max(1, (int) $current['billable_days_per_year']);
        }

        $current['updated_at'] = (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);

        $directory = dirname($this->path());
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        AtomicFile::write(
            $this->path(),
            Yaml::dump($current, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        return $this->read();
    }

    public function accountMode(): string
    {
        $mode = strtoupper((string) ($this->config->settings()['account'] ?? 'NONE'));

        return in_array($mode, $this->config->allowedAccountModes(), true) ? $mode : 'NONE';
    }

    public function enabled(): bool
    {
        return $this->accountMode() !== 'NONE';
    }

    /**
     * Full computed Economics snapshot for dashboard, API, and CLI.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $account = $this->accountMode();
        $profile = $this->read();
        $inception = $this->inceptionContext();
        $effort = $this->effortModel($inception, $profile);

        $payload = [
            'enabled' => $account !== 'NONE',
            'account' => $account,
            'fiscal_year' => TaxCatalog::YEAR,
            'disclaimer' => 'Planning estimates from statutory '.$this->yearLabel().' rates. Not personalised tax advice.',
            'profile' => $profile,
            'inception' => $inception,
            'effort' => $effort,
            'path' => $this->config->relativePath($this->path()),
            'countries' => $this->countryOptions(),
        ];

        if ($account === 'NONE') {
            return $payload + [
                'quote' => null,
                'tax' => null,
                'alternate' => null,
                'payback' => null,
                'product' => ['model' => 'off'],
                'saas' => null,
                'forecast' => [],
            ];
        }

        $country = (string) ($profile['country'] ?? TaxCatalog::defaultCountry());
        $regimeId = (string) ($profile['regime'] ?: TaxCatalog::defaultRegime($country, $account));
        $regime = TaxCatalog::regime($country, $account, $regimeId);
        $countryMeta = TaxCatalog::country($country);

        $quote = $this->buildQuote($effort, $profile, $regime, $countryMeta);
        $tax = $this->applyTax((float) $quote['gross'], (float) $quote['overhead'], $profile, $regime, $countryMeta, $account);
        $quote['net_to_owner'] = $tax['net_to_owner'];
        $quote['effective_tax_pct'] = $tax['effective_rate_pct'];
        $quote['client_total'] = $quote['gross'] + $quote['vat'];

        $alternateAccount = $account === 'FREELANCE' ? 'COMPANY' : 'FREELANCE';
        $alternate = $this->alternateQuote($quote, $profile, $country, $alternateAccount, $countryMeta);

        $productModel = $this->resolveProductModel($profile, $inception);
        $saas = $productModel === 'saas'
            ? $this->saasModel($quote, $profile, $inception, $tax)
            : null;

        return $payload + [
            'country' => [
                'code' => $country,
                'name' => $countryMeta['name'],
                'currency' => $profile['currency'] ?: $countryMeta['currency'],
                'vat_rate' => $countryMeta['vat_rate'],
            ],
            'regime' => [
                'id' => $regime['id'],
                'label' => $regime['label'],
                'notes' => $regime['notes'] ?? null,
                'options' => $this->regimeOptions($country, $account),
            ],
            'quote' => $quote,
            'tax' => $tax,
            'alternate' => $alternate,
            'payback' => $this->payback($quote, $effort, $profile, $productModel, $saas),
            'product' => [
                'model' => $productModel,
                'label' => $this->productLabel($productModel),
            ],
            'saas' => $saas,
            'forecast' => $saas['forecast'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return $this->snapshot();
    }

    public function reportMarkdown(): string
    {
        $data = $this->snapshot();
        $currency = (string) ($data['country']['currency'] ?? $data['profile']['currency'] ?? 'EUR');

        $lines = [
            '# Larapilot Economics',
            '',
            '- Account: **'.($data['account'] ?? 'NONE').'**',
            '- Fiscal year: '.($data['fiscal_year'] ?? TaxCatalog::YEAR),
        ];

        if (! ($data['enabled'] ?? false)) {
            $lines[] = '';
            $lines[] = 'Account mode is NONE. Enable with `php artisan larapilot:settings-set --account=FREELANCE` or `--account=COMPANY`.';

            return implode("\n", $lines)."\n";
        }

        $lines[] = '- Country: **'.($data['country']['name'] ?? '').'** ('.($data['country']['code'] ?? '').')';
        $lines[] = '- Regime: **'.($data['regime']['label'] ?? '').'**';
        $lines[] = '- Product: **'.($data['product']['label'] ?? '').'**';
        $lines[] = '';
        $lines[] = '## Quote';
        $lines[] = '';
        $quote = is_array($data['quote'] ?? null) ? $data['quote'] : [];
        foreach ([
            'billable_hours' => 'Billable hours',
            'labor' => 'Labor',
            'overhead' => 'Overhead',
            'margin' => 'Margin',
            'gross' => 'Client price (ex VAT)',
            'vat' => 'VAT',
            'client_total' => 'Client total',
            'net_to_owner' => 'Net to owner',
            'maintenance_year' => 'Annual maintenance',
        ] as $key => $label) {
            $value = $quote[$key] ?? null;
            if ($value === null) {
                continue;
            }
            $formatted = in_array($key, ['billable_hours'], true)
                ? (string) $value
                : $this->money((float) $value, $currency);
            $lines[] = '- **'.$label.':** '.$formatted;
        }

        $tax = is_array($data['tax'] ?? null) ? $data['tax'] : [];
        if ($tax !== []) {
            $lines[] = '';
            $lines[] = '## Tax';
            $lines[] = '';
            $lines[] = '- Taxable base: '.$this->money((float) ($tax['taxable'] ?? 0), $currency);
            $lines[] = '- Income / corporate tax: '.$this->money((float) ($tax['income_tax'] ?? 0), $currency);
            $lines[] = '- Social contributions: '.$this->money((float) ($tax['social'] ?? 0), $currency);
            $lines[] = '- Local tax: '.$this->money((float) ($tax['local_tax'] ?? 0), $currency);
            $lines[] = '- Dividend / extraction: '.$this->money((float) ($tax['dividend_tax'] ?? 0), $currency);
            $lines[] = '- Compliance: '.$this->money((float) ($tax['compliance'] ?? 0), $currency);
            $lines[] = '- Effective rate: '.($tax['effective_rate_pct'] ?? 0).'%';
        }

        $saas = is_array($data['saas'] ?? null) ? $data['saas'] : [];
        if ($saas !== []) {
            $lines[] = '';
            $lines[] = '## SaaS';
            $lines[] = '';
            $lines[] = '- Price: '.$this->money((float) ($saas['price_monthly'] ?? 0), $currency).'/mo · '.$this->money((float) ($saas['price_annual'] ?? 0), $currency).'/yr';
            $lines[] = '- Break-even customers: '.($saas['break_even_customers'] ?? 0);
            $lines[] = '- Customers to recover build in 12 months: '.($saas['customers_to_recover_12m'] ?? 0);
            $lines[] = '- ARR at break-even: '.$this->money((float) ($saas['arr_at_break_even'] ?? 0), $currency);
            $lines[] = '- LTV: '.$this->money((float) ($saas['ltv'] ?? 0), $currency);
            $lines[] = '- LTV:CAC: '.($saas['ltv_cac'] ?? 'n/a');
        }

        $lines[] = '';
        $lines[] = '_'.($data['disclaimer'] ?? '').'_';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    protected function inceptionContext(): array
    {
        $choices = $this->choices->read();
        $prd = $this->prd->read() ?? '';

        return [
            'project_kind' => $this->stringOrNull($choices['project_kind'] ?? null),
            'website_type' => $this->stringOrNull($choices['website_type'] ?? null),
            'delivery_target' => $this->stringOrNull($choices['delivery_target'] ?? null),
            'budget_sensitivity' => $this->stringOrNull($choices['budget_sensitivity'] ?? null),
            'deploy_platform' => $this->stringOrNull($choices['deploy_platform'] ?? null),
            'frontend_topology' => $this->stringOrNull($choices['frontend_topology'] ?? null),
            'prd_excerpt' => $prd,
        ];
    }

    /**
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected function effortModel(array $inception, array $profile): array
    {
        $planHours = 0.0;
        $plannedTasks = 0;
        $storyPoints = 0;
        $specCount = 0;

        foreach ($this->specs->allSpecs() as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $specCount++;
            $storyPoints += max(0, (int) ($spec['points'] ?? 0));
            $code = (string) ($spec['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $plan = $this->plans->read($code);
            $tasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];

            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $plannedTasks++;
                $planHours += max(0.0, (float) ($task['estimate_hours'] ?? 0));
            }
        }

        $settings = $this->config->settings();
        $hoursPerPoint = match ($settings['effort'] ?? 'STANDARD') {
            'ECO' => 3.0,
            'MAX' => 5.5,
            default => 4.0,
        };

        $fromPoints = $storyPoints * $hoursPerPoint;
        $source = $planHours > 0 ? 'plan_hours' : ($storyPoints > 0 ? 'story_points' : 'heuristic');
        $baseHours = $planHours > 0 ? $planHours : ($fromPoints > 0 ? $fromPoints : $this->heuristicHours($inception));

        $deliveryMultiplier = $this->deliveryMultiplier((string) ($inception['delivery_target'] ?? ''));
        $kindMultiplier = $this->kindMultiplier((string) ($inception['project_kind'] ?? ''));
        $typeMultiplier = $this->typeMultiplier((string) ($inception['website_type'] ?? ''));
        $buffer = 1.15;

        $adjusted = $baseHours * $deliveryMultiplier * $kindMultiplier * $typeMultiplier * $buffer;
        $actualHours = (float) ($this->usage->summary()['total_hours'] ?? 0);

        return [
            'source' => $source,
            'spec_count' => $specCount,
            'story_points' => $storyPoints,
            'planned_tasks' => $plannedTasks,
            'plan_hours' => round($planHours, 1),
            'hours_from_points' => round($fromPoints, 1),
            'hours_per_point' => $hoursPerPoint,
            'heuristic_hours' => round($this->heuristicHours($inception), 1),
            'base_hours' => round($baseHours, 1),
            'delivery_multiplier' => $deliveryMultiplier,
            'kind_multiplier' => $kindMultiplier,
            'type_multiplier' => $typeMultiplier,
            'buffer' => $buffer,
            'billable_hours' => round($adjusted, 1),
            'calendar_months' => round($adjusted / max(1.0, ((float) $profile['hours_per_day']) * 20), 1),
            'actual_hours' => round($actualHours, 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $inception
     */
    protected function heuristicHours(array $inception): float
    {
        $kind = strtolower((string) ($inception['project_kind'] ?? 'application'));
        $target = strtolower((string) ($inception['delivery_target'] ?? 'mvp'));

        $base = match (true) {
            str_contains($kind, 'personal') => 40.0,
            str_contains($kind, 'package') => 80.0,
            str_contains($kind, 'website') => 90.0,
            default => 160.0,
        };

        $targetBoost = match (true) {
            str_contains($target, 'enterprise') => 2.4,
            str_contains($target, 'full') => 1.8,
            str_contains($target, 'v1') => 1.3,
            default => 1.0,
        };

        return $base * $targetBoost;
    }

    protected function deliveryMultiplier(string $target): float
    {
        $value = strtolower($target);

        return match (true) {
            str_contains($value, 'enterprise') => 2.8,
            str_contains($value, 'full') => 2.0,
            str_contains($value, 'v1') || str_contains($value, 'complete') => 1.45,
            default => 1.0,
        };
    }

    protected function kindMultiplier(string $kind): float
    {
        $value = strtolower($kind);

        return match (true) {
            str_contains($value, 'personal') => 0.75,
            str_contains($value, 'package') => 0.85,
            str_contains($value, 'website') => 1.0,
            default => 1.15,
        };
    }

    protected function typeMultiplier(string $type): float
    {
        $value = strtolower($type);

        return match (true) {
            str_contains($value, 'commerce') || str_contains($value, 'saas') => 1.25,
            str_contains($value, 'portal') => 1.15,
            str_contains($value, 'blog') || str_contains($value, 'showcase') => 0.85,
            default => 1.0,
        };
    }

    /**
     * @param  array<string, mixed>  $effort
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @return array<string, mixed>
     */
    protected function buildQuote(array $effort, array $profile, array $regime, array $country): array
    {
        $hours = (float) $effort['billable_hours'];
        $rate = (float) $profile['hourly_rate'];
        $labor = $hours * $rate;
        $months = max(0.25, (float) $effort['calendar_months']);
        $overhead = ((float) $profile['overhead_monthly'] + ((float) ($regime['compliance_annual'] ?? 0) / 12)) * $months;
        $direct = $labor + $overhead;
        $marginPct = (float) $profile['margin_target_pct'];
        $margin = $direct * ($marginPct / 100);
        $gross = $direct + $margin;

        $vatExempt = (bool) ($regime['vat_exempt'] ?? false);
        $vatRegistered = $profile['vat_registered'];
        if ($vatRegistered === null) {
            $vatRegistered = ! $vatExempt;
        }
        $vatRate = $vatRegistered && ! $vatExempt ? (float) ($country['vat_rate'] ?? 0) : 0.0;
        $vat = $gross * ($vatRate / 100);
        $maintenance = $gross * ((float) $profile['maintenance_annual_pct'] / 100);

        return [
            'hourly_rate' => round($rate, 2),
            'billable_hours' => round($hours, 1),
            'calendar_months' => round($months, 1),
            'labor' => round($labor, 2),
            'overhead' => round($overhead, 2),
            'direct' => round($direct, 2),
            'margin' => round($margin, 2),
            'margin_pct' => $marginPct,
            'gross' => round($gross, 2),
            'vat_rate' => $vatRate,
            'vat' => round($vat, 2),
            'vat_registered' => (bool) $vatRegistered,
            'maintenance_year' => round($maintenance, 2),
            'maintenance_monthly' => round($maintenance / 12, 2),
            'currency' => (string) ($profile['currency'] ?: ($country['currency'] ?? 'EUR')),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @return array<string, mixed>
     */
    protected function applyTax(float $revenue, float $operatingCosts, array $profile, array $regime, array $country, string $account): array
    {
        $compliance = (float) ($regime['compliance_annual'] ?? 0);
        $months = max(0.25, $revenue / max(1.0, (float) $profile['hourly_rate'] * (float) $profile['hours_per_day'] * 20));
        $complianceAlloc = $compliance * min(1.0, $months / 12);
        $costs = $operatingCosts + $complianceAlloc;
        $profit = max(0.0, $revenue - $costs);

        $coefficient = (float) ($regime['revenue_coefficient'] ?? 1.0);
        $cap = isset($regime['revenue_cap']) ? (float) $regime['revenue_cap'] : null;
        $cappedRevenue = $cap !== null ? min($revenue, $cap) : $revenue;

        $model = (string) ($regime['model'] ?? 'flat');
        $taxable = match ($model) {
            'flat' => $cappedRevenue * $coefficient,
            'progressive' => $profit,
            default => $profit,
        };

        $incomeTax = 0.0;
        if ($model === 'progressive') {
            $incomeTax = $this->progressiveTax($taxable, is_array($regime['brackets'] ?? null) ? $regime['brackets'] : []);
        } elseif ($model === 'corporate') {
            $incomeTax = $this->corporateTax($profit, $regime);
        } else {
            $incomeTax = $taxable * (float) ($regime['income_tax_rate'] ?? 0);
        }

        $incomeTax += $incomeTax * (float) ($regime['surtax_rate'] ?? 0);
        $incomeTax += $taxable * (float) ($regime['additional_rate'] ?? 0);

        $socialBase = match ((string) ($regime['social_base'] ?? 'profit')) {
            'revenue' => $cappedRevenue,
            'taxable' => $taxable,
            default => $profit,
        };
        $social = $socialBase * (float) ($regime['social_rate'] ?? 0) + (float) ($regime['social_fixed_annual'] ?? 0) * min(1.0, $months / 12);

        $localTax = $profit * (float) ($regime['local_tax_rate'] ?? 0);

        $afterEntity = max(0.0, $profit - $incomeTax - $localTax - ($model === 'corporate' ? 0.0 : $social));
        if ($model !== 'corporate') {
            $afterEntity = max(0.0, $revenue - $costs - $incomeTax - $social - $localTax);
        }

        $dividend = $model === 'corporate' ? $afterEntity * (float) ($regime['dividend_rate'] ?? 0) : 0.0;
        $net = $model === 'corporate' ? $afterEntity - $dividend : $afterEntity;

        if ($model === 'corporate') {
            $net -= $social;
        }

        $totalTax = $incomeTax + $social + $localTax + $dividend + $complianceAlloc;
        $effective = $revenue > 0 ? round($totalTax / $revenue * 100, 1) : 0.0;

        return [
            'model' => $model,
            'account' => $account,
            'country' => $country['code'] ?? null,
            'regime' => $regime['id'] ?? null,
            'revenue' => round($revenue, 2),
            'costs' => round($costs, 2),
            'profit' => round($profit, 2),
            'taxable' => round($taxable, 2),
            'income_tax' => round($incomeTax, 2),
            'social' => round($social, 2),
            'local_tax' => round($localTax, 2),
            'dividend_tax' => round($dividend, 2),
            'compliance' => round($complianceAlloc, 2),
            'total_tax' => round($totalTax, 2),
            'net_to_owner' => round(max(0.0, $net), 2),
            'effective_rate_pct' => $effective,
            'over_cap' => $cap !== null && $revenue > $cap,
            'revenue_cap' => $cap,
            'notes' => $regime['notes'] ?? null,
        ];
    }

    /**
     * @param  list<array{up_to: float|int|null, rate: float}>  $brackets
     */
    protected function progressiveTax(float $income, array $brackets): float
    {
        if ($income <= 0 || $brackets === []) {
            return 0.0;
        }

        $tax = 0.0;
        $previous = 0.0;

        foreach ($brackets as $bracket) {
            $limit = $bracket['up_to'];
            $rate = (float) ($bracket['rate'] ?? 0);
            $upper = $limit === null ? $income : min($income, (float) $limit);
            $slice = max(0.0, $upper - $previous);

            if ($slice <= 0) {
                continue;
            }

            $tax += $slice * $rate;
            $previous = $limit === null ? $income : (float) $limit;

            if ($limit !== null && $income <= (float) $limit) {
                break;
            }
        }

        return $tax;
    }

    /**
     * @param  array<string, mixed>  $regime
     */
    protected function corporateTax(float $profit, array $regime): float
    {
        if ($profit <= 0) {
            return 0.0;
        }

        $smallUpTo = (float) ($regime['small_profit_up_to'] ?? 0);
        $smallRate = (float) ($regime['small_profit_rate'] ?? 0);
        $mainRate = (float) ($regime['income_tax_rate'] ?? 0);

        if ($smallUpTo > 0 && $smallRate > 0) {
            $lower = min($profit, $smallUpTo);

            return ($lower * $smallRate) + (max(0.0, $profit - $smallUpTo) * $mainRate);
        }

        return $profit * $mainRate;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $country
     * @return array<string, mixed>
     */
    protected function alternateQuote(array $quote, array $profile, string $countryCode, string $account, array $country): array
    {
        $regimeId = TaxCatalog::defaultRegime($countryCode, $account);
        $regime = TaxCatalog::regime($countryCode, $account, $regimeId);
        $tax = $this->applyTax((float) $quote['gross'], (float) $quote['overhead'], $profile, $regime, $country, $account);

        return [
            'account' => $account,
            'regime' => $regime['label'],
            'net_to_owner' => $tax['net_to_owner'],
            'effective_rate_pct' => $tax['effective_rate_pct'],
            'total_tax' => $tax['total_tax'],
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $inception
     */
    protected function resolveProductModel(array $profile, array $inception): string
    {
        $explicit = strtolower((string) ($profile['product_model'] ?? 'auto'));

        if (in_array($explicit, ['fixed', 'saas', 'ecommerce', 'package'], true)) {
            return $explicit;
        }

        $haystack = strtolower(implode(' ', array_filter([
            (string) ($inception['project_kind'] ?? ''),
            (string) ($inception['website_type'] ?? ''),
            (string) ($inception['delivery_target'] ?? ''),
            (string) ($inception['prd_excerpt'] ?? ''),
        ])));

        if (str_contains($haystack, 'saas') || str_contains($haystack, 'subscription') || preg_match('/\b(mrr|arr)\b/', $haystack) === 1) {
            return 'saas';
        }

        if (str_contains($haystack, 'e-commerce') || str_contains($haystack, 'ecommerce') || str_contains($haystack, 'commerce')) {
            return 'ecommerce';
        }

        if (str_contains($haystack, 'package') || str_contains($haystack, 'composer')) {
            return 'package';
        }

        return 'fixed';
    }

    protected function productLabel(string $model): string
    {
        return match ($model) {
            'saas' => 'SaaS / subscription',
            'ecommerce' => 'E-commerce',
            'package' => 'Package / licensed product',
            'fixed' => 'Fixed-price delivery',
            default => 'Not configured',
        };
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $effort
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>|null  $saas
     * @return array<string, mixed>
     */
    protected function payback(array $quote, array $effort, array $profile, string $productModel, ?array $saas): array
    {
        $gross = (float) $quote['gross'];
        $net = (float) ($quote['net_to_owner'] ?? 0);
        $hours = (float) $effort['billable_hours'];
        $yearDays = max(1, (int) $profile['billable_days_per_year']);
        $hoursPerDay = max(1.0, (float) $profile['hours_per_day']);
        $annualCapacity = $yearDays * $hoursPerDay;
        $utilizationPct = $annualCapacity > 0 ? round($hours / $annualCapacity * 100, 1) : 0.0;

        $projectsPerYear = max(1, (int) floor($annualCapacity / max(1.0, $hours)));

        $payload = [
            'model' => $productModel,
            'utilization_pct' => min(100.0, $utilizationPct),
            'capacity_hours_year' => $annualCapacity,
            'projects_per_year' => $projectsPerYear,
            'annual_gross_at_capacity' => round($projectsPerYear * $gross, 2),
            'annual_net_at_capacity' => round($projectsPerYear * $net, 2),
            'effective_hourly_net' => $hours > 0 ? round($net / $hours, 2) : 0.0,
            'maintenance_year' => $quote['maintenance_year'],
        ];

        if ($saas !== null) {
            $payload['months_to_recover_at_break_even'] = $saas['months_to_recover_at_break_even'];
            $payload['customers_to_recover_12m'] = $saas['customers_to_recover_12m'];
            $payload['customers_to_recover_24m'] = $saas['customers_to_recover_24m'];
        }

        if ($productModel === 'ecommerce') {
            $aov = 80.0;
            $takeRate = 0.15;
            $monthlyToRecover12 = $gross / 12;
            $payload['orders_per_month_to_recover_12m'] = (int) ceil($monthlyToRecover12 / ($aov * $takeRate));
            $payload['assumed_aov'] = $aov;
            $payload['assumed_take_rate_pct'] = $takeRate * 100;
        }

        if ($productModel === 'package') {
            $license = max(49.0, round($gross / 80, 0));
            $payload['licenses_to_recover'] = $license > 0 ? (int) ceil($gross / $license) : 0;
            $payload['suggested_license_price'] = $license;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>  $tax
     * @return array<string, mixed>
     */
    protected function saasModel(array $quote, array $profile, array $inception, array $tax): array
    {
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
        $priceMonthly = max(1.0, (float) ($saas['price_monthly'] ?? 29));
        $priceAnnual = (float) ($saas['price_annual'] ?? 0);
        if ($priceAnnual <= 0) {
            $priceAnnual = $priceMonthly * 10;
        }

        $churn = max(0.5, (float) ($saas['churn_monthly_pct'] ?? 4)) / 100;
        $fee = max(0.0, (float) ($saas['payment_fee_pct'] ?? 2.9)) / 100;
        $support = max(0.0, (float) ($saas['support_cost_per_customer_monthly'] ?? 3));
        $infra = (float) ($saas['infrastructure_monthly'] ?? 0);
        if ($infra <= 0) {
            $infra = $this->infraFromDeploy((string) ($inception['deploy_platform'] ?? ''));
        }

        $fixed = $infra + ((float) $quote['maintenance_monthly']) + ((float) $profile['overhead_monthly'] * 0.35);
        $contribution = ($priceMonthly * (1 - $fee)) - $support;
        $breakEven = $contribution > 0 ? (int) ceil($fixed / $contribution) : 0;

        $investment = (float) $quote['gross'];
        $customers12 = $contribution > 0 ? (int) ceil(($investment / 12 + $fixed) / $contribution) : 0;
        $customers18 = $contribution > 0 ? (int) ceil(($investment / 18 + $fixed) / $contribution) : 0;
        $customers24 = $contribution > 0 ? (int) ceil(($investment / 24 + $fixed) / $contribution) : 0;

        $target = (int) ($saas['target_customers'] ?? 0);
        $planningCustomers = $target > 0 ? $target : max($breakEven, $customers12, 25);

        $ltv = $churn > 0 ? $priceMonthly / $churn : $priceMonthly * 24;
        $cac = (float) ($saas['cac'] ?? 0);
        if ($cac <= 0) {
            $cac = round($ltv * 0.28, 2);
        }

        $arrBreakEven = $breakEven * $priceMonthly * 12;
        $arrTarget = $planningCustomers * $priceMonthly * 12;
        $monthlyProfitAtTarget = ($planningCustomers * $contribution) - $fixed;
        $monthsToRecover = $monthlyProfitAtTarget > 0
            ? (int) ceil($investment / $monthlyProfitAtTarget)
            : null;

        $growth = max(0.0, (float) ($saas['growth_monthly_pct'] ?? 12)) / 100;
        $start = max(0, (int) ($saas['starting_customers'] ?? 0));
        $forecast = $this->forecastSaaS($start, $planningCustomers, $growth, $churn, $priceMonthly, $contribution, $fixed, $investment, $tax);

        return [
            'price_monthly' => round($priceMonthly, 2),
            'price_annual' => round($priceAnnual, 2),
            'annual_discount_pct' => $priceMonthly > 0 ? round((1 - ($priceAnnual / ($priceMonthly * 12))) * 100, 1) : 0,
            'churn_monthly_pct' => round($churn * 100, 2),
            'payment_fee_pct' => round($fee * 100, 2),
            'support_per_customer' => round($support, 2),
            'infrastructure_monthly' => round($infra, 2),
            'fixed_monthly' => round($fixed, 2),
            'contribution_per_customer' => round($contribution, 2),
            'gross_margin_pct' => $priceMonthly > 0 ? round($contribution / $priceMonthly * 100, 1) : 0,
            'break_even_customers' => $breakEven,
            'customers_to_recover_12m' => $customers12,
            'customers_to_recover_18m' => $customers18,
            'customers_to_recover_24m' => $customers24,
            'planning_customers' => $planningCustomers,
            'target_customers' => $target,
            'arr_at_break_even' => round($arrBreakEven, 2),
            'arr_at_planning' => round($arrTarget, 2),
            'mrr_at_planning' => round($planningCustomers * $priceMonthly, 2),
            'monthly_profit_at_planning' => round($monthlyProfitAtTarget, 2),
            'ltv' => round($ltv, 2),
            'cac' => round($cac, 2),
            'ltv_cac' => $cac > 0 ? round($ltv / $cac, 2) : null,
            'months_to_recover_at_planning' => $monthsToRecover,
            'months_to_recover_at_break_even' => $breakEven > 0 && (($breakEven * $contribution) - $fixed) > 0
                ? (int) ceil($investment / max(0.01, ($breakEven * $contribution) - $fixed))
                : null,
            'conversion_pct' => (float) ($saas['conversion_pct'] ?? 3),
            'trial_to_paid_pct' => (float) ($saas['trial_to_paid_pct'] ?? 20),
            'leads_for_planning' => ((float) ($saas['conversion_pct'] ?? 3)) > 0
                ? (int) ceil($planningCustomers / (((float) ($saas['conversion_pct'] ?? 3)) / 100))
                : 0,
            'server_notes' => $this->serverNotes((string) ($inception['deploy_platform'] ?? ''), $infra, $planningCustomers),
            'forecast' => $forecast,
        ];
    }

    /**
     * @param  array<string, mixed>  $tax
     * @return list<array<string, mixed>>
     */
    protected function forecastSaaS(
        int $start,
        int $target,
        float $growth,
        float $churn,
        float $price,
        float $contribution,
        float $fixed,
        float $investment,
        array $tax,
    ): array {
        $customers = (float) $start;
        $cumulative = -$investment;
        $rows = [];
        $effective = ((float) ($tax['effective_rate_pct'] ?? 30)) / 100;

        for ($month = 1; $month <= 36; $month++) {
            if ($customers < $target) {
                $customers = $customers <= 0
                    ? max(2.0, $target * 0.08)
                    : $customers * (1 + $growth);
                $customers = min($customers, (float) $target * 1.4);
            } else {
                $customers = $customers * (1 + max(0.02, $growth * 0.35));
            }

            $customers = $customers * (1 - $churn);
            $count = max(0, (int) round($customers));
            $mrr = $count * $price;
            $profit = ($count * $contribution) - $fixed;
            $net = $profit * (1 - $effective);
            $cumulative += $net;

            $rows[] = [
                'month' => $month,
                'customers' => $count,
                'mrr' => round($mrr, 2),
                'arr' => round($mrr * 12, 2),
                'profit' => round($profit, 2),
                'net' => round($net, 2),
                'cumulative' => round($cumulative, 2),
                'recovered' => $cumulative >= 0,
            ];
        }

        return $rows;
    }

    protected function infraFromDeploy(string $platform): float
    {
        $value = strtolower($platform);

        return match (true) {
            str_contains($value, 'vapor') => 55.0,
            str_contains($value, 'cloud') && str_contains($value, 'laravel') => 28.0,
            str_contains($value, 'forge') => 24.0,
            str_contains($value, 'kubernetes') || str_contains($value, 'k8s') => 160.0,
            str_contains($value, 'aws') || str_contains($value, 'azure') || str_contains($value, 'gcp') => 95.0,
            str_contains($value, 'hetzner') || str_contains($value, 'digitalocean') || str_contains($value, 'vps') => 18.0,
            str_contains($value, 'shared') || str_contains($value, 'cpanel') => 8.0,
            default => 35.0,
        };
    }

    /**
     * @return list<string>
     */
    protected function serverNotes(string $platform, float $infra, int $customers): array
    {
        $scale = $customers > 200 ? 'Plan a replica / queue worker and object storage once you pass ~200 paying accounts.' : 'A single app + managed DB is enough until ~200 paying accounts.';
        $label = trim($platform) !== '' ? $platform : 'generic VPS / PaaS';

        return [
            'Baseline hosting for '.$label.': ~'.number_format($infra, 0).'/mo (app + database + backups).',
            $scale,
            'Add ~€8–15/mo per extra 100k monthly page views, and a staging clone (~50% of prod) before public launch.',
            'Payment fees (Stripe-like) are already in the contribution margin at the configured rate.',
        ];
    }

    /**
     * @return list<array{code: string, name: string, currency: string}>
     */
    protected function countryOptions(): array
    {
        $options = [];

        foreach (TaxCatalog::countries() as $code => $meta) {
            $options[] = [
                'code' => $code,
                'name' => (string) ($meta['name'] ?? $code),
                'currency' => (string) ($meta['currency'] ?? 'EUR'),
            ];
        }

        return $options;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    protected function regimeOptions(string $country, string $account): array
    {
        $options = [];

        foreach (TaxCatalog::regimesFor($country, $account) as $id => $regime) {
            $options[] = [
                'id' => $id,
                'label' => (string) ($regime['label'] ?? $id),
            ];
        }

        return $options;
    }

    protected function yearLabel(): string
    {
        return (string) TaxCatalog::YEAR;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function money(float $amount, string $currency): string
    {
        $formatted = number_format($amount, 0, '.', ',');

        return $currency.' '.$formatted;
    }
}
