<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\EconomicsService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\TaxCatalog;

class EconomicsSetCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:economics-set
                            {--country= : ISO country code (IT, DE, FR, ES, GB, IE, AT, CH, BE, NL, PT, SI, HR, NO, SE, DK, FI, IS, LU, MT, CY, PL, CZ, SK, HU, RO, BG, GR, EE, LV, LT, US, CA, AU, NZ, SG, JP, MX)}
                            {--regime= : Tax regime id (forfettario_15, srl, ltd, …)}
                            {--hourly-rate= : Billable hourly rate in the account currency}
                            {--currency= : ISO currency (EUR, GBP, USD, CHF)}
                            {--billable-days= : Billable days per year (default 220)}
                            {--hours-per-day= : Billable hours per day (default 6)}
                            {--margin= : Target margin percent on direct cost}
                            {--discount= : Commercial discount percent taken off the list price (0-90)}
                            {--team-size= : People working in parallel — compresses the timeline, not the price}
                            {--maintenance= : Annual maintenance as percent of the build quote}
                            {--overhead-monthly= : Monthly overhead (tools, office, accountant share)}
                            {--vat-registered= : YES or NO (blank = infer from the regime)}
                            {--vat-mode= : domestic or eu_b2b (reverse charge, no VAT on invoice)}
                            {--owner-working= : YES or NO — working shareholder in a company (default YES for COMPANY)}
                            {--extraction= : auto, dividends, or mixed (company extraction strategy)}
                            {--product-model= : auto, fixed, saas, ecommerce, or package}
                            {--price-monthly= : SaaS list price per month}
                            {--price-annual= : SaaS list price per year}
                            {--churn= : Monthly SaaS churn percent}
                            {--target-customers= : Planning customer count (0 = compute)}
                            {--growth= : Monthly SaaS growth percent}
                            {--infra-monthly= : Hosting baseline (0 = infer from deploy platform)}
                            {--support-per-customer= : Monthly support cost per paying customer}
                            {--payment-fee= : Payment processor percent}
                            {--cac= : Customer acquisition cost (0 = 28% of LTV)}
                            {--starting-customers= : Paying customers at month 0}';

    protected $description = 'Persist the Economics account profile into .larapilot/economics.yaml';

    /**
     * Accepted range per numeric flag. Anything else is refused instead of
     * being cast to 0 and quietly written into the profile.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    protected const NUMERIC_RANGES = [
        'hourly-rate' => [1.0, 5000.0],
        'billable-days' => [1.0, 366.0],
        'hours-per-day' => [1.0, 24.0],
        'margin' => [0.0, 300.0],
        'discount' => [0.0, 90.0],
        'team-size' => [0.25, 50.0],
        'maintenance' => [0.0, 100.0],
        'overhead-monthly' => [0.0, 1000000.0],
        'price-monthly' => [0.0, 100000.0],
        'price-annual' => [0.0, 1000000.0],
        'churn' => [0.0, 100.0],
        'target-customers' => [0.0, 10000000.0],
        'growth' => [0.0, 200.0],
        'infra-monthly' => [0.0, 1000000.0],
        'support-per-customer' => [0.0, 100000.0],
        'payment-fee' => [0.0, 50.0],
        'cac' => [0.0, 1000000.0],
        'starting-customers' => [0.0, 10000000.0],
    ];

    public function handle(ConfigService $config, EconomicsService $economics): int
    {
        if (! $config->accountEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Account mode is NONE (settings.account = NONE).',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --account=FREELANCE  or  --account=COMPANY'
            );
        }

        $invalid = $this->numericViolations();

        if ($invalid !== []) {
            return $this->failure(
                'E_INVALID_INPUT',
                implode(' ', $invalid),
                $this->exitForCode('E_INVALID_INPUT'),
                'Pass plain numbers, e.g. --hourly-rate=55 --margin=30 --churn=4.'
            );
        }

        $partial = [];
        $saas = [];

        $country = $this->option('country');
        if (is_string($country) && trim($country) !== '') {
            try {
                $code = TaxCatalog::normalizeCountry($country);
                TaxCatalog::country($code);
                $partial['country'] = $code;
            } catch (\InvalidArgumentException $e) {
                return $this->failure('E_INVALID_INPUT', $e->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
            }
        }

        $regime = $this->option('regime');
        if (is_string($regime) && trim($regime) !== '') {
            $partial['regime'] = trim($regime);
        }

        foreach ([
            'hourly-rate' => 'hourly_rate',
            'currency' => 'currency',
            'billable-days' => 'billable_days_per_year',
            'hours-per-day' => 'hours_per_day',
            'margin' => 'margin_target_pct',
            'discount' => 'discount_pct',
            'team-size' => 'team_size',
            'maintenance' => 'maintenance_annual_pct',
            'overhead-monthly' => 'overhead_monthly',
        ] as $option => $key) {
            $value = $this->option($option);
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            if ($option === 'currency') {
                $currency = strtoupper(trim((string) $value));

                if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                    return $this->failure(
                        'E_INVALID_INPUT',
                        'Invalid --currency: expected a 3-letter ISO code (EUR, GBP, USD, CHF, …).',
                        $this->exitForCode('E_INVALID_INPUT')
                    );
                }

                $partial[$key] = $currency;

                continue;
            }

            $partial[$key] = (float) $value;
        }

        $vat = $this->normalizeYesNoOption('vat-registered');
        if ($vat !== null) {
            $partial['vat_registered'] = $vat;
        }

        $vatMode = $this->option('vat-mode');
        if (is_string($vatMode) && trim($vatMode) !== '') {
            $partial['vat_mode'] = strtolower(trim($vatMode));
        }

        $ownerWorking = $this->normalizeYesNoOption('owner-working');
        if ($ownerWorking !== null) {
            $partial['owner_working'] = $ownerWorking;
        }

        $extraction = $this->option('extraction');
        if (is_string($extraction) && trim($extraction) !== '') {
            $partial['extraction'] = strtolower(trim($extraction));
        }

        $model = $this->option('product-model');
        if (is_string($model) && trim($model) !== '') {
            $partial['product_model'] = strtolower(trim($model));
        }

        foreach ([
            'price-monthly' => 'price_monthly',
            'price-annual' => 'price_annual',
            'churn' => 'churn_monthly_pct',
            'target-customers' => 'target_customers',
            'growth' => 'growth_monthly_pct',
            'infra-monthly' => 'infrastructure_monthly',
            'support-per-customer' => 'support_cost_per_customer_monthly',
            'payment-fee' => 'payment_fee_pct',
            'cac' => 'cac',
            'starting-customers' => 'starting_customers',
        ] as $option => $key) {
            $value = $this->option($option);
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            $saas[$key] = in_array($key, ['target_customers', 'starting_customers'], true)
                ? (int) $value
                : (float) $value;
        }

        if ($saas !== []) {
            $partial['saas'] = $saas;
        }

        if ($partial === []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide at least one economics flag (--country, --regime, --hourly-rate, …).',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        try {
            $profile = $economics->update($partial);
            $snapshot = $economics->snapshot();
        } catch (\InvalidArgumentException $e) {
            return $this->failure('E_INVALID_INPUT', $e->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        return $this->success('economics', [
            'profile' => $profile,
            'account' => $config->accountMode(),
            'updated' => array_keys($partial),
            'path' => $economics->path(),
            'snapshot_path' => $snapshot['snapshot_path'] ?? null,
            'snapshot_saved_at' => $snapshot['snapshot_saved_at'] ?? null,
            'quote' => $snapshot['quote'] ?? null,
            'hint' => 'Open /larapilot/economics or run larapilot:economics-show',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function numericViolations(): array
    {
        $violations = [];

        foreach (self::NUMERIC_RANGES as $option => [$min, $max]) {
            $raw = $this->option($option);

            if ($raw === null || $raw === false || $raw === '') {
                continue;
            }

            if (! is_numeric($raw)) {
                $violations[] = '--'.$option.' must be a number ("'.(string) $raw.'" given).';

                continue;
            }

            $value = (float) $raw;

            if ($value < $min || $value > $max) {
                $violations[] = '--'.$option.' must be between '
                    .rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.').' and '
                    .rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.').' ('.(string) $raw.' given).';
            }
        }

        return $violations;
    }

    protected function normalizeYesNoOption(string $name): ?bool
    {
        $raw = $this->option($name);

        if ($raw === null || $raw === false || $raw === '') {
            return null;
        }

        $normalized = strtoupper(trim((string) $raw));

        if (in_array($normalized, ['YES', 'SI', 'TRUE', 'ON', '1'], true)) {
            return true;
        }

        if (in_array($normalized, ['NO', 'FALSE', 'OFF', '0'], true)) {
            return false;
        }

        return null;
    }
}
