<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * Additional FY-2026 country snapshots merged into TaxCatalog.
 * Planning approximations — not personalised tax advice.
 */
final class TaxCatalogExtra
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function countries(): array
    {
        return [
            'SI' => self::country('Slovenia', 'EUR', 22.0, 45.0, 65.0, [
                'FREELANCE' => [
                    'normirani' => self::flat('Normirani / flat 20% + social', 0.20, 0.221, 0.60, 1200, true, 50000),
                    'progressive' => self::progressive('Progressive + social', [
                        ['up_to' => 8500, 'rate' => 0.16],
                        ['up_to' => 25000, 'rate' => 0.26],
                        ['up_to' => 50000, 'rate' => 0.33],
                        ['up_to' => 72000, 'rate' => 0.39],
                        ['up_to' => null, 'rate' => 0.50],
                    ], 0.221, 1800),
                ],
                'COMPANY' => [
                    'doo' => self::corporate('d.o.o. (DDV + dividend)', 0.19, 0.0, 0.25, 3200),
                ],
            ]),
            'HR' => self::country('Croatia', 'EUR', 25.0, 40.0, 55.0, [
                'FREELANCE' => [
                    'pausal' => self::flat('Paušal / flat income', 0.12, 0.20, 0.70, 800, true, 40000),
                    'progressive' => self::progressive('Progressive + social', [
                        ['up_to' => 50000, 'rate' => 0.20],
                        ['up_to' => null, 'rate' => 0.30],
                    ], 0.20, 1400),
                ],
                'COMPANY' => [
                    'doo' => self::corporate('d.o.o. (18% + dividend)', 0.18, 0.0, 0.12, 2800),
                ],
            ]),
            'NO' => self::country('Norway', 'NOK', 25.0, 850.0, 1100.0, [
                'FREELANCE' => [
                    'enk' => self::progressive('Enkeltpersonforetak', [
                        ['up_to' => 208050, 'rate' => 0.22],
                        ['up_to' => 292850, 'rate' => 0.23],
                        ['up_to' => 670000, 'rate' => 0.24],
                        ['up_to' => 967050, 'rate' => 0.245],
                        ['up_to' => null, 'rate' => 0.257],
                    ], 0.108, 12000),
                ],
                'COMPANY' => [
                    'as' => self::corporate('AS (22% CIT + dividend)', 0.22, 0.0, 0.3784, 45000),
                ],
            ]),
            'SE' => self::country('Sweden', 'SEK', 25.0, 750.0, 950.0, [
                'FREELANCE' => [
                    'enskild' => self::progressive('Enskild firma', [
                        ['up_to' => 573000, 'rate' => 0.0],
                        ['up_to' => null, 'rate' => 0.20],
                    ], 0.2897, 12000),
                ],
                'COMPANY' => [
                    'ab' => self::corporate('AB (20.6% CIT + dividend)', 0.206, 0.0, 0.30, 45000),
                ],
            ]),
            'DK' => self::country('Denmark', 'DKK', 25.0, 650.0, 850.0, [
                'FREELANCE' => [
                    'enkeltmand' => self::progressive('Enkeltmandsvirksomhed', [
                        ['up_to' => 49700, 'rate' => 0.37],
                        ['up_to' => null, 'rate' => 0.52],
                    ], 0.0, 10000),
                ],
                'COMPANY' => [
                    'aps' => self::corporate('ApS (22% CIT + dividend)', 0.22, 0.0, 0.42, 35000),
                ],
            ]),
            'FI' => self::country('Finland', 'EUR', 25.5, 60.0, 80.0, [
                'FREELANCE' => [
                    'toiminimi' => self::progressive('Toiminimi / YEL', [
                        ['up_to' => 20200, 'rate' => 0.1264],
                        ['up_to' => 30400, 'rate' => 0.1924],
                        ['up_to' => null, 'rate' => 0.3144],
                    ], 0.244, 2000),
                ],
                'COMPANY' => [
                    'oy' => self::corporate('Oy (20% CIT + dividend)', 0.20, 0.0, 0.28, 4500),
                ],
            ]),
            'IS' => self::country('Iceland', 'ISK', 24.0, 9000.0, 12000.0, [
                'FREELANCE' => [
                    'self_employed' => self::progressive('Self-employed', [
                        ['up_to' => 4460000, 'rate' => 0.3145],
                        ['up_to' => null, 'rate' => 0.4625],
                    ], 0.0635, 350000, 'Income tax bands plus 6.35% tryggingagjald on the calculated reference wage.'),
                ],
                'COMPANY' => [
                    'ehf' => self::corporate('ehf (20% CIT + dividend)', 0.20, 0.0, 0.22, 700000),
                ],
            ]),
            'LU' => self::country('Luxembourg', 'EUR', 17.0, 85.0, 110.0, [
                'FREELANCE' => [
                    'independent' => self::progressive('Independent + social', [
                        ['up_to' => 11265, 'rate' => 0.0],
                        ['up_to' => 14535, 'rate' => 0.08],
                        ['up_to' => 19995, 'rate' => 0.09],
                        ['up_to' => 32205, 'rate' => 0.11],
                        ['up_to' => null, 'rate' => 0.42],
                    ], 0.245, 3500),
                ],
                'COMPANY' => [
                    'sarl' => self::corporate('SARL (24.94% CIT + dividend)', 0.2494, 0.0, 0.26, 6000),
                ],
            ]),
            'MT' => self::country('Malta', 'EUR', 18.0, 45.0, 60.0, [
                'FREELANCE' => [
                    'sole_trader' => self::progressive('Sole trader', [
                        ['up_to' => 9100, 'rate' => 0.0],
                        ['up_to' => 14500, 'rate' => 0.15],
                        ['up_to' => 19500, 'rate' => 0.25],
                        ['up_to' => 60000, 'rate' => 0.25],
                        ['up_to' => null, 'rate' => 0.35],
                    ], 0.10, 1200),
                ],
                'COMPANY' => [
                    'ltd' => self::corporate('Ltd (35% headline / ref. regime)', 0.35, 0.0, 0.15, 3500,
                        'Malta effective rate varies strongly with refund mechanisms — 35% headline used as conservative planning default.'),
                ],
            ]),
            'CY' => self::country('Cyprus', 'EUR', 19.0, 45.0, 60.0, [
                'FREELANCE' => [
                    'self_employed' => self::progressive('Self-employed', [
                        ['up_to' => 19500, 'rate' => 0.0],
                        ['up_to' => 28000, 'rate' => 0.20],
                        ['up_to' => 36300, 'rate' => 0.25],
                        ['up_to' => 60000, 'rate' => 0.30],
                        ['up_to' => null, 'rate' => 0.35],
                    ], 0.166, 1500),
                ],
                'COMPANY' => [
                    'ltd' => self::corporate('Ltd (12.5% CIT + SDC)', 0.125, 0.0, 0.0265, 3000),
                ],
            ]),
            'PL' => self::country('Poland', 'PLN', 23.0, 180.0, 240.0, [
                'FREELANCE' => [
                    'ryczalt' => self::flat(
                        'Ryczałt (flat on revenue)',
                        0.12,
                        0.0,
                        0.85,
                        4500,
                        false,
                        0,
                        'Ryczałt 12% for IT services on 85% of revenue, plus the flat ZUS bill (social + health, ~20,000 PLN a year) which is deducted from the taxed base.',
                        20000.0,
                    ),
                    'scale' => self::progressive('Skala podatkowa + ZUS', [
                        ['up_to' => 120000, 'rate' => 0.12],
                        ['up_to' => null, 'rate' => 0.32],
                    ], 0.1952, 6000),
                ],
                'COMPANY' => [
                    'spzoo' => self::corporate('sp. z o.o. (19% CIT + dividend)', 0.19, 0.0, 0.19, 14000),
                ],
            ]),
            'CZ' => self::country('Czech Republic', 'CZK', 21.0, 1200.0, 1600.0, [
                'FREELANCE' => [
                    'osvc' => self::flat('OSVČ paušální / flat', 0.15, 0.292, 0.60, 15000, false, 0),
                    'progressive' => self::progressive('Progressive + social', [
                        ['up_to' => 1677000, 'rate' => 0.15],
                        ['up_to' => null, 'rate' => 0.23],
                    ], 0.292, 25000),
                ],
                'COMPANY' => [
                    'sro' => self::corporate('s.r.o. (21% CIT + dividend)', 0.21, 0.0, 0.15, 70000),
                ],
            ]),
            'SK' => self::country('Slovakia', 'EUR', 23.0, 40.0, 55.0, [
                'FREELANCE' => [
                    'zivnost' => self::progressive('Živnosť + social', [
                        ['up_to' => 49790, 'rate' => 0.19],
                        ['up_to' => null, 'rate' => 0.25],
                    ], 0.299, 1100),
                ],
                'COMPANY' => [
                    'sro' => self::corporate('s.r.o. (21% CIT + dividend)', 0.21, 0.0, 0.07, 2800),
                ],
            ]),
            'HU' => self::country('Hungary', 'HUF', 27.0, 18000.0, 24000.0, [
                'FREELANCE' => [
                    'ev' => self::flat('EV / KATA-style flat', 0.09, 0.185, 0.60, 400000, false, 0),
                ],
                'COMPANY' => [
                    'kft' => self::corporate('Kft (9% CIT + dividend)', 0.09, 0.0, 0.15, 1400000),
                ],
            ]),
            'RO' => self::country('Romania', 'RON', 19.0, 180.0, 240.0, [
                'FREELANCE' => [
                    'pfa' => self::flat('PFA / micro-style', 0.10, 0.25, 0.70, 4000, false, 0),
                ],
                'COMPANY' => [
                    'srl' => self::corporate('SRL (16% CIT + dividend)', 0.16, 0.0, 0.08, 14000),
                ],
            ]),
            'BG' => self::country('Bulgaria', 'BGN', 20.0, 45.0, 60.0, [
                'FREELANCE' => [
                    'sole_trader' => self::flat('Sole trader 10% + social', 0.10, 0.248, 0.75, 1400, false, 0),
                ],
                'COMPANY' => [
                    'ood' => self::corporate('OOD (10% CIT + dividend)', 0.10, 0.0, 0.05, 4000),
                ],
            ]),
            'GR' => self::country('Greece', 'EUR', 24.0, 40.0, 55.0, [
                'FREELANCE' => [
                    'freelancer' => self::progressive('Freelancer + EFKA', [
                        ['up_to' => 10000, 'rate' => 0.09],
                        ['up_to' => 20000, 'rate' => 0.22],
                        ['up_to' => 30000, 'rate' => 0.28],
                        ['up_to' => 40000, 'rate' => 0.36],
                        ['up_to' => null, 'rate' => 0.44],
                    ], 0.206, 1500),
                ],
                'COMPANY' => [
                    'ike' => self::corporate('IKE / AE (22% CIT + dividend)', 0.22, 0.0, 0.05, 3200),
                ],
            ]),
            'EE' => self::country('Estonia', 'EUR', 22.0, 55.0, 75.0, [
                'FREELANCE' => [
                    'fie' => self::flat('FIE / sole proprietor', 0.20, 0.33, 0.80, 900, false, 0),
                ],
                'COMPANY' => [
                    'ou' => self::corporate('OÜ (20% on distributions)', 0.0, 0.0, 0.20, 2800,
                        'Estonia taxes distributed profits at 20% (22% from 2025 on large regular distributions). Retained profits untaxed at entity level.'),
                ],
            ]),
            'LV' => self::country('Latvia', 'EUR', 21.0, 45.0, 60.0, [
                'FREELANCE' => [
                    'sole_trader' => self::progressive('Sole trader', [
                        ['up_to' => 20004, 'rate' => 0.20],
                        ['up_to' => null, 'rate' => 0.31],
                    ], 0.31, 1200),
                ],
                'COMPANY' => [
                    'sia' => self::corporate('SIA (20% CIT + dividend)', 0.20, 0.0, 0.20, 2600),
                ],
            ]),
            'LT' => self::country('Lithuania', 'EUR', 21.0, 45.0, 55.0, [
                'FREELANCE' => [
                    'individual' => self::progressive('Individual activity + PSD/VSD', [
                        ['up_to' => 100000, 'rate' => 0.05],
                        ['up_to' => null, 'rate' => 0.15],
                    ], 0.215, 1100),
                ],
                'COMPANY' => [
                    'uab' => self::corporate('UAB (16% CIT + dividend)', 0.16, 0.0, 0.15, 2500),
                ],
            ]),
            'CA' => self::country('Canada', 'CAD', 5.0, 95.0, 130.0, [
                'FREELANCE' => [
                    'sole_prop' => self::progressive('Sole proprietor (federal)', [
                        ['up_to' => 55867, 'rate' => 0.15],
                        ['up_to' => 111733, 'rate' => 0.205],
                        ['up_to' => 173205, 'rate' => 0.26],
                        ['up_to' => 246752, 'rate' => 0.29],
                        ['up_to' => null, 'rate' => 0.33],
                    ], 0.109, 1500, 'Federal bands only; provincial tax not included.'),
                ],
                'COMPANY' => [
                    'corp' => self::corporate('Corporation (15% SME + dividend)', 0.15, 0.0, 0.25, 4000,
                        'Small-business rate 15% federal; provincial CIT not included. Eligible dividend gross-up simplified.'),
                ],
            ]),
            'AU' => self::country('Australia', 'AUD', 10.0, 95.0, 130.0, [
                'FREELANCE' => [
                    'sole_trader' => self::progressive('Sole trader', [
                        ['up_to' => 18200, 'rate' => 0.0],
                        ['up_to' => 45000, 'rate' => 0.16],
                        ['up_to' => 135000, 'rate' => 0.30],
                        ['up_to' => 190000, 'rate' => 0.37],
                        ['up_to' => null, 'rate' => 0.45],
                    ], 0.0, 1200),
                ],
                'COMPANY' => [
                    'pty_ltd' => self::corporate('Pty Ltd (25% CIT + franked dividend)', 0.25, 0.0, 0.15, 3500),
                ],
            ]),
            'NZ' => self::country('New Zealand', 'NZD', 15.0, 85.0, 115.0, [
                'FREELANCE' => [
                    'sole_trader' => self::progressive('Sole trader', [
                        ['up_to' => 15600, 'rate' => 0.105],
                        ['up_to' => 53500, 'rate' => 0.175],
                        ['up_to' => 78100, 'rate' => 0.30],
                        ['up_to' => 180000, 'rate' => 0.33],
                        ['up_to' => null, 'rate' => 0.39],
                    ], 0.0, 900),
                ],
                'COMPANY' => [
                    'ltd' => self::corporate('Ltd (28% CIT + imputation)', 0.28, 0.0, 0.33, 2800),
                ],
            ]),
            'SG' => self::country('Singapore', 'SGD', 9.0, 85.0, 120.0, [
                'FREELANCE' => [
                    'sole_prop' => self::progressive('Sole proprietor', [
                        ['up_to' => 20000, 'rate' => 0.0],
                        ['up_to' => 30000, 'rate' => 0.02],
                        ['up_to' => 40000, 'rate' => 0.035],
                        ['up_to' => 80000, 'rate' => 0.07],
                        ['up_to' => null, 'rate' => 0.22],
                    ], 0.0, 800),
                ],
                'COMPANY' => [
                    'pte_ltd' => self::corporate('Pte Ltd (17% CIT + dividend)', 0.17, 0.0, 0.0, 2500,
                        'No withholding on dividends for individuals; 17% headline CIT (partial exemptions not auto-applied).'),
                ],
            ]),
            'JP' => self::country('Japan', 'JPY', 10.0, 8000.0, 11000.0, [
                'FREELANCE' => [
                    'sole_prop' => self::progressive('Sole proprietor (national)', [
                        ['up_to' => 1950000, 'rate' => 0.05],
                        ['up_to' => 3300000, 'rate' => 0.10],
                        ['up_to' => 6950000, 'rate' => 0.20],
                        ['up_to' => 9000000, 'rate' => 0.23],
                        ['up_to' => null, 'rate' => 0.33],
                    ], 0.145, 250000, 'National income tax bands; local inhabitant tax not included.'),
                ],
                'COMPANY' => [
                    'kk' => self::corporate('KK (23.2% CIT + dividend)', 0.232, 0.0, 0.20315, 700000),
                ],
            ]),
            'MX' => self::country('Mexico', 'MXN', 16.0, 650.0, 900.0, [
                'FREELANCE' => [
                    'pf' => self::progressive('Persona física RESICO / general', [
                        ['up_to' => 125900, 'rate' => 0.0192],
                        ['up_to' => 1000000, 'rate' => 0.2136],
                        ['up_to' => null, 'rate' => 0.35],
                    ], 0.0, 25000),
                ],
                'COMPANY' => [
                    'srl' => self::corporate('S. de R.L. (30% CIT + dividend)', 0.30, 0.0, 0.10, 70000),
                ],
            ]),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $regimes
     * @return array<string, mixed>
     */
    protected static function country(
        string $name,
        string $currency,
        float $vat,
        float $freelanceHourly,
        float $companyHourly,
        array $regimes,
    ): array {
        return [
            'name' => $name,
            'currency' => $currency,
            'vat_rate' => $vat,
            'hourly' => ['FREELANCE' => $freelanceHourly, 'COMPANY' => $companyHourly],
            'regimes' => $regimes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function flat(
        string $label,
        float $taxRate,
        float $socialRate,
        float $coefficient,
        float $compliance,
        bool $vatExempt,
        float $cap,
        ?string $notes = null,
        float $socialFixedAnnual = 0.0,
    ): array {
        return [
            'label' => $label,
            'model' => 'flat',
            'revenue_coefficient' => $coefficient,
            'income_tax_rate' => $taxRate,
            'social_rate' => $socialRate,
            'social_fixed_annual' => $socialFixedAnnual,
            'social_base' => 'taxable',
            'social_deductible' => $socialRate > 0 || $socialFixedAnnual > 0,
            'vat_exempt' => $vatExempt,
            'revenue_cap' => $cap > 0 ? $cap : null,
            'compliance_annual' => $compliance,
            'notes' => $notes ?? $label,
        ];
    }

    /**
     * @param  list<array{up_to: float|int|null, rate: float}>  $brackets
     * @return array<string, mixed>
     */
    protected static function progressive(
        string $label,
        array $brackets,
        float $socialRate,
        float $compliance,
        ?string $notes = null,
        float $socialFixedAnnual = 0.0,
    ): array {
        return [
            'label' => $label,
            'model' => 'progressive',
            'brackets' => $brackets,
            'social_rate' => $socialRate,
            'social_fixed_annual' => $socialFixedAnnual,
            'social_base' => 'profit',
            'social_deductible' => $socialRate > 0 || $socialFixedAnnual > 0,
            'vat_exempt' => false,
            'compliance_annual' => $compliance,
            'notes' => $notes ?? $label,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function corporate(
        string $label,
        float $citRate,
        float $localRate,
        float $dividendRate,
        float $compliance,
        ?string $notes = null,
    ): array {
        return [
            'label' => $label,
            'model' => 'corporate',
            'income_tax_rate' => $citRate,
            'local_tax_rate' => $localRate,
            'dividend_rate' => $dividendRate,
            'compliance_annual' => $compliance,
            'notes' => $notes ?? $label,
        ];
    }
}
