<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\ArtifactLanguage;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\EconomicsStrings;
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

    /**
     * PRD language of the snapshot being computed. Set at the top of every
     * `snapshot()` call; English until then.
     */
    protected string $language = ArtifactLanguage::DEFAULT;

    /**
     * Inputs the dashboard pricing tool may change without persisting anything.
     * Anything outside this list is ignored: a simulation can move the price,
     * never the shape of the engine.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    public const NUMERIC_OVERRIDES = [
        'hourly_rate' => [1.0, 5000.0],
        'margin_target_pct' => [0.0, 300.0],
        'discount_pct' => [0.0, 90.0],
        'overhead_monthly' => [0.0, 1000000.0],
        'maintenance_annual_pct' => [0.0, 100.0],
        'team_size' => [0.25, 50.0],
        'hours_per_day' => [1.0, 24.0],
        'billable_days_per_year' => [1.0, 366.0],
        'price_monthly' => [1.0, 100000.0],
        'churn_monthly_pct' => [0.1, 100.0],
        'growth_monthly_pct' => [0.0, 200.0],
        'target_customers' => [0.0, 10000000.0],
    ];

    /**
     * Subscription inputs that live under `saas.` in the profile.
     *
     * @var list<string>
     */
    protected const SAAS_OVERRIDES = ['price_monthly', 'churn_monthly_pct', 'growth_monthly_pct', 'target_customers'];

    /**
     * Packaging ladder around the list price: BASE undercuts it, PRO is it,
     * PREMIUM carries the whole backlog. Overridden by researched tiers.
     */
    protected const TIER_MULTIPLIERS = ['base' => 0.6, 'pro' => 1.0, 'premium' => 2.2];

    /**
     * Revenue mix assumed when nobody researched one, in percent per tier.
     */
    protected const TIER_MIX = ['base' => 55.0, 'pro' => 35.0, 'premium' => 10.0];

    /**
     * Saved profile values behind the currently rendered controls, so each
     * dropdown knows what "unchanged" means while a simulation is on screen.
     *
     * @var array<string, mixed>
     */
    protected array $savedControlValues = [];

    public function __construct(
        protected ConfigService $config,
        protected ChoicesService $choices,
        protected SpecService $specs,
        protected PlanService $plans,
        protected PrdService $prd,
        protected UsageService $usage,
        protected EconomicsQuoteWriter $quoteWriter,
        protected EconomicsMarketService $market,
        protected ReleaseService $releases,
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
            'discount_pct' => 0.0,
            'team_size' => 1.0,
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

        // A discount above 100% would invert the price, and half a person is
        // the smallest team that still means something on a calendar.
        if (array_key_exists('discount_pct', $current)) {
            $current['discount_pct'] = round(min(90.0, max(0.0, (float) $current['discount_pct'])), 2);
        }

        if (array_key_exists('team_size', $current)) {
            $current['team_size'] = round(min(50.0, max(0.25, (float) $current['team_size'])), 2);
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
     * `$overrides` is what the dashboard pricing tool sends: a what-if layered
     * on the saved profile for this one computation. A simulated snapshot is
     * never written to disk — the stored cost board keeps following the
     * profile, so a dropdown someone played with cannot become the quote.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function snapshot(array $overrides = []): array
    {
        $overrides = $this->normalizeOverrides($overrides);
        $this->language = $this->quoteLanguage();
        $account = $this->accountMode();
        $profile = $this->read();
        $research = $this->market->read();
        $savedProfile = $profile;
        $savedAccount = $account;

        if ($overrides !== []) {
            [$profile, $account] = $this->applyOverrides($profile, $account, $overrides);
        }

        $inception = $this->inceptionContext();
        $effort = $this->effortModel($inception, $profile);
        $maintenance = $this->maintenanceModel($inception, $profile);

        // An untouched profile has no opinion on the retainer, so it follows
        // the inception answers instead of a flat catalogue default.
        if (empty($profile['configured']) && ! array_key_exists('maintenance_annual_pct', $overrides)) {
            $profile['maintenance_annual_pct'] = $maintenance['recommended_pct'];
            $maintenance['configured_pct'] = $maintenance['recommended_pct'];
            $maintenance['follows_inception'] = true;
            $maintenance['adopted'] = true;
        }

        $payload = [
            'enabled' => $account !== 'NONE',
            'account' => $account,
            'fiscal_year' => TaxCatalog::YEAR,
            'language' => $this->language,
            'disclaimer' => $this->t('svc.disclaimer', ['year' => $this->yearLabel()]),
            'profile' => $profile,
            'inception' => $inception,
            'effort' => $effort,
            'path' => $this->config->relativePath($this->path()),
            'countries' => $this->countryOptions(),
            'inputs' => $this->inputsFingerprint(),
            'simulation' => [
                'active' => $overrides !== [],
                'overrides' => $overrides,
                'command' => $this->persistCommand($profile, $account),
            ],
            'market' => $this->marketBlock($research, $this->resolveProductModel($profile, $inception)),
        ];

        if ($account === 'NONE') {
            return $payload + [
                'quote' => null,
                'tax' => null,
                'alternate' => null,
                'scenarios' => [],
                'sales' => null,
                'payback' => null,
                'maintenance' => null,
                'product' => ['model' => 'off'],
                'saas' => null,
                'forecast' => [],
                'packaging' => null,
                'business_plan' => null,
                'controls' => [],
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

        // Packaging first: the selected tier is the price line the subscription
        // maths and the whole forecast below it run on.
        $packaging = $this->packaging($quote, $profile, $inception, $research, $overrides);
        $profile['saas']['price_monthly'] = $packaging['selected']['price_monthly'];

        // A derived annual price is a display figure, not a decision: writing
        // it back would make the licence heuristic think a price was set.
        if (($packaging['selected']['source'] ?? '') === 'research') {
            $profile['saas']['price_annual'] = $packaging['selected']['price_annual'];
        }

        $scenarioId = (string) ($overrides['scenario'] ?? 'realistic');
        $saas = $this->saasModel($quote, $profile, $inception, $tax, $research, $scenarioId, $overrides);
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
            'maintenance' => $maintenance + [
                'annual' => (float) $quote['maintenance_year'],
                'monthly' => (float) $quote['maintenance_monthly'],
                'recommended_annual' => round(((float) $quote['gross']) * $maintenance['recommended_pct'] / 100, 2),
            ],
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
            'packaging' => $packaging,
            'business_plan' => $this->businessPlan($quote, $profile, $inception, $tax, $research, $packaging, $scenarioId, $overrides),
            'controls' => $this->controls(
                $profile,
                $account,
                $country,
                $effort,
                $packaging,
                $overrides,
                $productModel,
                $this->savedControls($savedProfile, $savedAccount, $inception),
                (float) $maintenance['recommended_pct']
            ),
            'quote_document' => $this->quoteMeta(),
            'snapshot_path' => $this->config->relativePath($this->snapshotPath()),
            'snapshot_saved_at' => $computedAt,
        ];

        if ($overrides === []) {
            $this->writeSnapshot($result, $computedAt);
        }

        return $result;
    }

    /**
     * Keep only the inputs the pricing tool is allowed to move, each inside the
     * range the engine can actually compute with. Everything else is dropped —
     * a query string never reaches the tax engine unfiltered.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function normalizeOverrides(array $overrides): array
    {
        $clean = [];

        foreach (self::NUMERIC_OVERRIDES as $key => [$min, $max]) {
            $value = $overrides[$key] ?? null;

            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }

            $number = (float) $value;

            if ($number < $min || $number > $max) {
                continue;
            }

            $clean[$key] = in_array($key, ['target_customers', 'billable_days_per_year'], true)
                ? (int) round($number)
                : round($number, 2);
        }

        $account = strtoupper(trim((string) ($overrides['account'] ?? '')));
        if (in_array($account, ['FREELANCE', 'COMPANY'], true)) {
            $clean['account'] = $account;
        }

        $country = trim((string) ($overrides['country'] ?? ''));
        if ($country !== '') {
            try {
                $code = TaxCatalog::normalizeCountry($country);
                TaxCatalog::country($code);
                $clean['country'] = $code;
            } catch (\InvalidArgumentException) {
                // Unknown country: keep the saved one.
            }
        }

        $regime = trim((string) ($overrides['regime'] ?? ''));
        if ($regime !== '') {
            $clean['regime'] = $regime;
        }

        $model = strtolower(trim((string) ($overrides['product_model'] ?? '')));
        if (in_array($model, self::PRODUCT_MODELS, true)) {
            $clean['product_model'] = $model;
        }

        $vatMode = strtolower(trim((string) ($overrides['vat_mode'] ?? '')));
        if (in_array($vatMode, ['domestic', 'eu_b2b'], true)) {
            $clean['vat_mode'] = $vatMode;
        }

        $extraction = strtolower(trim((string) ($overrides['extraction'] ?? '')));
        if (in_array($extraction, ['auto', 'dividends', 'mixed'], true)) {
            $clean['extraction'] = $extraction;
        }

        if (array_key_exists('owner_working', $overrides) && $overrides['owner_working'] !== '' && $overrides['owner_working'] !== null) {
            $clean['owner_working'] = filter_var($overrides['owner_working'], FILTER_VALIDATE_BOOLEAN);
        }

        $tier = strtolower(trim((string) ($overrides['tier'] ?? '')));
        if (in_array($tier, EconomicsMarketService::TIERS, true)) {
            $clean['tier'] = $tier;
        }

        $scenario = strtolower(trim((string) ($overrides['scenario'] ?? '')));
        if (in_array($scenario, EconomicsMarketService::SCENARIOS, true)) {
            $clean['scenario'] = $scenario;
        }

        return $clean;
    }

    /**
     * Layer the simulation on the profile. Moving the account or the country
     * can strand the saved regime, so the regime falls back to the default for
     * the pair unless the simulation named one that exists.
     *
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $overrides
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function applyOverrides(array $profile, string $account, array $overrides): array
    {
        if (isset($overrides['account'])) {
            $account = (string) $overrides['account'];
        }

        if (isset($overrides['country'])) {
            $profile['country'] = $overrides['country'];
            $profile['currency'] = TaxCatalog::defaultCurrency((string) $overrides['country']);
        }

        foreach (self::NUMERIC_OVERRIDES as $key => $_range) {
            if (! array_key_exists($key, $overrides)) {
                continue;
            }

            if (in_array($key, self::SAAS_OVERRIDES, true)) {
                $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
                $saas[$key] = $overrides[$key];
                $profile['saas'] = $saas;

                continue;
            }

            $profile[$key] = $overrides[$key];
        }

        foreach (['product_model', 'vat_mode', 'extraction', 'owner_working'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $profile[$key] = $overrides[$key];
            }
        }

        $country = (string) ($profile['country'] ?? TaxCatalog::defaultCountry());
        $allowed = array_keys(TaxCatalog::regimesFor($country, $account));
        $regime = (string) ($overrides['regime'] ?? $profile['regime'] ?? '');

        $profile['regime'] = in_array($regime, $allowed, true)
            ? $regime
            : TaxCatalog::defaultRegime($country, $account);

        return [$profile, $account];
    }

    /**
     * The `economics-set` call that would make the current simulation the
     * project profile. The dashboard never writes — it hands over the command.
     *
     * @param  array<string, mixed>  $profile
     */
    protected function persistCommand(array $profile, string $account): string
    {
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];

        $flags = [
            '--country='.($profile['country'] ?? ''),
            '--regime='.($profile['regime'] ?? ''),
            '--hourly-rate='.$this->flagNumber($profile['hourly_rate'] ?? 0),
            '--margin='.$this->flagNumber($profile['margin_target_pct'] ?? 0),
            '--discount='.$this->flagNumber($profile['discount_pct'] ?? 0),
            '--team-size='.$this->flagNumber($profile['team_size'] ?? 1),
            '--overhead-monthly='.$this->flagNumber($profile['overhead_monthly'] ?? 0),
            '--maintenance='.$this->flagNumber($profile['maintenance_annual_pct'] ?? 0),
            '--product-model='.($profile['product_model'] ?? 'auto'),
            '--price-monthly='.$this->flagNumber($saas['price_monthly'] ?? 0),
            '--churn='.$this->flagNumber($saas['churn_monthly_pct'] ?? 0),
        ];

        $prefix = $account !== $this->accountMode()
            ? 'php artisan larapilot:settings-set --account='.$account."\n"
            : '';

        return $prefix.'php artisan larapilot:economics-set '.implode(' ', $flags);
    }

    protected function flagNumber(mixed $value): string
    {
        $number = (float) $value;

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.') ?: '0';
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
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function dashboard(array $overrides = []): array
    {
        return $this->snapshot($overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function reportMarkdown(array $overrides = []): string
    {
        $data = $this->snapshot($overrides);
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
     * template (see ArtifactLanguage::SUPPORTED) renders the download when no one wrote one yet.
     * Internal tax figures stay in reportMarkdown().
     */
    /**
     * A simulation renders the built-in template with the simulated numbers:
     * the stored document was written by hand for the saved profile, and
     * Larapilot never silently rewrites a client document around a dropdown.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function quoteMarkdown(array $overrides = []): string
    {
        $overrides = $this->normalizeOverrides($overrides);
        $stored = $overrides === [] ? $this->readStoredQuote() : null;

        if ($stored !== null) {
            return $stored['content'];
        }

        return $this->quoteWriter->render($this->snapshot($overrides));
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

    /**
     * Language of the prose this engine writes into the snapshot, and of the
     * dashboard that renders it — the PRD's, like the client quote.
     *
     * Read straight from the PRD rather than from the cached property, so the
     * answer is right before the first `snapshot()` of the request too. The
     * property exists to keep detection off the hot path while one snapshot is
     * being composed.
     */
    public function language(): string
    {
        return $this->quoteLanguage();
    }

    /**
     * A sentence from the Economics vocabulary in the current language.
     *
     * @param  array<string, string|int|float>  $replace
     */
    protected function t(string $key, array $replace = []): string
    {
        return EconomicsStrings::line($this->language, $key, $replace);
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
            'business_model' => $this->stringOrNull($choices['business_model'] ?? null),
            'budget_sensitivity' => $this->stringOrNull($choices['budget_sensitivity'] ?? null),
            'deploy_platform' => $this->stringOrNull($choices['deploy_platform'] ?? null),
            'server_management' => $this->stringOrNull($choices['server_management'] ?? null),
            'ops_owner' => $this->stringOrNull($choices['ops_owner'] ?? null),
            'support_window' => $this->stringOrNull($choices['support_window'] ?? null),
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
                    'epic' => $row['epic'],
                    'release' => $row['release'],
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

        // Team size compresses the calendar, not the work: the same hours
        // delivered by two people take half the elapsed months. Person-months
        // stay constant, which is why the price below does not move with it.
        $teamSize = max(0.25, (float) ($profile['team_size'] ?? 1));
        $personMonths = $adjusted / max(1.0, $hoursPerDay * 20);
        $elapsedMonths = $personMonths / $teamSize;
        $soloMonths = $personMonths;

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
            'calendar_months' => round($elapsedMonths, 1),
            'solo_months' => round($soloMonths, 1),
            'person_months' => round($personMonths, 1),
            'team_size' => round($teamSize, 2),
            'capacity_hours_year' => round($capacityYear, 1),
            'person_years' => $personYears,
            'actual_hours' => round($actualHours, 1),
            'warnings' => $this->effortWarnings($source, $unsizedSpecs, $personYears, $calibratedHoursPerPoint, $settingHoursPerPoint),
            'breakdown' => $breakdown,
            'by_epic' => $this->groupEffort($breakdown, 'epic', $scopeMultiplier * $buffer, (float) $profile['hourly_rate']),
            'by_release' => $this->groupEffort($breakdown, 'release', $scopeMultiplier * $buffer, (float) $profile['hourly_rate']),
            'notes' => $this->effortNotes($source, $calibratedHoursPerPoint !== null),
        ];
    }

    /**
     * One row per backlog item with the estimate signals it carries.
     *
     * @return list<array{code: string, title: string, status: string, points: int, tasks: int, plan_hours: float, done: bool, epic: string|null, release: string|null}>
     */
    protected function specEffortRows(): array
    {
        $rows = [];
        $releases = $this->releaseIndex();

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

            $epic = is_array($spec['epic'] ?? null) ? $spec['epic'] : [];

            $rows[] = [
                'code' => $code !== '' ? $code : '—',
                'title' => (string) ($spec['title'] ?? ($code !== '' ? $code : 'Untitled')),
                'status' => $status !== '' ? $status : 'TODO',
                'points' => max(0, (int) ($spec['points'] ?? 0)),
                'tasks' => $tasks,
                'plan_hours' => round($planHours, 1),
                'done' => $status === 'DONE',
                'epic' => $this->stringOrNull($epic['title'] ?? null) ?? $this->stringOrNull($epic['code'] ?? null),
                'release' => $releases[strtoupper($code)] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Spec code → release version, so the effort table can be read the way the
     * project is actually delivered.
     *
     * @return array<string, string>
     */
    protected function releaseIndex(): array
    {
        $index = [];

        foreach ($this->releases->read()['releases'] as $release) {
            $version = $this->stringOrNull($release['version'] ?? null);

            if ($version === null) {
                continue;
            }

            foreach (is_array($release['specs'] ?? null) ? $release['specs'] : [] as $code) {
                if (is_scalar($code)) {
                    $index[strtoupper(trim((string) $code))] = $version;
                }
            }
        }

        return $index;
    }

    /**
     * Totals per epic or per release. Hours are the raw estimates, so the
     * buffer and any scope multiplier apply here exactly as they do to the
     * headline figure — the group totals add up to the billable hours.
     *
     * @param  list<array<string, mixed>>  $breakdown
     * @return list<array<string, mixed>>
     */
    protected function groupEffort(array $breakdown, string $key, float $factor, float $rate): array
    {
        $groups = [];

        foreach ($breakdown as $row) {
            $label = $this->stringOrNull($row[$key] ?? null);

            if ($label === null) {
                continue;
            }

            $groups[$label] ??= [
                'label' => $label,
                'specs' => 0,
                'done' => 0,
                'points' => 0,
                'base_hours' => 0.0,
            ];

            $groups[$label]['specs']++;
            $groups[$label]['done'] += ! empty($row['done']) ? 1 : 0;
            $groups[$label]['points'] += (int) ($row['points'] ?? 0);
            $groups[$label]['base_hours'] += (float) ($row['hours'] ?? 0);
        }

        $rows = [];

        foreach ($groups as $group) {
            $billable = $group['base_hours'] * $factor;

            $rows[] = [
                'label' => $group['label'],
                'specs' => $group['specs'],
                'done' => $group['done'],
                'points' => $group['points'],
                'base_hours' => round($group['base_hours'], 1),
                'billable_hours' => round($billable, 1),
                'labor' => round($billable * $rate, 2),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['billable_hours'] <=> $a['billable_hours']);

        return $rows;
    }

    protected function effortSourceLabel(string $source): string
    {
        return match ($source) {
            'plan_hours' => $this->t('svc.effort.source.plan_hours'),
            'story_points' => $this->t('svc.effort.source.story_points'),
            'mixed' => $this->t('svc.effort.source.mixed'),
            default => $this->t('svc.effort.source.heuristic'),
        };
    }

    protected function effortNotes(string $source, bool $calibrated): string
    {
        if ($source === 'heuristic') {
            return $this->t('svc.effort.notes.heuristic');
        }

        $note = $this->t('svc.effort.notes.backlog', [
            'from' => $this->t('svc.effort.notes.from.'.($source === 'plan_hours' ? 'plan_hours' : ($source === 'mixed' ? 'mixed' : 'story_points'))),
            'buffer' => (int) round((self::PM_QA_BUFFER - 1) * 100),
        ]);

        if ($calibrated) {
            $note .= ' '.$this->t('svc.effort.notes.calibrated');
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
            $warnings[] = $this->t('svc.effort.warn.heuristic');
        }

        if ($unsizedSpecs > 0) {
            $warnings[] = $this->t('svc.effort.warn.unsized.'.($unsizedSpecs === 1 ? 'one' : 'many'), [
                'count' => $unsizedSpecs,
                'points' => self::DEFAULT_SPEC_POINTS,
            ]);
        }

        if ($personYears > 1.0) {
            $warnings[] = $this->t('svc.effort.warn.person_years', ['years' => $personYears]);
        }

        if ($calibrated !== null && abs($calibrated - $setting) >= 1.0) {
            $warnings[] = $this->t('svc.effort.warn.calibration', ['calibrated' => $calibrated, 'setting' => $setting]);
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
     * What keeping this project alive is actually worth, built from the
     * answers inception already has: how far the product goes, who runs the
     * server, how it ships, how it is tested, and how fast support has to
     * answer. A retainer that ignores those is a number pulled out of the air.
     *
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected function maintenanceModel(array $inception, array $profile): array
    {
        $settings = $this->config->settings();
        $drivers = [];
        $covers = [
            $this->t('svc.maint.covers.security'),
            $this->t('svc.maint.covers.bugs'),
        ];
        $gaps = [];
        $points = 12.0;

        $add = static function (float $delta, string $reason) use (&$points, &$drivers): void {
            $points += $delta;
            $drivers[] = ['delta' => $delta, 'reason' => $reason];
        };

        $target = strtolower((string) ($inception['delivery_target'] ?? ''));
        match (true) {
            str_contains($target, 'enterprise') => $add(8.0, $this->t('svc.maint.driver.target.enterprise')),
            str_contains($target, 'full') => $add(3.0, $this->t('svc.maint.driver.target.full')),
            str_contains($target, 'mvp') => $add(-2.0, $this->t('svc.maint.driver.target.mvp')),
            $target !== '' => $add(0.0, $this->t('svc.maint.driver.target.v1')),
            default => $gaps[] = $this->t('svc.maint.gap.target'),
        };

        // Who runs the machine after go-live is the single biggest driver.
        $operations = strtolower(trim(($inception['server_management'] ?? '').' '.($inception['ops_owner'] ?? '').' '.($inception['deploy_platform'] ?? '')));
        match (true) {
            str_contains($operations, 'client') && ! str_contains($operations, 'client project') => $add(-3.0, $this->t('svc.maint.driver.ops.client')),
            str_contains($operations, 'kubernetes') || str_contains($operations, 'k8s') || str_contains($operations, 'self') || str_contains($operations, 'vps') || str_contains($operations, 'bare') || str_contains($operations, 'hetzner') || str_contains($operations, 'digitalocean') => $add(4.0, $this->t('svc.maint.driver.ops.self')),
            str_contains($operations, 'vapor') || str_contains($operations, 'forge') || str_contains($operations, 'cloud') || str_contains($operations, 'paas') || str_contains($operations, 'managed') || str_contains($operations, 'shared') => $add(-1.0, $this->t('svc.maint.driver.ops.managed')),
            default => $gaps[] = $this->t('svc.maint.gap.ops'),
        };

        if (str_contains($operations, 'kubernetes') || str_contains($operations, 'self') || str_contains($operations, 'vps') || str_contains($operations, 'bare')) {
            $covers[] = $this->t('svc.maint.covers.ops');
        }

        $budget = strtolower((string) ($inception['budget_sensitivity'] ?? ''));
        match (true) {
            str_contains($budget, 'tracked') => $add(-2.0, $this->t('svc.maint.driver.budget.tracked')),
            str_contains($budget, 'relaxed') => $add(0.0, $this->t('svc.maint.driver.budget.relaxed')),
            default => $gaps[] = $this->t('svc.maint.gap.budget'),
        };

        if (strtoupper((string) ($settings['release_mode'] ?? '')) === 'YES' || ($settings['release_mode'] ?? false) === true) {
            $add(2.0, $this->t('svc.maint.driver.release_mode'));
            $covers[] = $this->t('svc.maint.covers.releases');
        }

        if (strtoupper((string) ($settings['git_mode'] ?? '')) === 'GITFLOW') {
            $add(1.0, $this->t('svc.maint.driver.gitflow'));
            $covers[] = $this->t('svc.maint.covers.hotfix');
        }

        $testing = strtoupper((string) ($settings['testing'] ?? 'NORMAL'));
        match ($testing) {
            'BEST' => $add(-1.0, $this->t('svc.maint.driver.testing.best')),
            'NONE' => $add(3.0, $this->t('svc.maint.driver.testing.none')),
            default => null,
        };

        if (($settings['security_scan'] ?? false) === true || strtoupper((string) ($settings['security_scan'] ?? '')) === 'YES') {
            $add(1.0, $this->t('svc.maint.driver.scan'));
            $covers[] = $this->t('svc.maint.covers.scan');
        }

        $support = strtolower((string) ($inception['support_window'] ?? ''));
        match (true) {
            str_contains($support, '24') => $add(6.0, $this->t('svc.maint.driver.support.24')),
            str_contains($support, 'extended') => $add(3.0, $this->t('svc.maint.driver.support.extended')),
            str_contains($support, 'business') => $add(0.0, $this->t('svc.maint.driver.support.business')),
            str_contains($support, 'best') => $add(-2.0, $this->t('svc.maint.driver.support.best_effort')),
            default => $gaps[] = $this->t('svc.maint.gap.support'),
        };

        $recommended = round(min(40.0, max(5.0, $points)), 1);
        $configured = (float) ($profile['maintenance_annual_pct'] ?? 15);

        return [
            'recommended_pct' => $recommended,
            'configured_pct' => $configured,
            'follows_inception' => abs($configured - $recommended) <= 2.0,
            'drivers' => $drivers,
            'covers' => $covers,
            'gaps' => $gaps,
            'support_window' => $inception['support_window'] ?? null,
            'operations' => $this->stringOrNull($inception['server_management'] ?? null)
                ?? $this->stringOrNull($inception['ops_owner'] ?? null)
                ?? $this->stringOrNull($inception['deploy_platform'] ?? null),
        ];
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

        // Overhead follows person-months, not the calendar: putting two people
        // on the project halves the timeline without halving what it costs.
        $personMonths = max(0.25, (float) ($effort['person_months'] ?? $months));
        $operatingOverhead = ((float) $profile['overhead_monthly']) * $personMonths;
        $complianceInQuote = ((float) ($regime['compliance_annual'] ?? 0) / 12) * $months;
        $overhead = $operatingOverhead;
        $direct = $labor + $overhead;
        $marginPct = (float) $profile['margin_target_pct'];
        $margin = $direct * ($marginPct / 100);
        $listPrice = $direct + $margin;

        // The discount is a commercial decision taken off the list price, so it
        // comes out of the margin — the cost underneath it does not move.
        $discountPct = min(90.0, max(0.0, (float) ($profile['discount_pct'] ?? 0)));
        $discount = $listPrice * ($discountPct / 100);
        $gross = $listPrice - $discount;
        $marginAfterDiscount = $gross - $direct;
        $marginAfterDiscountPct = $direct > 0 ? ($marginAfterDiscount / $direct) * 100 : 0.0;

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
            'list_price' => round($listPrice, 2),
            'discount_pct' => round($discountPct, 2),
            'discount' => round($discount, 2),
            'margin_after_discount' => round($marginAfterDiscount, 2),
            'margin_after_discount_pct' => round($marginAfterDiscountPct, 1),
            'below_cost' => $gross < $direct,
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

        // Inception asked how this product makes money. A stated answer is a
        // decision; reading the PRD for keywords is only the fallback.
        $stated = strtolower((string) ($inception['business_model'] ?? ''));

        if ($stated !== '') {
            $model = match (true) {
                str_contains($stated, 'saas') || str_contains($stated, 'subscription') || str_contains($stated, 'abbonamento') => 'saas',
                str_contains($stated, 'commerce') || str_contains($stated, 'shop') || str_contains($stated, 'store') => 'ecommerce',
                str_contains($stated, 'package') || str_contains($stated, 'licen') || str_contains($stated, 'librar') => 'package',
                str_contains($stated, 'client') || str_contains($stated, 'one-off') || str_contains($stated, 'internal') || str_contains($stated, 'commessa') => 'fixed',
                default => null,
            };

            if ($model !== null) {
                return $model;
            }
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
            'saas' => $this->t('svc.product.saas'),
            'ecommerce' => $this->t('svc.product.ecommerce'),
            'package' => $this->t('svc.product.package'),
            'fixed' => $this->t('svc.product.fixed'),
            default => $this->t('svc.product.none'),
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
     * @param  array<string, mixed>|null  $research
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function saasModel(array $quote, array $profile, array $inception, array $tax, ?array $research = null, string $scenarioId = 'realistic', array $overrides = []): array
    {
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
        $priceMonthly = max(1.0, (float) ($saas['price_monthly'] ?? 29));
        $priceAnnual = (float) ($saas['price_annual'] ?? 0);
        if ($priceAnnual <= 0) {
            $priceAnnual = $priceMonthly * 10;
        }

        $demand = $this->scenarioParams($profile, $research, $scenarioId, $overrides);
        $churn = max(0.5, $demand['churn_monthly_pct']) / 100;
        [$contribution, $fixed, $infra] = $this->subscriptionEconomics($priceMonthly, $profile, $quote, $inception);
        $fee = max(0.0, (float) ($saas['payment_fee_pct'] ?? 2.9)) / 100;
        $support = max(0.0, (float) ($saas['support_cost_per_customer_monthly'] ?? 3));
        $breakEven = $contribution > 0 ? (int) ceil($fixed / $contribution) : 0;

        $investment = (float) $quote['gross'];
        $customers12 = $contribution > 0 ? (int) ceil(($investment / 12 + $fixed) / $contribution) : 0;
        $customers18 = $contribution > 0 ? (int) ceil(($investment / 18 + $fixed) / $contribution) : 0;
        $customers24 = $contribution > 0 ? (int) ceil(($investment / 24 + $fixed) / $contribution) : 0;

        $target = (int) $demand['customers'];
        $planningCustomers = $target > 0
            ? $target
            : max(1, (int) round(max($breakEven, $customers12, 25) * $demand['ambition']));

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

        $growth = max(0.0, $demand['growth_monthly_pct']) / 100;
        $start = max(0, (int) ($saas['starting_customers'] ?? 0));
        $forecast = $this->forecastSaaS($start, $planningCustomers, $growth, $churn, $priceMonthly, $contribution, $fixed, $investment, $tax);

        return [
            'price_monthly' => round($priceMonthly, 2),
            'price_annual' => round($priceAnnual, 2),
            'annual_discount_pct' => $priceMonthly > 0 ? round((1 - ($priceAnnual / ($priceMonthly * 12))) * 100, 1) : 0,
            'churn_monthly_pct' => round($churn * 100, 2),
            'growth_monthly_pct' => round($growth * 100, 2),
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
            'scenario' => $demand['id'],
            'scenario_label' => $demand['label'],
            'scenario_source' => $demand['source'],
            'conversion_pct' => $demand['conversion_pct'],
            'trial_to_paid_pct' => (float) ($saas['trial_to_paid_pct'] ?? 20),
            'leads_for_planning' => $demand['conversion_pct'] > 0
                ? (int) ceil($planningCustomers / ($demand['conversion_pct'] / 100))
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

    /**
     * Per-customer contribution and the monthly bill the product carries,
     * shared by the subscription model and every business-plan scenario so a
     * price line changes both at once.
     *
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $inception
     * @return array{0: float, 1: float, 2: float}
     */
    protected function subscriptionEconomics(float $priceMonthly, array $profile, array $quote, array $inception): array
    {
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
        $fee = max(0.0, (float) ($saas['payment_fee_pct'] ?? 2.9)) / 100;
        $support = max(0.0, (float) ($saas['support_cost_per_customer_monthly'] ?? 3));
        $infra = (float) ($saas['infrastructure_monthly'] ?? 0);

        if ($infra <= 0) {
            $infra = $this->infraFromDeploy((string) ($inception['deploy_platform'] ?? ''));
        }

        $fixed = $infra + ((float) $quote['maintenance_monthly']) + ((float) $profile['overhead_monthly'] * 0.35);
        $contribution = ($priceMonthly * (1 - $fee)) - $support;

        return [$contribution, $fixed, $infra];
    }

    /**
     * Demand assumptions for one scenario. Researched numbers win when Jennifer
     * put them in the market file; otherwise the optimistic and pessimistic
     * lines are the realistic one bent by a fixed amount. A value the user
     * picked in the pricing tool always wins over both — the dropdown is never
     * quietly ignored.
     *
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>|null  $research
     * @param  array<string, mixed>  $overrides
     * @return array{id: string, label: string, source: string, ambition: float, customers: int, growth_monthly_pct: float, churn_monthly_pct: float, conversion_pct: float, note: string|null}
     */
    protected function scenarioParams(array $profile, ?array $research, string $scenarioId, array $overrides = []): array
    {
        $id = in_array($scenarioId, EconomicsMarketService::SCENARIOS, true) ? $scenarioId : 'realistic';
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];

        $baseCustomers = max(0, (int) ($saas['target_customers'] ?? 0));
        $baseGrowth = max(0.0, (float) ($saas['growth_monthly_pct'] ?? 12));
        $baseChurn = max(0.5, (float) ($saas['churn_monthly_pct'] ?? 4));
        $baseConversion = max(0.1, (float) ($saas['conversion_pct'] ?? 3));

        [$customerFactor, $growthFactor, $churnFactor, $conversionFactor] = match ($id) {
            'pessimistic' => [0.4, 0.5, 1.6, 0.6],
            'optimistic' => [2.0, 1.6, 0.6, 1.4],
            default => [1.0, 1.0, 1.0, 1.0],
        };

        $params = [
            'customers' => (int) round($baseCustomers * $customerFactor),
            'growth_monthly_pct' => round($baseGrowth * $growthFactor, 2),
            'churn_monthly_pct' => round(min(100.0, $baseChurn * $churnFactor), 2),
            'conversion_pct' => round(min(100.0, $baseConversion * $conversionFactor), 2),
            'note' => null,
        ];

        $source = 'derived';
        $researched = is_array($research['demand'][$id] ?? null) ? $research['demand'][$id] : null;

        if ($researched !== null) {
            $source = 'research';

            foreach (['customers', 'growth_monthly_pct', 'churn_monthly_pct', 'conversion_pct'] as $key) {
                if (($researched[$key] ?? null) !== null) {
                    $params[$key] = $key === 'customers' ? (int) $researched[$key] : (float) $researched[$key];
                }
            }

            $params['note'] = $researched['note'] ?? null;
        }

        // The realistic line is what the dropdowns describe, so an explicit
        // pick replaces the researched figure rather than sitting next to it.
        foreach (['target_customers' => 'customers', 'growth_monthly_pct' => 'growth_monthly_pct', 'churn_monthly_pct' => 'churn_monthly_pct'] as $override => $key) {
            if (! array_key_exists($override, $overrides)) {
                continue;
            }

            $picked = (float) $overrides[$override];
            $params[$key] = $key === 'customers'
                ? (int) round($picked * $customerFactor)
                : round($picked * ($key === 'growth_monthly_pct' ? $growthFactor : $churnFactor), 2);
            $source = $source === 'research' ? 'research+picked' : 'picked';
        }

        return $params + [
            'id' => $id,
            'label' => $this->t('svc.plan.line.'.$id),
            'source' => $source,
            'ambition' => $customerFactor,
        ];
    }

    /**
     * BASE / PRO / PREMIUM around the list price. Researched tiers win; without
     * them the ladder is derived from the monthly price and the backlog is cut
     * into three cumulative feature sets, so there is always something to
     * present and the dashboard says which of the two it is showing.
     *
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>|null  $research
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function packaging(array $quote, array $profile, array $inception, ?array $research, array $overrides): array
    {
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
        $anchor = max(1.0, (float) ($saas['price_monthly'] ?? 29));
        $researched = [];

        foreach (is_array($research['tiers'] ?? null) ? $research['tiers'] : [] as $tier) {
            $researched[(string) $tier['id']] = $tier;
        }

        $features = $this->tierFeatures();
        $selectedId = (string) ($overrides['tier'] ?? 'pro');
        $tiers = [];
        $mixRevenue = 0.0;
        $shareTotal = 0.0;
        $cumulative = 0;

        foreach (self::TIER_MULTIPLIERS as $id => $multiplier) {
            $row = $researched[$id] ?? null;
            $price = $row !== null && ($row['price_monthly'] ?? null) !== null
                ? (float) $row['price_monthly']
                : $this->listPriceFor($anchor * $multiplier);
            $annual = $row !== null && ($row['price_annual'] ?? null) !== null
                ? (float) $row['price_annual']
                : round($price * 10, 2);
            $share = $row !== null && ($row['share_pct'] ?? null) !== null
                ? (float) $row['share_pct']
                : self::TIER_MIX[$id];

            [$contribution, $fixed] = $this->subscriptionEconomics($price, $profile, $quote, $inception);
            $tierFeatures = $row !== null && $row['features'] !== [] ? $row['features'] : ($features[$id] ?? []);

            $cumulative += count($tierFeatures);

            $tiers[$id] = [
                'id' => $id,
                'name' => $row['name'] ?? strtoupper($id),
                'price_monthly' => round($price, 2),
                'price_annual' => round($annual, 2),
                'annual_discount_pct' => $price > 0 ? round((1 - ($annual / ($price * 12))) * 100, 1) : 0.0,
                'share_pct' => round($share, 1),
                'contribution_per_customer' => round($contribution, 2),
                'break_even_customers' => $contribution > 0 ? (int) ceil($fixed / $contribution) : 0,
                'customers_to_recover_12m' => $contribution > 0
                    ? (int) ceil((((float) $quote['gross']) / 12 + $fixed) / $contribution)
                    : 0,
                'features' => $tierFeatures,
                'adds' => count($tierFeatures),
                'includes' => $cumulative,
                'note' => $row['note'] ?? $this->tierNote($id),
                'source' => $row !== null ? 'research' : 'derived',
                'selected' => $id === $selectedId,
            ];

            $mixRevenue += $price * $share;
            $shareTotal += $share;
        }

        $blended = $shareTotal > 0 ? $mixRevenue / $shareTotal : $anchor;
        $selected = $tiers[$selectedId] ?? $tiers['pro'];

        return [
            'source' => $researched !== [] ? 'research' : 'derived',
            'anchor_price' => round($anchor, 2),
            'selected_tier' => $selected['id'],
            'selected' => $selected,
            'tiers' => array_values($tiers),
            'blended_arpu' => round($blended, 2),
            'blended_arr_per_100' => round($blended * 100 * 12, 2),
            'positioning' => $this->pricePositioning($research, $selected['price_monthly']),
            'features_source' => $researched !== [] ? 'research' : 'backlog',
        ];
    }

    /**
     * Cut the backlog into three cumulative feature sets. Backlog order is the
     * only priority signal Larapilot has, so BASE is what was specified first.
     *
     * @return array<string, list<string>>
     */
    protected function tierFeatures(): array
    {
        $titles = [];

        foreach ($this->specs->allSpecs() as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $title = trim((string) ($spec['title'] ?? ''));

            if ($title !== '') {
                $titles[] = $title;
            }
        }

        if ($titles === []) {
            return ['base' => [], 'pro' => [], 'premium' => []];
        }

        $total = count($titles);
        $baseCount = max(1, (int) round($total * 0.45));
        $proCount = max(1, (int) round($total * 0.35));

        return [
            'base' => array_slice($titles, 0, $baseCount),
            'pro' => array_slice($titles, $baseCount, $proCount),
            'premium' => array_slice($titles, $baseCount + $proCount),
        ];
    }

    protected function tierNote(string $tier): string
    {
        return match ($tier) {
            'base' => $this->t('svc.tier.note.base'),
            'premium' => $this->t('svc.tier.note.premium'),
            default => $this->t('svc.tier.note.pro'),
        };
    }

    /**
     * A price a buyer recognises. Sitting between two shelf prices reads as
     * arbitrary, so the ladder snaps to the nearest one below a thousand.
     */
    protected function listPriceFor(float $value): float
    {
        if ($value >= 1000) {
            return round($value / 100) * 100 - 1;
        }

        $ladder = [9, 12, 15, 19, 24, 29, 39, 49, 59, 69, 79, 99, 129, 149, 179, 199, 249, 299, 349, 399, 499, 599, 699, 799, 899, 999];
        $best = $ladder[0];

        foreach ($ladder as $candidate) {
            if (abs($candidate - $value) < abs($best - $value)) {
                $best = $candidate;
            }
        }

        return (float) $best;
    }

    /**
     * Where the selected price sits in the researched competitor set.
     *
     * @param  array<string, mixed>|null  $research
     * @return array<string, mixed>|null
     */
    protected function pricePositioning(?array $research, float $price): ?array
    {
        $prices = [];

        foreach (is_array($research['competitors'] ?? null) ? $research['competitors'] : [] as $competitor) {
            if (($competitor['price_monthly'] ?? null) !== null) {
                $prices[] = (float) $competitor['price_monthly'];
            }
        }

        if ($prices === []) {
            return null;
        }

        sort($prices);
        $cheaper = count(array_filter($prices, static fn (float $value): bool => $value < $price));
        $median = $prices[(int) floor((count($prices) - 1) / 2)];

        return [
            'competitors' => count($prices),
            'cheaper_than_us' => $cheaper,
            'pricier_than_us' => count($prices) - $cheaper,
            'percentile' => (int) round($cheaper / count($prices) * 100),
            'min' => round($prices[0], 2),
            'median' => round($median, 2),
            'max' => round(end($prices), 2),
            'average' => round(array_sum($prices) / count($prices), 2),
            'delta_vs_median_pct' => $median > 0 ? round(($price / $median - 1) * 100, 1) : null,
        ];
    }

    /**
     * Pessimistic / realistic / optimistic on the selected price line: the same
     * product, three readings of the market. Each line is a full 36-month
     * forecast, so switching the price recomputes all three.
     *
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>  $tax
     * @param  array<string, mixed>|null  $research
     * @param  array<string, mixed>  $packaging
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function businessPlan(array $quote, array $profile, array $inception, array $tax, ?array $research, array $packaging, string $selectedScenario, array $overrides = []): array
    {
        $price = (float) $packaging['selected']['price_monthly'];
        [$contribution, $fixed] = $this->subscriptionEconomics($price, $profile, $quote, $inception);
        $investment = (float) $quote['gross'];
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
        $start = max(0, (int) ($saas['starting_customers'] ?? 0));
        $lines = [];

        foreach (EconomicsMarketService::SCENARIOS as $id) {
            $demand = $this->scenarioParams($profile, $research, $id, $overrides);
            $churn = max(0.5, $demand['churn_monthly_pct']) / 100;
            $growth = max(0.0, $demand['growth_monthly_pct']) / 100;
            // With no researched customer count, the three lines still have to
            // differ in ambition, not only in growth and churn.
            $target = $demand['customers'] > 0
                ? $demand['customers']
                : max(1, (int) round(($contribution > 0 ? ceil(($investment / 24 + $fixed) / $contribution) : 25) * $demand['ambition']));

            $forecast = $this->forecastSaaS($start, $target, $growth, $churn, $price, $contribution, $fixed, $investment, $tax);
            $lines[] = $this->planLine($id, $demand, $forecast, $price, $contribution, $fixed, $target) + [
                'selected' => $id === $selectedScenario,
                // Below this line the customer base cannot grow: the product
                // loses accounts faster than it wins them, whatever the target.
                'shrinking' => $growth <= $churn,
            ];
        }

        return [
            'selected' => $selectedScenario,
            'tier' => $packaging['selected']['id'],
            'tier_name' => $packaging['selected']['name'],
            'price_monthly' => round($price, 2),
            'contribution_per_customer' => round($contribution, 2),
            'fixed_monthly' => round($fixed, 2),
            'investment' => round($investment, 2),
            'source' => is_array($research['demand'] ?? null) && $research['demand'] !== [] ? 'research' : 'derived',
            'lines' => $lines,
            'notes' => $this->businessPlanNotes($research),
        ];
    }

    /**
     * @param  array<string, mixed>  $demand
     * @param  list<array<string, mixed>>  $forecast
     * @return array<string, mixed>
     */
    protected function planLine(string $id, array $demand, array $forecast, float $price, float $contribution, float $fixed, int $target): array
    {
        $at = static function (array $rows, int $month): ?array {
            foreach ($rows as $row) {
                if ((int) $row['month'] === $month) {
                    return $row;
                }
            }

            return null;
        };

        $recovered = null;
        $profitable = null;

        foreach ($forecast as $row) {
            if ($profitable === null && (float) $row['profit'] > 0) {
                $profitable = (int) $row['month'];
            }

            if ($recovered === null && ! empty($row['recovered'])) {
                $recovered = (int) $row['month'];
            }
        }

        $m12 = $at($forecast, 12);
        $m24 = $at($forecast, 24);
        $m36 = $at($forecast, 36);

        return [
            'id' => $id,
            'label' => $this->t('svc.plan.line.'.$id),
            'source' => $demand['source'],
            'note' => $demand['note'],
            'target_customers' => $target,
            'growth_monthly_pct' => $demand['growth_monthly_pct'],
            'churn_monthly_pct' => $demand['churn_monthly_pct'],
            'conversion_pct' => $demand['conversion_pct'],
            'leads_needed' => $demand['conversion_pct'] > 0 ? (int) ceil($target / ($demand['conversion_pct'] / 100)) : 0,
            'break_even_customers' => $contribution > 0 ? (int) ceil($fixed / $contribution) : 0,
            'customers_m12' => (int) ($m12['customers'] ?? 0),
            'customers_m24' => (int) ($m24['customers'] ?? 0),
            'customers_m36' => (int) ($m36['customers'] ?? 0),
            'mrr_m12' => (float) ($m12['mrr'] ?? 0),
            'mrr_m36' => (float) ($m36['mrr'] ?? 0),
            'arr_m12' => (float) ($m12['arr'] ?? 0),
            'arr_m24' => (float) ($m24['arr'] ?? 0),
            'arr_m36' => (float) ($m36['arr'] ?? 0),
            'cumulative_m36' => (float) ($m36['cumulative'] ?? 0),
            'profitable_month' => $profitable,
            'recovered_month' => $recovered,
            'forecast' => $forecast,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $research
     * @return list<string>
     */
    protected function businessPlanNotes(?array $research): array
    {
        if (is_array($research['demand'] ?? null) && $research['demand'] !== []) {
            $notes = [$this->t('svc.plan.notes.research', ['path' => $research['path'] ?? '.larapilot/economics.market.yaml'])];

            if (($research['sector'] ?? null) !== null) {
                $notes[] = $this->t('svc.plan.notes.sector', [
                    'sector' => $research['sector'].(($research['segment'] ?? null) !== null ? ' · '.$research['segment'] : ''),
                ]);
            }

            return $notes;
        }

        return [
            $this->t('svc.plan.notes.derived'),
            $this->t('svc.plan.notes.research_hint'),
        ];
    }

    /**
     * Market research as the dashboard consumes it: the sector, the competitor
     * set with its price trend, and whether the file still matches the project.
     *
     * @param  array<string, mixed>|null  $research
     * @return array<string, mixed>
     */
    protected function marketBlock(?array $research, string $productModel = 'fixed'): array
    {
        if ($research === null) {
            // A one-off client delivery never reaches the research step of
            // /larapilot-economics unless the user asks for it, so the hint
            // says which of the two situations this is.
            return [
                'available' => false,
                'path' => $this->config->relativePath($this->market->path()),
                'hint' => $this->t($productModel === 'fixed' ? 'svc.market.hint.fixed' : 'svc.market.hint'),
            ];
        }

        $competitors = is_array($research['competitors'] ?? null) ? $research['competitors'] : [];
        $trend = ['up' => 0, 'flat' => 0, 'down' => 0];
        $changes = [];

        foreach ($competitors as $competitor) {
            $direction = $competitor['trend'] ?? null;

            if ($direction !== null && isset($trend[$direction])) {
                $trend[$direction]++;
            }

            if (($competitor['change_pct'] ?? null) !== null) {
                $changes[] = (float) $competitor['change_pct'];
            }
        }

        $tracked = array_sum($trend);

        return [
            'available' => true,
            'path' => $research['path'] ?? $this->config->relativePath($this->market->path()),
            'sector' => $research['sector'] ?? null,
            'segment' => $research['segment'] ?? null,
            'summary' => $research['summary'] ?? null,
            'researched_at' => $research['researched_at'] ?? null,
            'stale' => ($research['inputs'] ?? null) !== null && $research['inputs'] !== $this->inputsFingerprint(),
            'competitors' => $competitors,
            'sources' => is_array($research['sources'] ?? null) ? $research['sources'] : [],
            'risks' => is_array($research['risks'] ?? null) ? $research['risks'] : [],
            'trend' => $trend + [
                'tracked' => $tracked,
                'direction' => $tracked === 0
                    ? null
                    : ($trend['up'] > $trend['down'] ? 'up' : ($trend['down'] > $trend['up'] ? 'down' : 'flat')),
                'average_change_pct' => $changes === [] ? null : round(array_sum($changes) / count($changes), 1),
            ],
        ];
    }

    /**
     * Every dropdown the pricing tool renders, with the values it may take.
     * The current value is always in its own list, so a profile that sits
     * between two suggestions still shows what it actually is.
     *
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $effort
     * @param  array<string, mixed>  $packaging
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    protected function controls(array $profile, string $account, string $country, array $effort, array $packaging, array $overrides, string $productModel, array $saved = [], float $recommendedMaintenance = 15.0): array
    {
        $this->savedControlValues = $saved;

        $currency = (string) ($profile['currency'] ?: TaxCatalog::defaultCurrency($country));
        $catalogueRate = TaxCatalog::defaultHourlyRate($country, $account);
        $rate = (float) $profile['hourly_rate'];
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
        $money = fn (float $value): string => $this->money($value, $currency);

        $rateOptions = [];
        foreach ([0.7, 0.85, 1.0, 1.25, 1.6] as $factor) {
            $rateOptions[] = max(1.0, round($catalogueRate * $factor / 5) * 5);
        }

        $teamOptions = [1.0, 1.5, 2.0, 3.0, 4.0];
        if ((float) ($effort['person_years'] ?? 0) > 1.0) {
            $teamOptions[] = round((float) $effort['person_years'], 1);
        }

        $priceOptions = [9.0, 19.0, 29.0, 49.0, 79.0, 99.0, 149.0, 199.0];

        return [
            'currency' => $currency,
            'overridden' => array_keys($overrides),
            'groups' => [
                'quote' => 'The quote',
                'account' => 'Who is selling',
                'saas' => 'Subscription',
            ],
            'controls' => [
                $this->control('hourly_rate', 'Hourly rate', 'quote', $rate, $rateOptions, $overrides, fn (float $v): string => $money($v).'/h', 'What one billable hour costs the client. Everything in the quote scales with it.'),
                $this->control('margin_target_pct', 'Target margin', 'quote', (float) $profile['margin_target_pct'], [20.0, 25.0, 30.0, 35.0, 45.0], $overrides, fn (float $v): string => $v.'%', 'Markup on labour and overhead: the buffer that absorbs scope creep and unpaid days.'),
                $this->control('discount_pct', 'Commercial discount', 'quote', (float) ($profile['discount_pct'] ?? 0), [0.0, 5.0, 10.0, 15.0, 20.0, 25.0, 30.0], $overrides, fn (float $v): string => $v > 0 ? '−'.$v.'%' : 'No discount', 'Taken off the list price at the negotiating table. It comes straight out of your margin.'),
                $this->control('team_size', 'Team size', 'quote', (float) ($profile['team_size'] ?? 1), $teamOptions, $overrides, fn (float $v): string => $v == 1.0 ? '1 person' : $v.' people', 'How many people work in parallel. It compresses the timeline, not the price: overhead follows person-months.'),
                $this->control('overhead_monthly', 'Monthly overhead', 'quote', (float) $profile['overhead_monthly'], [0.0, 150.0, 250.0, 500.0, 800.0, 1500.0], $overrides, $money, 'Tools, workspace, accountant share — charged per person-month of the project.'),
                $this->control('maintenance_annual_pct', 'Maintenance', 'quote', (float) $profile['maintenance_annual_pct'], [0.0, 10.0, 15.0, 20.0, 25.0, $recommendedMaintenance], $overrides, fn (float $v): string => $v.'% / year'.($v === $recommendedMaintenance ? ' · inception' : ''), 'Yearly retainer as a share of the build. The inception answers — delivery target, who runs the server, how it ships, support window — recommend '.$recommendedMaintenance.'%.'),
                $this->choice('account', 'Account type', 'account', $account, [
                    ['value' => 'FREELANCE', 'label' => 'Freelance / sole trader'],
                    ['value' => 'COMPANY', 'label' => 'Company (SRL, Ltd, GmbH)'],
                ], $overrides, 'Who invoices the client. It changes the regimes available and the tax on what you keep.'),
                $this->choice('country', 'Country', 'account', $country, array_map(
                    static fn (array $option): array => ['value' => $option['code'], 'label' => $option['name']],
                    $this->countryOptions()
                ), $overrides, 'Tax residency of the account. Brackets, VAT, and the accountant bill all follow it.'),
                $this->choice('regime', 'Tax regime', 'account', (string) ($profile['regime'] ?? ''), array_map(
                    static fn (array $option): array => ['value' => $option['id'], 'label' => $option['label']],
                    $this->regimeOptions($country, $account)
                ), $overrides, 'The regime the account is taxed under — forfettario, ordinario, corporate.'),
                $this->choice('vat_mode', 'VAT', 'account', (string) ($profile['vat_mode'] ?? 'domestic'), [
                    ['value' => 'domestic', 'label' => 'Domestic VAT'],
                    ['value' => 'eu_b2b', 'label' => 'EU B2B reverse charge'],
                ], $overrides, 'Whether VAT is invoiced or the client accounts for it.'),
                $this->choice('product_model', 'Sold as', 'account', $productModel, [
                    ['value' => 'fixed', 'label' => 'One shot — fixed price'],
                    ['value' => 'saas', 'label' => 'SaaS — subscription'],
                    ['value' => 'ecommerce', 'label' => 'E-commerce'],
                    ['value' => 'package', 'label' => 'Licensed package'],
                ], $overrides, 'How the project makes money. It decides which half of this page is the real one.'),
                $this->control('price_monthly', 'List price / month', 'saas', (float) ($packaging['anchor_price'] ?? ($saas['price_monthly'] ?? 29)), $priceOptions, $overrides, $money, 'The PRO price. BASE and PREMIUM are derived from it unless the research sets them.'),
                $this->choice('tier', 'Price line', 'saas', (string) ($packaging['selected_tier'] ?? 'pro'), array_map(
                    static fn (array $tier): array => ['value' => $tier['id'], 'label' => $tier['name'].' · '.number_format((float) $tier['price_monthly'], 0, '.', ',')],
                    is_array($packaging['tiers'] ?? null) ? $packaging['tiers'] : []
                ), $overrides, 'Which plan the forecast below runs on. Switch it and every projection recomputes.'),
                $this->choice('scenario', 'Market scenario', 'saas', (string) ($overrides['scenario'] ?? 'realistic'), [
                    ['value' => 'pessimistic', 'label' => 'Pessimistic'],
                    ['value' => 'realistic', 'label' => 'Realistic'],
                    ['value' => 'optimistic', 'label' => 'Optimistic'],
                ], $overrides, 'Which reading of the market drives the headline subscription numbers.'),
                $this->control('churn_monthly_pct', 'Churn / month', 'saas', (float) ($saas['churn_monthly_pct'] ?? 4), [2.0, 3.0, 4.0, 6.0, 8.0], $overrides, fn (float $v): string => $v.'%', 'Share of paying customers lost every month.'),
                $this->control('growth_monthly_pct', 'Growth / month', 'saas', (float) ($saas['growth_monthly_pct'] ?? 12), [4.0, 8.0, 12.0, 20.0, 30.0], $overrides, fn (float $v): string => $v.'%', 'How fast the customer base grows before churn.'),
                $this->control('target_customers', 'Planning customers', 'saas', (float) ($saas['target_customers'] ?? 0), [0.0, 25.0, 50.0, 100.0, 250.0, 500.0], $overrides, static fn (float $v): string => $v > 0 ? (string) (int) $v : 'Compute it', 'The customer count the plan aims at. Zero lets the engine derive one from the build cost.'),
            ],
        ];
    }

    /**
     * What each control would read with no simulation running.
     *
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $inception
     * @return array<string, mixed>
     */
    protected function savedControls(array $profile, string $account, array $inception): array
    {
        $saas = is_array($profile['saas'] ?? null) ? $profile['saas'] : [];
        $country = (string) ($profile['country'] ?? TaxCatalog::defaultCountry());

        return [
            'hourly_rate' => (float) $profile['hourly_rate'],
            'margin_target_pct' => (float) $profile['margin_target_pct'],
            'discount_pct' => (float) ($profile['discount_pct'] ?? 0),
            'team_size' => (float) ($profile['team_size'] ?? 1),
            'overhead_monthly' => (float) $profile['overhead_monthly'],
            'maintenance_annual_pct' => (float) $profile['maintenance_annual_pct'],
            'account' => $account,
            'country' => $country,
            'regime' => (string) ($profile['regime'] ?: TaxCatalog::defaultRegime($country, $account === 'NONE' ? 'FREELANCE' : $account)),
            'vat_mode' => (string) ($profile['vat_mode'] ?? 'domestic'),
            'product_model' => $this->resolveProductModel($profile, $inception),
            'price_monthly' => (float) ($saas['price_monthly'] ?? 29),
            'tier' => 'pro',
            'scenario' => 'realistic',
            'churn_monthly_pct' => (float) ($saas['churn_monthly_pct'] ?? 4),
            'growth_monthly_pct' => (float) ($saas['growth_monthly_pct'] ?? 12),
            'target_customers' => (float) ($saas['target_customers'] ?? 0),
        ];
    }

    /**
     * @param  list<float>  $options
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function control(string $key, string $label, string $group, float $value, array $options, array $overrides, callable $format, string $hint): array
    {
        $values = $options;
        $values[] = $value;
        $values = array_values(array_unique(array_map(static fn (float $v): float => round($v, 2), $values)));
        sort($values);

        return [
            'key' => $key,
            'label' => $label,
            'group' => $group,
            'hint' => $hint,
            'value' => round($value, 2),
            'saved' => round((float) ($this->savedControlValues[$key] ?? $value), 2),
            'overridden' => array_key_exists($key, $overrides),
            'options' => array_map(static fn (float $option): array => [
                'value' => $option,
                'label' => $format($option),
            ], $values),
        ];
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function choice(string $key, string $label, string $group, string $value, array $options, array $overrides, string $hint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'group' => $group,
            'hint' => $hint,
            'value' => $value,
            'saved' => (string) ($this->savedControlValues[$key] ?? $value),
            'overridden' => array_key_exists($key, $overrides),
            'options' => $options,
        ];
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
        $scale = $this->t('svc.server.scale.'.($customers > 200 ? 'large' : 'small'));
        $label = trim($platform) !== '' ? $platform : $this->t('svc.server.label.generic');

        return [
            $this->t('svc.server.baseline', ['label' => $label, 'infra' => number_format($infra, 0)]),
            $scale,
            $this->t('svc.server.traffic'),
            $this->t('svc.server.fees'),
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
