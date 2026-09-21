<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * Statutory tax snapshots used by Economics quotes. Rates are FY-2026
 * approximations for planning — not personalised tax advice.
 */
final class TaxCatalog
{
    public const YEAR = 2026;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function countries(): array
    {
        return [
            'IT' => [
                'name' => 'Italy',
                'currency' => 'EUR',
                'vat_rate' => 22.0,
                'hourly' => ['FREELANCE' => 55.0, 'COMPANY' => 75.0],
                'regimes' => [
                    'FREELANCE' => [
                        'forfettario_5' => [
                            'label' => 'Forfettario 5% (startup, first 5 years)',
                            'model' => 'flat',
                            'revenue_coefficient' => 0.67,
                            'income_tax_rate' => 0.05,
                            'social_rate' => 0.2607,
                            'social_base' => 'taxable',
                            'vat_exempt' => true,
                            'revenue_cap' => 85000,
                            'compliance_annual' => 900,
                            'notes' => 'ATECO 62 software/consulenza coefficient 67%. INPS gestione separata on taxable income. No VAT, no IRAP.',
                        ],
                        'forfettario_15' => [
                            'label' => 'Forfettario 15%',
                            'model' => 'flat',
                            'revenue_coefficient' => 0.67,
                            'income_tax_rate' => 0.15,
                            'social_rate' => 0.2607,
                            'social_base' => 'taxable',
                            'vat_exempt' => true,
                            'revenue_cap' => 85000,
                            'compliance_annual' => 900,
                            'notes' => 'Same forfettario rules after the 5-year startup window. Ceiling €85,000.',
                        ],
                        'ordinario' => [
                            'label' => 'IRPEF ordinario + INPS',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 28000, 'rate' => 0.23],
                                ['up_to' => 50000, 'rate' => 0.35],
                                ['up_to' => null, 'rate' => 0.43],
                            ],
                            'additional_rate' => 0.023,
                            'social_rate' => 0.2607,
                            'social_base' => 'profit',
                            'local_tax_rate' => 0.039,
                            'vat_exempt' => false,
                            'compliance_annual' => 1800,
                            'notes' => 'IRPEF 23/35/43 + addizionali ~2.3% + INPS 26.07% + IRAP 3.9% when due. VAT 22%.',
                        ],
                    ],
                    'COMPANY' => [
                        'srl' => [
                            'label' => 'SRL (IRES + IRAP + dividend 26%)',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.24,
                            'local_tax_rate' => 0.039,
                            'dividend_rate' => 0.26,
                            'compliance_annual' => 4500,
                            'notes' => 'IRES 24% + IRAP 3.9% on profit; 26% withholding when profits are distributed to individuals.',
                        ],
                        'srls' => [
                            'label' => 'SRL semplificata',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.24,
                            'local_tax_rate' => 0.039,
                            'dividend_rate' => 0.26,
                            'compliance_annual' => 2800,
                            'notes' => 'Same IRES/IRAP/dividend as SRL; lower compliance floor.',
                        ],
                        'spa' => [
                            'label' => 'SPA',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.24,
                            'local_tax_rate' => 0.039,
                            'dividend_rate' => 0.26,
                            'compliance_annual' => 12000,
                            'notes' => 'Same corporate rates as SRL plus collegio sindacale / heavier compliance.',
                        ],
                    ],
                ],
            ],
            'DE' => [
                'name' => 'Germany',
                'currency' => 'EUR',
                'vat_rate' => 19.0,
                'hourly' => ['FREELANCE' => 75.0, 'COMPANY' => 95.0],
                'regimes' => [
                    'FREELANCE' => [
                        'freelancer' => [
                            'label' => 'Freiberufler (ESt + Solidarität + KV)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 11604, 'rate' => 0.0],
                                ['up_to' => 17005, 'rate' => 0.14],
                                ['up_to' => 66760, 'rate' => 0.24],
                                ['up_to' => 277825, 'rate' => 0.42],
                                ['up_to' => null, 'rate' => 0.45],
                            ],
                            'surtax_rate' => 0.055,
                            'social_rate' => 0.146,
                            'social_base' => 'profit',
                            'vat_exempt' => false,
                            'compliance_annual' => 1500,
                            'notes' => 'Simplified Einkommensteuer bands + 5.5% Solidaritätszuschlag on income tax + ~14.6% health insurance.',
                        ],
                    ],
                    'COMPANY' => [
                        'gmbh' => [
                            'label' => 'GmbH (KSt + Soli + GewSt)',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.15,
                            'surtax_rate' => 0.055,
                            'local_tax_rate' => 0.14,
                            'dividend_rate' => 0.26375,
                            'compliance_annual' => 5500,
                            'notes' => 'KSt 15% + Solidarität + typical Gewerbesteuer ~14%. Combined ~30% before dividend tax.',
                        ],
                    ],
                ],
            ],
            'FR' => [
                'name' => 'France',
                'currency' => 'EUR',
                'vat_rate' => 20.0,
                'hourly' => ['FREELANCE' => 60.0, 'COMPANY' => 80.0],
                'regimes' => [
                    'FREELANCE' => [
                        'micro_bnc' => [
                            'label' => 'Micro-entreprise BNC',
                            'model' => 'flat',
                            'revenue_coefficient' => 1.0,
                            'income_tax_rate' => 0.022,
                            'social_rate' => 0.212,
                            'social_base' => 'revenue',
                            'vat_exempt' => true,
                            'revenue_cap' => 77700,
                            'compliance_annual' => 400,
                            'notes' => 'Versement libératoire ~2.2% + social 21.2% on turnover (BNC). VAT franchise until the micro ceiling.',
                        ],
                        'bnc' => [
                            'label' => 'BNC réel (IR progressive)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 11294, 'rate' => 0.0],
                                ['up_to' => 28797, 'rate' => 0.11],
                                ['up_to' => 82341, 'rate' => 0.30],
                                ['up_to' => 177106, 'rate' => 0.41],
                                ['up_to' => null, 'rate' => 0.45],
                            ],
                            'social_rate' => 0.22,
                            'social_base' => 'profit',
                            'vat_exempt' => false,
                            'compliance_annual' => 1600,
                        ],
                    ],
                    'COMPANY' => [
                        'sasu' => [
                            'label' => 'SASU / SAS (IS)',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.25,
                            'small_profit_rate' => 0.15,
                            'small_profit_up_to' => 42500,
                            'dividend_rate' => 0.30,
                            'compliance_annual' => 3500,
                            'notes' => 'IS 15% on first €42,500 then 25%. PFU 30% on dividends (flat tax).',
                        ],
                    ],
                ],
            ],
            'ES' => [
                'name' => 'Spain',
                'currency' => 'EUR',
                'vat_rate' => 21.0,
                'hourly' => ['FREELANCE' => 40.0, 'COMPANY' => 60.0],
                'regimes' => [
                    'FREELANCE' => [
                        'autonomo' => [
                            'label' => 'Autónomo (IRPF + RETA)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 12450, 'rate' => 0.19],
                                ['up_to' => 20200, 'rate' => 0.24],
                                ['up_to' => 35200, 'rate' => 0.30],
                                ['up_to' => 60000, 'rate' => 0.37],
                                ['up_to' => 300000, 'rate' => 0.45],
                                ['up_to' => null, 'rate' => 0.47],
                            ],
                            'social_fixed_annual' => 3600,
                            'vat_exempt' => false,
                            'compliance_annual' => 900,
                            'notes' => 'IRPF bands + RETA ~€300/month (quota plana after year 1 approximated). IVA 21%.',
                        ],
                    ],
                    'COMPANY' => [
                        'sl' => [
                            'label' => 'Sociedad Limitada',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.25,
                            'small_profit_rate' => 0.15,
                            'small_profit_up_to' => 0,
                            'dividend_rate' => 0.19,
                            'compliance_annual' => 3200,
                            'notes' => 'IS 25% (15% possible in first years — not auto-applied). Dividend withholding from 19%.',
                        ],
                    ],
                ],
            ],
            'GB' => [
                'name' => 'United Kingdom',
                'currency' => 'GBP',
                'vat_rate' => 20.0,
                'hourly' => ['FREELANCE' => 65.0, 'COMPANY' => 85.0],
                'regimes' => [
                    'FREELANCE' => [
                        'sole_trader' => [
                            'label' => 'Sole trader (Income Tax + NI)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 12570, 'rate' => 0.0],
                                ['up_to' => 50270, 'rate' => 0.20],
                                ['up_to' => 125140, 'rate' => 0.40],
                                ['up_to' => null, 'rate' => 0.45],
                            ],
                            'social_rate' => 0.08,
                            'social_base' => 'profit',
                            'vat_exempt' => false,
                            'compliance_annual' => 800,
                            'notes' => 'England/NI bands. Class 4 NI approximated at 8% on profits (simplified).',
                        ],
                    ],
                    'COMPANY' => [
                        'ltd' => [
                            'label' => 'Private limited company',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.25,
                            'small_profit_rate' => 0.19,
                            'small_profit_up_to' => 50000,
                            'dividend_rate' => 0.0875,
                            'compliance_annual' => 2000,
                            'notes' => 'Corporation Tax 19% under £50k, 25% above £250k (marginal relief simplified as 25% after £50k). Basic-rate dividend tax ~8.75%.',
                        ],
                    ],
                ],
            ],
            'US' => [
                'name' => 'United States',
                'currency' => 'USD',
                'vat_rate' => 0.0,
                'hourly' => ['FREELANCE' => 95.0, 'COMPANY' => 130.0],
                'regimes' => [
                    'FREELANCE' => [
                        'sole_prop' => [
                            'label' => 'Sole proprietor / single-member LLC (federal + SE)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 11925, 'rate' => 0.10],
                                ['up_to' => 48475, 'rate' => 0.12],
                                ['up_to' => 103350, 'rate' => 0.22],
                                ['up_to' => 197300, 'rate' => 0.24],
                                ['up_to' => 250525, 'rate' => 0.32],
                                ['up_to' => 626350, 'rate' => 0.35],
                                ['up_to' => null, 'rate' => 0.37],
                            ],
                            'social_rate' => 0.153,
                            'social_base' => 'profit',
                            'vat_exempt' => true,
                            'compliance_annual' => 1200,
                            'notes' => 'Federal single filer 2026-style bands + 15.3% self-employment tax. State income tax not included.',
                        ],
                    ],
                    'COMPANY' => [
                        'c_corp' => [
                            'label' => 'C-Corporation',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.21,
                            'dividend_rate' => 0.15,
                            'compliance_annual' => 4500,
                            'notes' => 'Federal 21%. Qualified dividend rate 15% assumed. State franchise/income tax not included.',
                        ],
                        'llc' => [
                            'label' => 'Multi-member LLC (pass-through)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 11925, 'rate' => 0.10],
                                ['up_to' => 48475, 'rate' => 0.12],
                                ['up_to' => 103350, 'rate' => 0.22],
                                ['up_to' => 197300, 'rate' => 0.24],
                                ['up_to' => 250525, 'rate' => 0.32],
                                ['up_to' => 626350, 'rate' => 0.35],
                                ['up_to' => null, 'rate' => 0.37],
                            ],
                            'social_rate' => 0.153,
                            'social_base' => 'profit',
                            'compliance_annual' => 1800,
                            'notes' => 'Pass-through: federal individual bands + SE tax. No entity-level federal CIT.',
                        ],
                    ],
                ],
            ],
            'NL' => [
                'name' => 'Netherlands',
                'currency' => 'EUR',
                'vat_rate' => 21.0,
                'hourly' => ['FREELANCE' => 70.0, 'COMPANY' => 90.0],
                'regimes' => [
                    'FREELANCE' => [
                        'eenmanszaak' => [
                            'label' => 'Eenmanszaak (Box 1)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 38441, 'rate' => 0.3582],
                                ['up_to' => 76817, 'rate' => 0.3748],
                                ['up_to' => null, 'rate' => 0.495],
                            ],
                            'social_rate' => 0.0,
                            'vat_exempt' => false,
                            'compliance_annual' => 1100,
                            'notes' => 'Box 1 bands include national insurance. Zelfstandigenaftrek not auto-applied.',
                        ],
                    ],
                    'COMPANY' => [
                        'bv' => [
                            'label' => 'BV',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.258,
                            'small_profit_rate' => 0.19,
                            'small_profit_up_to' => 200000,
                            'dividend_rate' => 0.246,
                            'compliance_annual' => 4000,
                            'notes' => 'Vpb 19% to €200k then 25.8%. Box 2 dividend ~24.6% (simplified).',
                        ],
                    ],
                ],
            ],
            'PT' => [
                'name' => 'Portugal',
                'currency' => 'EUR',
                'vat_rate' => 23.0,
                'hourly' => ['FREELANCE' => 40.0, 'COMPANY' => 55.0],
                'regimes' => [
                    'FREELANCE' => [
                        'simplified' => [
                            'label' => 'IRS simplified (75% coefficient)',
                            'model' => 'flat',
                            'revenue_coefficient' => 0.75,
                            'income_tax_rate' => 0.28,
                            'social_rate' => 0.214,
                            'social_base' => 'taxable',
                            'vat_exempt' => false,
                            'compliance_annual' => 700,
                            'notes' => 'Simplified IRS: 75% of services turnover taxed around the 28% band + SS 21.4%.',
                        ],
                    ],
                    'COMPANY' => [
                        'lda' => [
                            'label' => 'Lda (IRC)',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.21,
                            'local_tax_rate' => 0.015,
                            'dividend_rate' => 0.28,
                            'compliance_annual' => 2500,
                        ],
                    ],
                ],
            ],
            'CH' => [
                'name' => 'Switzerland',
                'currency' => 'CHF',
                'vat_rate' => 8.1,
                'hourly' => ['FREELANCE' => 110.0, 'COMPANY' => 140.0],
                'regimes' => [
                    'FREELANCE' => [
                        'self_employed' => [
                            'label' => 'Self-employed (federal + cantonal blended)',
                            'model' => 'flat',
                            'revenue_coefficient' => 1.0,
                            'income_tax_rate' => 0.18,
                            'social_rate' => 0.10,
                            'social_base' => 'profit',
                            'vat_exempt' => false,
                            'compliance_annual' => 1500,
                            'notes' => 'Blended ~18% income tax (varies strongly by canton) + AHV ~10%.',
                        ],
                    ],
                    'COMPANY' => [
                        'gmbh' => [
                            'label' => 'GmbH / AG (blended CIT)',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.145,
                            'dividend_rate' => 0.15,
                            'compliance_annual' => 4500,
                            'notes' => 'Effective CIT often 12–18% depending on canton. 14.5% used as a mid-point.',
                        ],
                    ],
                ],
            ],
            'AT' => [
                'name' => 'Austria',
                'currency' => 'EUR',
                'vat_rate' => 20.0,
                'hourly' => ['FREELANCE' => 65.0, 'COMPANY' => 85.0],
                'regimes' => [
                    'FREELANCE' => [
                        'einzelunternehmen' => [
                            'label' => 'Einzelunternehmen (ESt + SVS)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 12816, 'rate' => 0.0],
                                ['up_to' => 20818, 'rate' => 0.20],
                                ['up_to' => 34513, 'rate' => 0.30],
                                ['up_to' => 66612, 'rate' => 0.40],
                                ['up_to' => 99266, 'rate' => 0.48],
                                ['up_to' => 1000000, 'rate' => 0.50],
                                ['up_to' => null, 'rate' => 0.55],
                            ],
                            'social_rate' => 0.255,
                            'social_base' => 'profit',
                            'vat_exempt' => false,
                            'compliance_annual' => 1400,
                        ],
                    ],
                    'COMPANY' => [
                        'gmbh' => [
                            'label' => 'GmbH (KöSt)',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.23,
                            'dividend_rate' => 0.275,
                            'compliance_annual' => 4000,
                        ],
                    ],
                ],
            ],
            'BE' => [
                'name' => 'Belgium',
                'currency' => 'EUR',
                'vat_rate' => 21.0,
                'hourly' => ['FREELANCE' => 65.0, 'COMPANY' => 85.0],
                'regimes' => [
                    'FREELANCE' => [
                        'independant' => [
                            'label' => 'Indépendant (IPP + social)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 15820, 'rate' => 0.25],
                                ['up_to' => 27920, 'rate' => 0.40],
                                ['up_to' => 48480, 'rate' => 0.45],
                                ['up_to' => null, 'rate' => 0.50],
                            ],
                            'social_rate' => 0.208,
                            'social_base' => 'profit',
                            'vat_exempt' => false,
                            'compliance_annual' => 1600,
                        ],
                    ],
                    'COMPANY' => [
                        'srl' => [
                            'label' => 'SRL / BV',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.25,
                            'small_profit_rate' => 0.20,
                            'small_profit_up_to' => 100000,
                            'dividend_rate' => 0.30,
                            'compliance_annual' => 3800,
                        ],
                    ],
                ],
            ],
            'IE' => [
                'name' => 'Ireland',
                'currency' => 'EUR',
                'vat_rate' => 23.0,
                'hourly' => ['FREELANCE' => 70.0, 'COMPANY' => 90.0],
                'regimes' => [
                    'FREELANCE' => [
                        'sole_trader' => [
                            'label' => 'Sole trader (income tax + PRSI + USC)',
                            'model' => 'progressive',
                            'brackets' => [
                                ['up_to' => 44000, 'rate' => 0.20],
                                ['up_to' => null, 'rate' => 0.40],
                            ],
                            'additional_rate' => 0.08,
                            'social_rate' => 0.04,
                            'social_base' => 'profit',
                            'vat_exempt' => false,
                            'compliance_annual' => 900,
                            'notes' => '20/40% income tax + USC ~8% blended + PRSI 4% (simplified).',
                        ],
                    ],
                    'COMPANY' => [
                        'ltd' => [
                            'label' => 'Limited company (trading CT)',
                            'model' => 'corporate',
                            'income_tax_rate' => 0.125,
                            'dividend_rate' => 0.33,
                            'compliance_annual' => 2500,
                            'notes' => '12.5% trading corporation tax. Dividend income tax ~33% (higher-rate approximation).',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function countryCodes(): array
    {
        return array_keys(self::countries());
    }

    /**
     * @return array<string, mixed>
     */
    public static function country(string $code): array
    {
        $countries = self::countries();
        $normalized = self::normalizeCountry($code);

        if (! isset($countries[$normalized])) {
            throw new \InvalidArgumentException('Unknown country: '.$code.'. Allowed: '.implode(', ', self::countryCodes()).'.');
        }

        return $countries[$normalized] + ['code' => $normalized];
    }

    public static function normalizeCountry(string $code): string
    {
        $normalized = strtoupper(trim($code));

        $aliases = [
            'ITA' => 'IT',
            'ITALY' => 'IT',
            'ITALIA' => 'IT',
            'DEU' => 'DE',
            'GERMANY' => 'DE',
            'DEUTSCHLAND' => 'DE',
            'FRA' => 'FR',
            'FRANCE' => 'FR',
            'ESP' => 'ES',
            'SPAIN' => 'ES',
            'ESPANA' => 'ES',
            'UK' => 'GB',
            'GBR' => 'GB',
            'UNITED_KINGDOM' => 'GB',
            'ENGLAND' => 'GB',
            'USA' => 'US',
            'UNITED_STATES' => 'US',
            'NLD' => 'NL',
            'NETHERLANDS' => 'NL',
            'HOLLAND' => 'NL',
            'PRT' => 'PT',
            'PORTUGAL' => 'PT',
            'CHE' => 'CH',
            'SWITZERLAND' => 'CH',
            'SVIZZERA' => 'CH',
            'AUT' => 'AT',
            'AUSTRIA' => 'AT',
            'BEL' => 'BE',
            'BELGIUM' => 'BE',
            'IRL' => 'IE',
            'IRELAND' => 'IE',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    public static function defaultCountry(): string
    {
        return 'IT';
    }

    public static function defaultRegime(string $country, string $account): string
    {
        $regimes = self::regimesFor($country, $account);
        $keys = array_keys($regimes);

        if ($keys === []) {
            throw new \InvalidArgumentException("No tax regime for {$account} in {$country}.");
        }

        if ($country === 'IT' && $account === 'FREELANCE' && isset($regimes['forfettario_15'])) {
            return 'forfettario_15';
        }

        if ($country === 'IT' && $account === 'COMPANY' && isset($regimes['srl'])) {
            return 'srl';
        }

        return (string) $keys[0];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function regimesFor(string $country, string $account): array
    {
        $meta = self::country($country);
        $bucket = is_array($meta['regimes'][$account] ?? null) ? $meta['regimes'][$account] : [];

        return $bucket;
    }

    /**
     * @return array<string, mixed>
     */
    public static function regime(string $country, string $account, string $regime): array
    {
        $regimes = self::regimesFor($country, $account);
        $id = self::normalizeRegime($regime, array_keys($regimes));

        if (! isset($regimes[$id])) {
            throw new \InvalidArgumentException(
                "Unknown regime {$regime} for {$account} in {$country}. Allowed: ".implode(', ', array_keys($regimes)).'.'
            );
        }

        return $regimes[$id] + ['id' => $id, 'account' => $account];
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function normalizeRegime(string $regime, array $allowed = []): string
    {
        $normalized = strtolower(trim($regime));
        $normalized = (string) preg_replace('/[\s-]+/', '_', $normalized);

        $aliases = [
            'forfettario' => 'forfettario_15',
            'forfettario5' => 'forfettario_5',
            'forfettario15' => 'forfettario_15',
            'startup' => 'forfettario_5',
            'irpef' => 'ordinario',
            'ordinary' => 'ordinario',
            'partita_iva' => 'forfettario_15',
            'piva' => 'forfettario_15',
            'limited' => 'ltd',
            'llc_pass_through' => 'llc',
            'c-corp' => 'c_corp',
            'ccorp' => 'c_corp',
            'corporation' => 'c_corp',
        ];

        $mapped = $aliases[$normalized] ?? $normalized;

        if ($allowed !== [] && ! in_array($mapped, $allowed, true)) {
            foreach ($allowed as $id) {
                if ($id === $mapped || str_contains($id, $mapped) || str_contains($mapped, $id)) {
                    return $id;
                }
            }
        }

        return $mapped;
    }

    public static function defaultHourlyRate(string $country, string $account): float
    {
        $meta = self::country($country);
        $hourly = is_array($meta['hourly'] ?? null) ? $meta['hourly'] : [];

        return (float) ($hourly[$account] ?? ($account === 'COMPANY' ? 80.0 : 55.0));
    }

    public static function defaultCurrency(string $country): string
    {
        return (string) (self::country($country)['currency'] ?? 'EUR');
    }

    public static function defaultVatRate(string $country): float
    {
        return (float) (self::country($country)['vat_rate'] ?? 0.0);
    }
}
