<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\ArtifactLanguage;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\TaxCatalog;
use Larapilot\Support\TaxEngine;
use Symfony\Component\Yaml\Yaml;

class EconomicsService
{
    public const PRODUCT_MODELS = ['auto', 'fixed', 'saas', 'ecommerce', 'package'];

    /**
     * PM / QA buffer applied on top of every effort figure.
     */
    public const PM_QA_BUFFER = 1.15;

    /**
     * Size assumed for a spec that carries neither a plan nor story points.
     */
    public const DEFAULT_SPEC_POINTS = 3;

    /**
     * Floor for a project with an empty backlog, before scope multipliers.
     */
    public const HEURISTIC_BASE_HOURS = 100.0;

    /**
     * Per-spec effort rows kept in the snapshot.
     */
    protected const MAX_BREAKDOWN_ROWS = 200;

    public function __construct(
        protected ConfigService $config,
        protected ChoicesService $choices,
        protected SpecService $specs,
        protected PlanService $plans,
        protected PrdService $prd,
        protected UsageService $usage,
        protected EconomicsQuoteWriter $quoteWriter,
    ) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['economics'] ?? '.larapilot/economics.yaml');
    }

    public function snapshotPath(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['economics_snapshot'] ?? '.larapilot/economics.snapshot.yaml');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readStoredSnapshot(): ?array
    {
        $path = $this->snapshotPath();

        if (! is_file($path)) {
            return null;
        }

        $parsed = Yaml::parseFile($path);

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * Fingerprint of every input the quote depends on: backlog, plans, PRD,
     * inception answers, usage ledger, profile, and project settings.
     *
     * The dashboard, the API, and `economics-show` always compute live; this is
     * what lets a mutating command notice the stored snapshot fell behind.
     */
    public function inputsFingerprint(): string
    {
        $config = $this->config->resolve();
        $parts = [];

        $files = [
            'config' => $this->config->configPath(),
            'economics' => $this->path(),
            'backlog' => $this->config->absolutePath($config['file']['backlog'] ?? '.larapilot/backlog.yaml'),
            'choices' => $this->config->absolutePath($config['paths']['choices'] ?? '.larapilot/choices.yaml'),
            'prd' => $this->prd->path(),
            'usage' => $this->usage->ledgerPath(),
        ];

        foreach ($files as $label => $path) {
            $parts[] = $label.':'.$this->fileStamp($path);
        }

        $planning = rtrim($this->config->absolutePath($config['file']['planning'] ?? '.larapilot/plans/'), '/\\');

        foreach (glob($planning.DIRECTORY_SEPARATOR.'*-plan.yaml') ?: [] as $plan) {
            $parts[] = 'plan:'.basename($plan).':'.$this->fileStamp($plan);
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 32);
    }

    /**
     * Whether the stored snapshot predates the current backlog / plans / PRD.
     */
    public function isStale(): bool
    {
        $stored = $this->readStoredSnapshot();

        if ($stored === null) {
            return true;
        }

        return (string) ($stored['inputs'] ?? '') !== $this->inputsFingerprint();
    }

    /**
     * Recompute and persist the snapshot when an input changed. Called after
     * every command that touches specs, plans, the PRD, inception, or settings,
     * so `.larapilot/economics.snapshot.yaml` tracks the backlog by itself.
     */
    public function refreshIfStale(): bool
    {
        if (! $this->enabled() || ! $this->isStale()) {
            return false;
        }

        $this->snapshot();

        return true;
    }

    protected function fileStamp(string $path): string
    {
        if (! is_file($path)) {
            return '0';
        }

        return ((int) filemtime($path)).'-'.((int) filesize($path));
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
            'vat_mode' => 'domestic',
            'owner_working' => $account === 'COMPANY' ? true : null,
            'extraction' => 'auto',
            'product_model' => 'auto',
            'saas' => [
                'price_monthly' => 29.0,
                'price_annual' => 0.0,
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
            $current['saas'] = $this->normalizeSaas(array_replace(
                $saasDefaults,
                is_array($current['saas'] ?? null) ? $current['saas'] : [],
                array_intersect_key($partial['saas'], $saasDefaults)
            ));
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
            $previousCountry = TaxCatalog::normalizeCountry((string) ($this->read()['country'] ?? $current['country']));
            $current['country'] = TaxCatalog::normalizeCountry((string) $current['country']);
            TaxCatalog::country($current['country']);
            $current['currency'] = $current['currency'] ?: TaxCatalog::defaultCurrency($current['country']);

            // Moving the tax residency leaves the old country's regime behind:
            // fall back to the new country's default unless the caller named one.
            if ($account !== 'NONE' && $previousCountry !== $current['country'] && ! isset($partial['regime'])) {
                $allowed = array_keys(TaxCatalog::regimesFor((string) $current['country'], $account));

                if (! in_array((string) ($current['regime'] ?? ''), $allowed, true)) {
                    $current['regime'] = TaxCatalog::defaultRegime((string) $current['country'], $account);
                }
            }
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

        if (isset($current['vat_mode'])) {
            $mode = strtolower(trim((string) $current['vat_mode']));
            if (! in_array($mode, ['domestic', 'eu_b2b'], true)) {
                throw new \InvalidArgumentException('Invalid vat_mode. Allowed: domestic, eu_b2b.');
            }
            $current['vat_mode'] = $mode;
        }

        if (isset($current['extraction'])) {
            $extraction = strtolower(trim((string) $current['extraction']));
            if (! in_array($extraction, ['auto', 'dividends', 'mixed'], true)) {
                throw new \InvalidArgumentException('Invalid extraction. Allowed: auto, dividends, mixed.');
            }
            $current['extraction'] = $extraction;
        }

        if (array_key_exists('owner_working', $current) && $current['owner_working'] !== null) {
            $current['owner_working'] = (bool) $current['owner_working'];
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

    /**
     * Subscription inputs are money and counts — never strings, never negative.
     *
     * @param  array<string, mixed>  $saas
     * @return array<string, mixed>
     */
    protected function normalizeSaas(array $saas): array
    {
        foreach ($saas as $key => $value) {
            $saas[$key] = in_array($key, ['target_customers', 'starting_customers'], true)
                ? max(0, (int) $value)
                : max(0.0, round((float) $value, 2));
        }

        return $saas;
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
            'inputs' => $this->inputsFingerprint(),
        ];

        if ($account === 'NONE') {
            return $payload + [
                'quote' => null,
                'tax' => null,
                'alternate' => null,
                'scenarios' => [],
                'sales' => null,
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
        $tax = $this->applyTax(
            (float) $quote['gross'],
            (float) $quote['overhead_operating'],
            $profile,
            $regime,
            $countryMeta,
            $account,
            (float) $quote['calendar_months']
        );
        $quote['net_to_owner'] = $tax['net_to_owner'];
        $quote['effective_tax_pct'] = $tax['effective_rate_pct'];
        $quote['client_total'] = $quote['gross'] + $quote['vat'];

        $alternateAccount = $account === 'FREELANCE' ? 'COMPANY' : 'FREELANCE';
        $alternate = $this->alternateQuote($quote, $profile, $country, $alternateAccount, $countryMeta);
        $scenarios = $this->taxScenarios($quote, $profile, $regime, $countryMeta, $account);

        $productModel = $this->resolveProductModel($profile, $inception);
        $saas = $this->saasModel($quote, $profile, $inception, $tax);
        $sales = $this->salesEstimates($quote, $profile, $inception, $tax, $productModel, $saas);

        $computedAt = (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);
        $result = $payload + [
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
            'scenarios' => $scenarios,
            'sales' => $sales,
            'payback' => $this->payback($quote, $effort, $profile, $productModel, $saas),
            'product' => [
                'model' => $productModel,
                'label' => $this->productLabel($productModel),
            ],
            'saas' => $saas,
            'forecast' => $saas['forecast'] ?? [],
            'quote_document' => $this->quoteMeta(),
            'snapshot_path' => $this->config->relativePath($this->snapshotPath()),
            'snapshot_saved_at' => $computedAt,
        ];

        $this->writeSnapshot($result, $computedAt);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function writeSnapshot(array $snapshot, string $computedAt): void
    {
        $stored = $this->normalizeSnapshotForStorage($snapshot);
        $stored['computed_at'] = $computedAt;

        $directory = dirname($this->snapshotPath());
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        AtomicFile::write(
            $this->snapshotPath(),
            Yaml::dump($stored, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function normalizeSnapshotForStorage(array $snapshot): array
    {
        unset($snapshot['countries']);

        if (isset($snapshot['regime']) && is_array($snapshot['regime'])) {
            unset($snapshot['regime']['options']);
        }

        if (isset($snapshot['inception']['prd_excerpt']) && is_string($snapshot['inception']['prd_excerpt'])) {
            $excerpt = trim($snapshot['inception']['prd_excerpt']);
            if (strlen($excerpt) > 400) {
                $snapshot['inception']['prd_excerpt'] = substr($excerpt, 0, 400).'…';
            }
        }

        unset($snapshot['snapshot_path'], $snapshot['snapshot_saved_at']);

        return $snapshot;
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
            if ((float) ($tax['personal_tax'] ?? 0) > 0) {
                $lines[] = '- Personal income tax: '.$this->money((float) $tax['personal_tax'], $currency);
            }
            $lines[] = '- Dividend / extraction: '.$this->money((float) ($tax['dividend_tax'] ?? 0), $currency);
            if ((float) ($tax['legal_reserve'] ?? 0) > 0) {
                $lines[] = '- Legal reserve (retained): '.$this->money((float) $tax['legal_reserve'], $currency);
            }
            $lines[] = '- Compliance: '.$this->money((float) ($tax['compliance'] ?? 0), $currency);
            $lines[] = '- **Total withheld (tax + contributions + compliance + reserve):** '.$this->money((float) ($tax['total_withheld'] ?? 0), $currency);
            $lines[] = '- Effective tax rate: '.($tax['effective_rate_pct'] ?? 0).'% · withheld '.($tax['withheld_rate_pct'] ?? 0).'% of revenue';

            if (! empty($tax['loss'])) {
                $lines[] = '- **This price does not cover its own costs and taxes — net to owner is negative.**';
            }

            $extraction = is_array($tax['extraction'] ?? null) ? $tax['extraction'] : [];
            if ($extraction !== []) {
                $lines[] = '- Extraction: '.($extraction['method'] ?? 'auto');
                if ((float) ($extraction['director_gross'] ?? 0) > 0) {
                    $lines[] = '- Director pay (gross): '.$this->money((float) $extraction['director_gross'], $currency);
                    $lines[] = '- Director pay (net): '.$this->money((float) ($extraction['director_net'] ?? 0), $currency);
                }
                if ((float) ($extraction['dividends_net'] ?? 0) > 0) {
                    $lines[] = '- Dividends (net): '.$this->money((float) $extraction['dividends_net'], $currency);
                }
            }

            $assumptions = is_array($tax['assumptions'] ?? null) ? $tax['assumptions'] : [];
            if ($assumptions !== []) {
                $lines[] = '';
                $lines[] = '## Assumptions';
                $lines[] = '';
                foreach ($assumptions as $assumption) {
                    $lines[] = '- '.$assumption;
                }
            }

            if (! empty($tax['over_cap'])) {
                $lines[] = '';
                $lines[] = 'Revenue exceeds the regime ceiling of '.$this->money((float) ($tax['revenue_cap'] ?? 0), $currency)
                    .(! empty($tax['forced_exit']) ? ' — computed under the exit regime.' : ' — you must leave this regime next year.');
            }
        }

        $sales = is_array($data['sales'] ?? null) ? $data['sales'] : [];
        if ($sales !== []) {
            $lines[] = '';
            $lines[] = '## Sales estimates';
            $lines[] = '';
            $oneShot = is_array($sales['one_shot'] ?? null) ? $sales['one_shot'] : [];
            if ($oneShot !== []) {
                $lines[] = '### One-shot';
                $lines[] = '';
                $lines[] = '- Client price (ex VAT): '.$this->money((float) ($oneShot['client_price_ex_vat'] ?? 0), $currency);
                $lines[] = '- Net to owner: '.$this->money((float) ($oneShot['net_to_owner'] ?? 0), $currency);
                $lines[] = '- Maintenance / year: '.$this->money((float) ($oneShot['maintenance_annual'] ?? 0), $currency);
                $lines[] = '- License units to recover build: '.($oneShot['units_to_recover_build'] ?? 0);
            }
            $saasSale = is_array($sales['saas'] ?? null) ? $sales['saas'] : [];
            if ($saasSale !== []) {
                $lines[] = '';
                $lines[] = '### SaaS critical mass';
                $lines[] = '';
                $lines[] = '- Price: '.$this->money((float) ($saasSale['price_monthly'] ?? 0), $currency).'/mo';
                $lines[] = '- Critical mass customers: '.($saasSale['critical_mass_customers'] ?? 0);
                $lines[] = '- ARR at critical mass: '.$this->money((float) ($saasSale['critical_mass_arr'] ?? 0), $currency);
                $lines[] = '- Monthly margin at critical mass: '.$this->money((float) ($saasSale['monthly_margin_at_critical_mass'] ?? 0), $currency);
                $lines[] = '- Customers to recover build in 12 months: '.($saasSale['customers_to_recover_build_12m'] ?? 0);
            }
        }

        $scenarios = is_array($data['scenarios'] ?? null) ? $data['scenarios'] : [];
        if ($scenarios !== []) {
            $lines[] = '';
            $lines[] = '## Tax scenarios';
            $lines[] = '';
            foreach ($scenarios as $scenario) {
                $lines[] = '- **'.($scenario['label'] ?? '').'**: net '.$this->money((float) ($scenario['net_to_owner'] ?? 0), $currency)
                    .' · '.($scenario['effective_rate_pct'] ?? 0).'% effective';
            }
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

    public function quotePath(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['economics_quote'] ?? '.larapilot/docs/quote.md');
    }

    /**
     * The quote document written by `/larapilot-economics` — any language the
     * PRD is in, not just the built-in template catalogue.
     *
     * @return array{content: string, lang: string|null, generated_at: string|null, inputs: string|null, stale: bool, path: string}|null
     */
    public function readStoredQuote(): ?array
    {
        $path = $this->quotePath();

        if (! is_file($path)) {
            return null;
        }

        $content = (string) file_get_contents($path);

        if (trim($content) === '') {
            return null;
        }

        $meta = $this->quoteFrontMatter($content);
        $inputs = $meta['inputs'] ?? null;

        return [
            'content' => $content,
            'lang' => $meta['lang'] ?? null,
            'generated_at' => $meta['generated_at'] ?? null,
            'inputs' => $inputs,
            'stale' => $inputs === null || $inputs !== $this->inputsFingerprint(),
            'path' => $this->config->relativePath($path),
        ];
    }

    /**
     * Persist an agent-written commercial quote. Larapilot stamps the language
     * and the input fingerprint it was written against so the dashboard can say
     * when the backlog moved on.
     *
     * @return array<string, mixed>
     */
    public function writeQuote(string $content, ?string $lang = null): array
    {
        $body = trim($content);

        if ($body === '') {
            throw new \InvalidArgumentException('Quote content is empty.');
        }

        $language = ArtifactLanguage::normalizeTag($lang);
        $stamps = [
            'lang' => $language ?? $this->quoteLanguage(),
            'generated_at' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
            'inputs' => $this->inputsFingerprint(),
        ];

        $document = $this->stampQuoteFrontMatter($body, $stamps);

        $directory = dirname($this->quotePath());
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        AtomicFile::write($this->quotePath(), $document);

        return [
            'path' => $this->config->relativePath($this->quotePath()),
            'bytes' => strlen($document),
            'lang' => $stamps['lang'],
            'generated_at' => $stamps['generated_at'],
            'inputs' => $stamps['inputs'],
            'filename' => $this->quoteFilename(),
        ];
    }

    /**
     * Client-facing commercial proposal. The stored document wins; the built-in
     * template (en/it/es/fr) renders the download when no one wrote one yet.
     * Internal tax figures stay in reportMarkdown().
     */
    public function quoteMarkdown(): string
    {
        $stored = $this->readStoredQuote();

        if ($stored !== null) {
            return $stored['content'];
        }

        return $this->quoteWriter->render($this->snapshot());
    }

    /**
     * Where the downloadable quote comes from, for the dashboard and the CLI.
     *
     * @return array<string, mixed>
     */
    public function quoteMeta(): array
    {
        $stored = $this->readStoredQuote();

        if ($stored !== null) {
            return [
                'source' => 'document',
                'lang' => $stored['lang'],
                'generated_at' => $stored['generated_at'],
                'stale' => $stored['stale'],
                'path' => $stored['path'],
                'filename' => $this->quoteFilename(),
            ];
        }

        return [
            'source' => 'template',
            'lang' => $this->quoteLanguage(),
            'generated_at' => null,
            'stale' => false,
            'path' => null,
            'filename' => $this->quoteFilename(),
        ];
    }

    /**
     * Language of the downloadable quote: the stored document's own stamp when
     * there is one, otherwise the PRD's language for the built-in template.
     */
    public function quoteLanguage(): string
    {
        return ArtifactLanguage::detect($this->prd->read());
    }

    public function quoteFilename(): string
    {
        $prd = $this->prd->read() ?? '';
        $stored = is_file($this->quotePath()) ? $this->quoteFrontMatter((string) file_get_contents($this->quotePath())) : [];
        $lang = ArtifactLanguage::normalizeTag($stored['lang'] ?? null) ?? $this->quoteLanguage();

        return $this->quoteWriter->filename($prd, $lang);
    }

    /**
     * @return array<string, string>
     */
    protected function quoteFrontMatter(string $content): array
    {
        if (preg_match('/\A---\R(.*?)\R---\R/s', $content, $matches) !== 1) {
            return [];
        }

        $parsed = Yaml::parse($matches[1]);

        if (! is_array($parsed)) {
            return [];
        }

        $meta = [];

        foreach (['lang', 'generated_at', 'inputs', 'title'] as $key) {
            if (is_scalar($parsed[$key] ?? null)) {
                $meta[$key] = (string) $parsed[$key];
            }
        }

        return $meta;
    }

    /**
     * Merge Larapilot's stamps into the document front matter, creating the
     * block when the author did not write one.
     *
     * @param  array<string, string>  $stamps
     */
    protected function stampQuoteFrontMatter(string $content, array $stamps): string
    {
        if (preg_match('/\A---\R(.*?)\R---\R?/s', $content, $matches) === 1) {
            $parsed = Yaml::parse($matches[1]);
            $front = is_array($parsed) ? $parsed : [];
            $body = substr($content, strlen($matches[0]));
        } else {
            $front = [];
            $body = $content;
        }

        foreach ($stamps as $key => $value) {
            $front[$key] = $value;
        }

        $front = ['title' => $front['title'] ?? 'Commercial quote'] + $front;

        return '---'."\n".rtrim(Yaml::dump($front, 2, 2))."\n".'---'."\n\n".ltrim($body)."\n";
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
     * Effort follows the backlog: planned task hours first, story points for
     * specs without a plan, and a scope heuristic only when nothing is sized.
     *
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected function effortModel(array $inception, array $profile): array
    {
        $settings = $this->config->settings();
        $settingHoursPerPoint = match ($settings['effort'] ?? 'STANDARD') {
            'ECO' => 3.0,
            'MAX' => 5.5,
            default => 4.0,
        };

        $rows = $this->specEffortRows();

        $plannedHours = 0.0;
        $plannedPoints = 0;
        $plannedSpecs = 0;
        $plannedTasks = 0;
        $storyPoints = 0;

        foreach ($rows as $row) {
            $plannedTasks += $row['tasks'];
            $storyPoints += $row['points'];

            if ($row['plan_hours'] > 0) {
                $plannedHours += $row['plan_hours'];
                $plannedSpecs++;
                $plannedPoints += $row['points'];
            }
        }

        // Plans are the strongest estimate the project has. Once enough of the
        // backlog is planned, the rest of the story points convert at the rate
        // those plans actually imply instead of the generic effort constant.
        $calibratedHoursPerPoint = $plannedSpecs >= 2 && $plannedPoints >= 5 && $plannedHours > 0
            ? min(12.0, max(0.5, round($plannedHours / $plannedPoints, 2)))
            : null;

        $hoursPerPoint = $calibratedHoursPerPoint ?? $settingHoursPerPoint;

        $breakdown = [];
        $baseHours = 0.0;
        $hoursFromPoints = 0.0;
        $unplannedPoints = 0;
        $unsizedSpecs = 0;
        $deliveredBase = 0.0;

        foreach ($rows as $row) {
            if ($row['plan_hours'] > 0) {
                $hours = $row['plan_hours'];
                $from = 'plan';
            } elseif ($row['points'] > 0) {
                $hours = $row['points'] * $hoursPerPoint;
                $from = 'points';
                $unplannedPoints += $row['points'];
                $hoursFromPoints += $hours;
            } else {
                $hours = self::DEFAULT_SPEC_POINTS * $hoursPerPoint;
                $from = 'unsized';
                $unsizedSpecs++;
                $hoursFromPoints += $hours;
            }

            $baseHours += $hours;

            if ($row['done']) {
                $deliveredBase += $hours;
            }

            if (count($breakdown) < self::MAX_BREAKDOWN_ROWS) {
                $breakdown[] = [
                    'code' => $row['code'],
                    'title' => $row['title'],
                    'status' => $row['status'],
                    'points' => $row['points'],
                    'tasks' => $row['tasks'],
                    'from' => $from,
                    'hours' => round($hours, 1),
                    'done' => $row['done'],
                ];
            }
        }

        $deliveryMultiplier = $this->deliveryMultiplier((string) ($inception['delivery_target'] ?? ''));
        $kindMultiplier = $this->kindMultiplier((string) ($inception['project_kind'] ?? ''));
        $typeMultiplier = $this->typeMultiplier((string) ($inception['website_type'] ?? ''));
        $heuristicHours = self::HEURISTIC_BASE_HOURS * $deliveryMultiplier * $kindMultiplier * $typeMultiplier;

        if ($rows === []) {
            $source = 'heuristic';
            $baseHours = self::HEURISTIC_BASE_HOURS;
            $scopeMultiplier = $deliveryMultiplier * $kindMultiplier * $typeMultiplier;
        } else {
            // Spec-backed hours already describe the real scope — scope
            // multipliers would double-count what the backlog says.
            $scopeMultiplier = 1.0;
            $deliveryMultiplier = 1.0;
            $kindMultiplier = 1.0;
            $typeMultiplier = 1.0;

            $source = match (true) {
                $plannedHours > 0 && ($unplannedPoints > 0 || $unsizedSpecs > 0) => 'mixed',
                $plannedHours > 0 => 'plan_hours',
                default => 'story_points',
            };
        }

        $buffer = self::PM_QA_BUFFER;
        $adjusted = $baseHours * $scopeMultiplier * $buffer;
        $deliveredHours = $deliveredBase * $scopeMultiplier * $buffer;
        $actualHours = (float) ($this->usage->summary()['total_hours'] ?? 0);

        $hoursPerDay = max(1.0, (float) $profile['hours_per_day']);
        $capacityYear = max(1.0, ((int) $profile['billable_days_per_year']) * $hoursPerDay);
        $personYears = round($adjusted / $capacityYear, 2);

        return [
            'source' => $source,
            'source_label' => $this->effortSourceLabel($source),
            'spec_count' => count($rows),
            'planned_specs' => $plannedSpecs,
            'unsized_specs' => $unsizedSpecs,
            'story_points' => $storyPoints,
            'unplanned_points' => $unplannedPoints,
            'planned_tasks' => $plannedTasks,
            'plan_hours' => round($plannedHours, 1),
            'hours_from_points' => round($hoursFromPoints, 1),
            'hours_per_point' => $hoursPerPoint,
            'hours_per_point_source' => $calibratedHoursPerPoint !== null ? 'plans' : 'settings',
            'hours_per_point_setting' => $settingHoursPerPoint,
            'heuristic_hours' => round($heuristicHours, 1),
            'base_hours' => round($baseHours, 1),
            'delivery_multiplier' => $deliveryMultiplier,
            'kind_multiplier' => $kindMultiplier,
            'type_multiplier' => $typeMultiplier,
            'scope_multiplier' => round($scopeMultiplier, 3),
            'buffer' => $buffer,
            'billable_hours' => round($adjusted, 1),
            'delivered_hours' => round($deliveredHours, 1),
            'remaining_hours' => round(max(0.0, $adjusted - $deliveredHours), 1),
            'calendar_months' => round($adjusted / max(1.0, $hoursPerDay * 20), 1),
            'capacity_hours_year' => round($capacityYear, 1),
            'person_years' => $personYears,
            'actual_hours' => round($actualHours, 1),
            'warnings' => $this->effortWarnings($source, $unsizedSpecs, $personYears, $calibratedHoursPerPoint, $settingHoursPerPoint),
            'breakdown' => $breakdown,
            'notes' => $this->effortNotes($source, $calibratedHoursPerPoint !== null),
        ];
    }

    /**
     * One row per backlog item with the estimate signals it carries.
     *
     * @return list<array{code: string, title: string, status: string, points: int, tasks: int, plan_hours: float, done: bool}>
     */
    protected function specEffortRows(): array
    {
        $rows = [];

        foreach ($this->specs->allSpecs() as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $code = (string) ($spec['code'] ?? '');
            $planHours = 0.0;
            $tasks = 0;

            if ($code !== '') {
                $plan = $this->plans->read($code);

                foreach (is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [] as $task) {
                    if (! is_array($task)) {
                        continue;
                    }

                    $tasks++;
                    $planHours += max(0.0, (float) ($task['estimate_hours'] ?? 0));
                }
            }

            $status = strtoupper(trim((string) ($spec['status'] ?? 'TODO')));

            $rows[] = [
                'code' => $code !== '' ? $code : '—',
                'title' => (string) ($spec['title'] ?? ($code !== '' ? $code : 'Untitled')),
                'status' => $status !== '' ? $status : 'TODO',
                'points' => max(0, (int) ($spec['points'] ?? 0)),
                'tasks' => $tasks,
                'plan_hours' => round($planHours, 1),
                'done' => $status === 'DONE',
            ];
        }

        return $rows;
    }

    protected function effortSourceLabel(string $source): string
    {
        return match ($source) {
            'plan_hours' => 'Plan task hours',
            'story_points' => 'Story points',
            'mixed' => 'Plan hours + story points',
            default => 'Scope heuristic (nothing sized yet)',
        };
    }

    protected function effortNotes(string $source, bool $calibrated): string
    {
        if ($source === 'heuristic') {
            return 'No backlog yet — sized from the inception answers (kind, delivery target, type). Add specs and the quote follows them instead.';
        }

        $note = 'Straight from the backlog: '.($source === 'plan_hours'
            ? 'planned task hours'
            : ($source === 'mixed' ? 'planned task hours plus story points for specs without a plan' : 'story points per spec'))
            .', plus the '.(int) round((self::PM_QA_BUFFER - 1) * 100).'% PM/QA buffer. No scope inflation.';

        if ($calibrated) {
            $note .= ' Hours per point are calibrated on the specs that already have a plan.';
        }

        return $note;
    }

    /**
     * @return list<string>
     */
    protected function effortWarnings(string $source, int $unsizedSpecs, float $personYears, ?float $calibrated, float $setting): array
    {
        $warnings = [];

        if ($source === 'heuristic') {
            $warnings[] = 'Nothing in the backlog is sized yet, so the hours are a scope heuristic — add story points or plans and the quote follows them.';
        }

        if ($unsizedSpecs > 0) {
            $warnings[] = $unsizedSpecs.' spec'.($unsizedSpecs === 1 ? '' : 's').' carry neither a plan nor story points — each counted as '
                .self::DEFAULT_SPEC_POINTS.' points. Size them for a firmer quote.';
        }

        if ($personYears > 1.0) {
            $warnings[] = 'Scope is '.$personYears.' person-years at the configured capacity. Split it into releases, or re-check the story points: one person cannot bill this inside a year.';
        }

        if ($calibrated !== null && abs($calibrated - $setting) >= 1.0) {
            $warnings[] = 'Your plans imply '.$calibrated.'h per story point instead of the '.$setting.'h effort default — the quote uses the planned rate.';
        }

        return $warnings;
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
            str_contains($value, 'personal') => 0.5,
            str_contains($value, 'package') => 0.8,
            str_contains($value, 'website') => 0.9,
            default => 1.6,
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
        $operatingOverhead = ((float) $profile['overhead_monthly']) * $months;
        $complianceInQuote = ((float) ($regime['compliance_annual'] ?? 0) / 12) * $months;
        $overhead = $operatingOverhead;
        $direct = $labor + $overhead;
        $marginPct = (float) $profile['margin_target_pct'];
        $margin = $direct * ($marginPct / 100);
        $gross = $direct + $margin;

        $vatExempt = (bool) ($regime['vat_exempt'] ?? false);
        $vatRegistered = $profile['vat_registered'];
        if ($vatRegistered === null) {
            $vatRegistered = ! $vatExempt;
        }
        $vatMode = strtolower((string) ($profile['vat_mode'] ?? 'domestic'));
        if (! in_array($vatMode, ['domestic', 'eu_b2b'], true)) {
            $vatMode = 'domestic';
        }
        $vatRate = $vatRegistered && ! $vatExempt && $vatMode !== 'eu_b2b'
            ? (float) ($country['vat_rate'] ?? 0)
            : 0.0;
        $vat = $gross * ($vatRate / 100);
        $maintenance = $gross * ((float) $profile['maintenance_annual_pct'] / 100);

        return [
            'hourly_rate' => round($rate, 2),
            'billable_hours' => round($hours, 1),
            'calendar_months' => round($months, 1),
            'labor' => round($labor, 2),
            'overhead' => round($overhead, 2),
            'overhead_operating' => round($operatingOverhead, 2),
            'overhead_compliance' => round($complianceInQuote, 2),
            'direct' => round($direct, 2),
            'margin' => round($margin, 2),
            'margin_pct' => $marginPct,
            'gross' => round($gross, 2),
            'vat_rate' => $vatRate,
            'vat' => round($vat, 2),
            'vat_registered' => (bool) $vatRegistered,
            'vat_mode' => $vatMode,
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
    protected function applyTax(float $revenue, float $operatingCosts, array $profile, array $regime, array $country, string $account, ?float $months = null): array
    {
        $calendarMonths = $months ?? max(
            0.25,
            $revenue / max(1.0, (float) $profile['hourly_rate'] * (float) $profile['hours_per_day'] * 20)
        );
        $yearFraction = min(1.0, max(0.02, $calendarMonths / 12));
        $compliance = (float) ($regime['compliance_annual'] ?? 0) * $yearFraction;
        $ownerWorking = $profile['owner_working'] ?? null;
        if ($ownerWorking === null) {
            $ownerWorking = $account === 'COMPANY';
        }

        return TaxEngine::compute($revenue, $operatingCosts, $regime, $country, $account, [
            'year_fraction' => $yearFraction,
            'compliance' => $compliance,
            'owner_working' => (bool) $ownerWorking,
            'extraction' => (string) ($profile['extraction'] ?? 'auto'),
        ]);
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
        $gross = (float) $quote['gross'];
        $cap = isset($regime['revenue_cap'])
            ? (float) $regime['revenue_cap']
            : (isset($regime['revenue_hard_cap']) ? (float) $regime['revenue_hard_cap'] : null);

        if ($cap !== null && $gross > $cap) {
            return [
                'account' => $account,
                'regime' => $regime['label'],
                'applicable' => false,
                'reason' => 'Client price exceeds the €'.number_format($cap, 0, '.', ',').' ceiling for this regime.',
                'revenue_cap' => $cap,
                'net_to_owner' => null,
                'effective_rate_pct' => null,
                'total_tax' => null,
            ];
        }

        $months = (float) ($quote['calendar_months'] ?? 6);
        $operating = (float) ($quote['overhead_operating'] ?? (float) ($quote['overhead'] ?? 0));
        $tax = $this->applyTax(
            $gross,
            $operating,
            $profile,
            $regime,
            $country,
            $account,
            $months
        );

        return [
            'account' => $account,
            'regime' => $regime['label'],
            'applicable' => true,
            'net_to_owner' => $tax['net_to_owner'],
            'effective_rate_pct' => $tax['effective_rate_pct'],
            'total_tax' => $tax['total_tax'],
            'social' => $tax['social'] ?? 0,
            'extraction' => is_array($tax['extraction'] ?? null) ? ($tax['extraction']['method'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @return list<array<string, mixed>>
     */
    protected function taxScenarios(array $quote, array $profile, array $regime, array $country, string $account): array
    {
        if (($regime['model'] ?? '') !== 'corporate' || $account !== 'COMPANY') {
            return [];
        }

        $gross = (float) $quote['gross'];
        $operating = (float) ($quote['overhead_operating'] ?? (float) ($quote['overhead'] ?? 0));
        $months = (float) ($quote['calendar_months'] ?? 6);
        $rows = [];

        foreach ([
            'optimistic' => ['owner_working' => false, 'extraction' => 'dividends', 'label' => 'Optimistic', 'hint' => 'Non-prevalent shareholder / investor path — no Gestione Commercianti.'],
            'realistic' => [
                'owner_working' => $profile['owner_working'] ?? true,
                'extraction' => (string) ($profile['extraction'] ?? 'auto'),
                'label' => 'Realistic',
                'hint' => 'Current profile settings.',
            ],
            'prudent' => ['owner_working' => true, 'extraction' => 'dividends', 'label' => 'Prudent', 'hint' => 'Working shareholder, dividends only — conservative INPS exposure.'],
        ] as $id => $scenario) {
            $tax = TaxEngine::compute($gross, $operating, $regime, $country, $account, [
                'year_fraction' => min(1.0, max(0.02, $months / 12)),
                'compliance' => (float) ($regime['compliance_annual'] ?? 0) * min(1.0, max(0.02, $months / 12)),
                'owner_working' => (bool) $scenario['owner_working'],
                'extraction' => $scenario['extraction'],
            ]);

            $rows[] = [
                'id' => $id,
                'label' => $scenario['label'],
                'hint' => $scenario['hint'],
                'net_to_owner' => $tax['net_to_owner'],
                'effective_rate_pct' => $tax['effective_rate_pct'],
                'social' => $tax['social'] ?? 0,
                'extraction' => is_array($tax['extraction'] ?? null) ? ($tax['extraction']['method'] ?? null) : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>  $tax
     * @param  array<string, mixed>  $saas
     * @return array<string, mixed>
     */
    protected function salesEstimates(array $quote, array $profile, array $inception, array $tax, string $productModel, array $saas): array
    {
        $gross = (float) $quote['gross'];
        $net = (float) ($quote['net_to_owner'] ?? 0);
        $maintenance = (float) ($quote['maintenance_year'] ?? 0);
        [$license, $licenseSource] = $this->licensePrice($gross, $profile);
        $unitsBreakEven = $license > 0 ? (int) ceil($gross / $license) : 0;
        $contribution = (float) ($saas['contribution_per_customer'] ?? 0);
        $fixedMonthly = (float) ($saas['fixed_monthly'] ?? 0);
        $criticalMass = (int) ($saas['break_even_customers'] ?? 0);
        $marginMonthlyAtCritical = $criticalMass > 0
            ? round(($criticalMass * $contribution) - $fixedMonthly, 2)
            : 0.0;

        return [
            'one_shot' => [
                'label' => 'One-shot delivery / license',
                'client_price_ex_vat' => $gross,
                'client_total' => (float) ($quote['client_total'] ?? $gross),
                'net_to_owner' => $net,
                'effective_tax_pct' => (float) ($quote['effective_tax_pct'] ?? 0),
                'maintenance_annual' => $maintenance,
                'maintenance_monthly' => (float) ($quote['maintenance_monthly'] ?? 0),
                'suggested_license_price' => $license,
                'license_price_source' => $licenseSource,
                'units_to_recover_build' => $unitsBreakEven,
                'primary' => in_array($productModel, ['fixed', 'package', 'ecommerce'], true),
            ],
            'saas' => [
                'label' => 'SaaS / subscription',
                'price_monthly' => (float) ($saas['price_monthly'] ?? 0),
                'price_annual' => (float) ($saas['price_annual'] ?? 0),
                'critical_mass_customers' => $criticalMass,
                'critical_mass_mrr' => round($criticalMass * (float) ($saas['price_monthly'] ?? 0), 2),
                'critical_mass_arr' => (float) ($saas['arr_at_break_even'] ?? 0),
                'monthly_margin_at_critical_mass' => $marginMonthlyAtCritical,
                'monthly_fixed_costs' => $fixedMonthly,
                'infrastructure_monthly' => (float) ($saas['infrastructure_monthly'] ?? 0),
                'maintenance_monthly' => (float) ($quote['maintenance_monthly'] ?? 0),
                'customers_to_recover_build_12m' => (int) ($saas['customers_to_recover_12m'] ?? 0),
                'customers_to_recover_build_24m' => (int) ($saas['customers_to_recover_24m'] ?? 0),
                'contribution_per_customer' => $contribution,
                'gross_margin_pct' => (float) ($saas['gross_margin_pct'] ?? 0),
                'primary' => $productModel === 'saas',
            ],
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

    /**
     * Resale price for one licence of what this project builds.
     *
     * An annual price the user actually set is a real decision, so it wins
     * (the profile default is 0 = undecided). Without one the fallback is
     * deliberately a round fraction of the build — it says "sell 80 of these to
     * earn the build back", nothing more, and the snapshot reports which of the
     * two produced the number.
     *
     * @param  array<string, mixed>  $profile
     * @return array{0: float, 1: string}
     */
    protected function licensePrice(float $gross, array $profile): array
    {
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
        $configured = (float) ($saas['price_annual'] ?? 0);

        if ($configured > 0) {
            return [round($configured, 2), 'configured_annual_price'];
        }

        return [max(49.0, round($gross / 80, 0)), 'heuristic_build_fraction'];
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

        // Honest numbers: a project larger than a working year shows above 100%
        // utilization and less than one project per year, instead of a
        // reassuring 100% / 1 that hides the overrun.
        $utilizationPct = $annualCapacity > 0 ? round($hours / $annualCapacity * 100, 1) : 0.0;
        $projectsPerYear = $hours > 0 ? round($annualCapacity / $hours, 2) : 0.0;

        $payload = [
            'model' => $productModel,
            'utilization_pct' => $utilizationPct,
            'over_capacity' => $utilizationPct > 100.0,
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
            [$license, $licenseSource] = $this->licensePrice($gross, $profile);
            $payload['licenses_to_recover'] = $license > 0 ? (int) ceil($gross / $license) : 0;
            $payload['suggested_license_price'] = $license;
            $payload['license_price_source'] = $licenseSource;
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
