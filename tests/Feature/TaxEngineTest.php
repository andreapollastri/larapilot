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

    expect($tax['forced_exit'] ?? false)->toBeTrue()
        ->and($tax['regime'])->toBe('ordinario');
});
