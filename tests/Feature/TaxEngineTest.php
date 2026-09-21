<?php

declare(strict_types=1);

use Larapilot\Support\TaxCatalog;
use Larapilot\Support\TaxEngine;

it('deducts INPS from the forfettario substitute-tax base', function (): void {
    $regime = TaxCatalog::regime('IT', 'FREELANCE', 'forfettario_15');
    $country = TaxCatalog::country('IT');

    $tax = TaxEngine::compute(87126, 7403, $regime, $country, 'FREELANCE', [
        'year_fraction' => 1.0,
        'compliance' => 1200,
    ]);

    $presocial = 87126 * 0.67;

    expect($tax['social'])->toBeGreaterThan(12000)
        ->and($tax['taxable'])->toBeLessThan($presocial)
        ->and($tax['taxable'])->toBeGreaterThan($presocial * 0.70)
        ->and($tax['net_to_owner'])->toBeLessThan(87126 - 7403 - ($presocial * 0.15));
});

it('models an Italian SRL with INPS commercianti and legal reserve', function (): void {
    $regime = TaxCatalog::regime('IT', 'COMPANY', 'srl');
    $country = TaxCatalog::country('IT');

    $tax = TaxEngine::compute(87126, 7403, $regime, $country, 'COMPANY', [
        'year_fraction' => 1.0,
        'compliance' => 5500,
        'owner_working' => true,
        'extraction' => 'dividends',
    ]);

    expect($tax['social'])->toBeGreaterThan(4000)
        ->and($tax['legal_reserve'])->toBeGreaterThan(0)
        ->and($tax['dividend_tax'])->toBeGreaterThan(0)
        ->and($tax['net_to_owner'])->toBeLessThan(40600)
        ->and($tax['effective_rate_pct'])->toBeGreaterThan(45);
});

it('optimises company extraction with director pay when it beats dividends alone', function (): void {
    $regime = TaxCatalog::regime('IT', 'COMPANY', 'srl');
    $country = TaxCatalog::country('IT');
    $options = [
        'year_fraction' => 1.0,
        'compliance' => 5500,
        'owner_working' => true,
    ];

    $dividendsOnly = TaxEngine::compute(87126, 7403, $regime, $country, 'COMPANY', $options + ['extraction' => 'dividends']);
    $auto = TaxEngine::compute(87126, 7403, $regime, $country, 'COMPANY', $options + ['extraction' => 'auto']);

    expect($auto['net_to_owner'])->toBeGreaterThanOrEqual($dividendsOnly['net_to_owner'])
        ->and($auto['extraction']['method'] ?? 'dividends')->toBeIn(['mixed', 'dividends']);
});

it('forces forfettario exit above the hard cap into IRPEF ordinario', function (): void {
    $regime = TaxCatalog::regime('IT', 'FREELANCE', 'forfettario_15');
    $country = TaxCatalog::country('IT');

    $tax = TaxEngine::compute(105000, 5000, $regime, $country, 'FREELANCE', [
        'year_fraction' => 1.0,
        'compliance' => 1200,
    ]);

    $ordinario = TaxCatalog::regime('IT', 'FREELANCE', 'ordinario');

    expect($tax['forced_exit'] ?? false)->toBeTrue()
        ->and($tax['regime'])->toBe('ordinario')
        // …including that regime's heavier accountant, not the forfettario one.
        ->and($tax['compliance'])->toBe((float) $ordinario['compliance_annual'])
        ->and($tax['net_to_owner'])->toBe(round(105000 - 5000 - $tax['total_withheld'], 2));
});

it('charges compliance to the owner and balances revenue against what is withheld', function (): void {
    $country = TaxCatalog::country('IT');
    $revenue = 60000.0;
    $costs = 3000.0;

    foreach ([
        ['FREELANCE', 'forfettario_15'],
        ['FREELANCE', 'ordinario'],
        ['COMPANY', 'srl'],
    ] as [$account, $id]) {
        $regime = TaxCatalog::regime('IT', $account, $id);
        $tax = TaxEngine::compute($revenue, $costs, $regime, $country, $account, [
            'year_fraction' => 1.0,
            'owner_working' => true,
        ]);

        // The accountant is a real bill: it must show up between revenue and net.
        expect($tax['compliance'])->toBeGreaterThan(0)
            ->and($tax['total_withheld'])
            ->toBe(round($tax['total_tax'] + $tax['compliance'] + $tax['legal_reserve'], 2))
            ->and($tax['net_to_owner'])->toBe(round($revenue - $costs - $tax['total_withheld'], 2))
            ->and($tax['withheld_rate_pct'])->toBeGreaterThan($tax['effective_rate_pct'])
            ->and($tax['loss'])->toBeFalse();
    }
});

it('reports a loss instead of clamping it to zero', function (): void {
    $regime = TaxCatalog::regime('IT', 'COMPANY', 'srl');
    $country = TaxCatalog::country('IT');

    // A tiny job under a company: compliance and the INPS minimale alone
    // outweigh the fee.
    $tax = TaxEngine::compute(4000, 500, $regime, $country, 'COMPANY', [
        'year_fraction' => 1.0,
        'owner_working' => true,
    ]);

    expect($tax['loss'])->toBeTrue()
        ->and($tax['net_to_owner'])->toBeLessThan(0);
});

it('drops owner contributions for a shareholder who does not work in the company', function (): void {
    $regime = TaxCatalog::regime('IT', 'COMPANY', 'srl');
    $country = TaxCatalog::country('IT');
    $options = ['year_fraction' => 1.0, 'extraction' => 'dividends'];

    $working = TaxEngine::compute(90000, 5000, $regime, $country, 'COMPANY', $options + ['owner_working' => true]);
    $passive = TaxEngine::compute(90000, 5000, $regime, $country, 'COMPANY', $options + ['owner_working' => false]);

    expect($working['social'])->toBeGreaterThan(0)
        ->and($passive['social'])->toBe(0.0)
        ->and($passive['net_to_owner'])->toBeGreaterThan($working['net_to_owner']);
});

it('keeps every catalogue regime on its own currency scale', function (): void {
    // Rough FY-2026 units per EUR. Only the order of magnitude matters here:
    // the test exists to catch a compliance or hourly figure written on a EUR
    // scale for a HUF / JPY / ISK country, which is how they were introduced.
    $perEur = [
        'EUR' => 1.0, 'GBP' => 0.85, 'USD' => 1.08, 'CHF' => 0.95, 'NOK' => 11.5,
        'SEK' => 11.3, 'DKK' => 7.46, 'ISK' => 150.0, 'PLN' => 4.3, 'CZK' => 25.0,
        'HUF' => 395.0, 'RON' => 4.98, 'BGN' => 1.96, 'CAD' => 1.47, 'AUD' => 1.63,
        'NZD' => 1.78, 'SGD' => 1.45, 'JPY' => 163.0, 'MXN' => 20.5,
    ];

    $problems = [];

    foreach (TaxCatalog::countries() as $code => $meta) {
        $currency = (string) ($meta['currency'] ?? '');
        $rate = $perEur[$currency] ?? null;

        expect($rate)->not->toBeNull("no FX reference for {$code} ({$currency}) — add it to this test");

        foreach (['FREELANCE', 'COMPANY'] as $account) {
            $hourlyEur = (float) ($meta['hourly'][$account] ?? 0) / $rate;

            if ($hourlyEur < 15 || $hourlyEur > 250) {
                $problems[] = "{$code} {$account} hourly rate is ".round($hourlyEur).' EUR-equivalent';
            }

            foreach (TaxCatalog::regimesFor($code, $account) as $id => $regime) {
                $complianceEur = (float) ($regime['compliance_annual'] ?? 0) / $rate;
                $floor = $account === 'COMPANY' ? 1000 : 300;
                $ceiling = $account === 'COMPANY' ? 15000 : 4000;

                if ($complianceEur < $floor || $complianceEur > $ceiling) {
                    $problems[] = "{$code} {$account} {$id} compliance is ".round($complianceEur).' EUR-equivalent';
                }
            }
        }
    }

    expect($problems)->toBe([]);
});

it('charges the flat social bill on regimes where contributions are a fixed amount', function (): void {
    $regime = TaxCatalog::regime('PL', 'FREELANCE', 'ryczalt');
    $country = TaxCatalog::country('PL');

    $tax = TaxEngine::compute(240000, 12000, $regime, $country, 'FREELANCE', ['year_fraction' => 1.0]);

    // ZUS is a bill you pay whatever the revenue — it used to be missing entirely.
    expect($regime['social_fixed_annual'])->toBeGreaterThan(0)
        ->and($tax['social'])->toBe(20000.0)
        ->and($tax['taxable'])->toBeLessThan(240000 * 0.85);
});

it('prorates a fixed social bill over a part-year project', function (): void {
    $regime = TaxCatalog::regime('PL', 'FREELANCE', 'ryczalt');
    $country = TaxCatalog::country('PL');

    $half = TaxEngine::compute(120000, 6000, $regime, $country, 'FREELANCE', ['year_fraction' => 0.5]);

    expect($half['social'])->toBe(10000.0);
});
