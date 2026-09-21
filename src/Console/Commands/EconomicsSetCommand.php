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
                            {--country= : ISO country code (IT, DE, FR, ES, GB, US, NL, PT, CH, AT, BE, IE)}
                            {--regime= : Tax regime id (forfettario_15, srl, ltd, …)}
                            {--hourly-rate= : Billable hourly rate in the account currency}
                            {--currency= : ISO currency (EUR, GBP, USD, CHF)}
                            {--billable-days= : Billable days per year (default 220)}
                            {--hours-per-day= : Billable hours per day (default 6)}
                            {--margin= : Target margin percent on direct cost}
                            {--maintenance= : Annual maintenance as percent of the build quote}
                            {--overhead-monthly= : Monthly overhead (tools, office, accountant share)}
                            {--vat-registered= : YES or NO (blank = infer from the regime)}
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
            'maintenance' => 'maintenance_annual_pct',
            'overhead-monthly' => 'overhead_monthly',
        ] as $option => $key) {
            $value = $this->option($option);
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            $partial[$key] = $option === 'currency' ? strtoupper(trim((string) $value)) : $value;
        }

        $vat = $this->normalizeYesNoOption('vat-registered');
        if ($vat !== null) {
            $partial['vat_registered'] = $vat;
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

            $saas[$key] = $value;
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
        } catch (\InvalidArgumentException $e) {
            return $this->failure('E_INVALID_INPUT', $e->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        return $this->success('economics', [
            'profile' => $profile,
            'account' => $config->accountMode(),
            'updated' => array_keys($partial),
            'path' => $economics->path(),
            'hint' => 'Open /larapilot/economics or run larapilot:economics-show',
        ]);
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
