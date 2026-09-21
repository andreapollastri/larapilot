<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * Planning tax engine for Economics quotes. Rates come from TaxCatalog (FY-2026).
 * Not personalised tax advice.
 */
final class TaxEngine
{
    /**
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function compute(
        float $revenue,
        float $costs,
        array $regime,
        array $country,
        string $account,
        array $options = [],
    ): array {
        $revenue = max(0.0, $revenue);
        $costs = max(0.0, $costs);
        $profit = max(0.0, $revenue - $costs);
        $yearFraction = self::clamp((float) ($options['year_fraction'] ?? 1.0), 0.02, 1.0);
        $compliance = array_key_exists('compliance', $options)
            ? max(0.0, (float) $options['compliance'])
            : (float) ($regime['compliance_annual'] ?? 0) * $yearFraction;

        $model = (string) ($regime['model'] ?? 'flat');
        $hardCap = isset($regime['revenue_hard_cap']) ? (float) $regime['revenue_hard_cap'] : null;
        $softCap = isset($regime['revenue_cap']) ? (float) $regime['revenue_cap'] : null;

        if ($model === 'flat' && $hardCap !== null && $revenue > $hardCap) {
            $exited = self::forcedExit($revenue, $costs, $regime, $country, $account, $options, $hardCap);
            if ($exited !== null) {
                return $exited;
            }
        }

        return match ($model) {
            'progressive' => self::progressive($revenue, $costs, $profit, $regime, $country, $account, $compliance, $yearFraction),
            'corporate' => self::corporate($revenue, $costs, $profit, $regime, $country, $account, $compliance, $yearFraction, $options),
            default => self::flat($revenue, $costs, $profit, $regime, $country, $account, $compliance, $yearFraction, $softCap, $hardCap),
        };
    }

    /**
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    protected static function forcedExit(
        float $revenue,
        float $costs,
        array $regime,
        array $country,
        string $account,
        array $options,
        float $hardCap,
    ): ?array {
        $fallbackId = $regime['exit_regime'] ?? null;
        $code = (string) ($country['code'] ?? '');

        if (! is_string($fallbackId) || $fallbackId === '' || $code === '') {
            return null;
        }

        try {
            $fallback = TaxCatalog::regime($code, $account, $fallbackId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        // Leaving the regime means the exit regime's own compliance bill, not
        // the one the caller allocated for the regime being left.
        unset($options['compliance']);

        $result = self::compute($revenue, $costs, $fallback, $country, $account, $options);
        $result['forced_exit'] = true;
        $result['forced_exit_from'] = $regime['id'] ?? null;
        $result['over_cap'] = true;
        $result['revenue_cap'] = $hardCap;
        $result['assumptions'] = array_values(array_filter(array_merge(
            ['Revenue exceeds €'.number_format($hardCap, 0, '.', ',').' — computed under '.$fallback['label'].' (immediate exit).'],
            is_array($result['assumptions'] ?? null) ? $result['assumptions'] : [],
        )));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @return array<string, mixed>
     */
    protected static function flat(
        float $revenue,
        float $costs,
        float $profit,
        array $regime,
        array $country,
        string $account,
        float $compliance,
        float $yearFraction,
        ?float $softCap,
        ?float $hardCap,
    ): array {
        $coefficient = (float) ($regime['revenue_coefficient'] ?? 1.0);
        $taxableRevenue = $revenue;
        $overCap = $softCap !== null && $revenue > $softCap;
        $presocial = $taxableRevenue * $coefficient;
        $socialBase = self::socialBase($regime, $taxableRevenue, $profit, $presocial);
        $social = self::socialOn($socialBase, $regime, $yearFraction);
        $deductible = (bool) ($regime['social_deductible'] ?? false);
        $taxable = max(0.0, $deductible ? $presocial - $social : $presocial);
        $incomeTax = $taxable * (float) ($regime['income_tax_rate'] ?? 0);
        $incomeTax += $incomeTax * (float) ($regime['surtax_rate'] ?? 0);
        $incomeTax += $taxable * (float) ($regime['additional_rate'] ?? 0);
        $localTax = $profit * (float) ($regime['local_tax_rate'] ?? 0);
        $totalTax = $incomeTax + $social + $localTax;
        $net = $revenue - $costs - $totalTax - $compliance;

        $assumptions = array_values(array_filter([
            $regime['notes'] ?? null,
            $deductible ? 'Social contributions are deducted from the substitute-tax base (not from the INPS base).' : null,
            $overCap && $softCap !== null
                ? 'Revenue exceeds the €'.number_format($softCap, 0, '.', ',').' ceiling — still taxed in this regime this year, then you must leave next year (exit is immediate only above €'.number_format((float) ($hardCap ?? $softCap), 0, '.', ',').').'
                : null,
        ]));

        return self::payload([
            'model' => 'flat',
            'account' => $account,
            'country' => $country['code'] ?? null,
            'regime' => $regime['id'] ?? null,
            'revenue' => $revenue,
            'costs' => $costs,
            'profit' => $profit,
            'taxable' => $taxable,
            'income_tax' => $incomeTax,
            'social' => $social,
            'local_tax' => $localTax,
            'personal_tax' => 0.0,
            'dividend_tax' => 0.0,
            'legal_reserve' => 0.0,
            'compliance' => $compliance,
            'total_tax' => $totalTax,
            'net_to_owner' => $net,
            'over_cap' => $overCap,
            'revenue_cap' => $softCap,
            'forced_exit' => false,
            'notes' => $regime['notes'] ?? null,
            'assumptions' => $assumptions,
            'extraction' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @return array<string, mixed>
     */
    protected static function progressive(
        float $revenue,
        float $costs,
        float $profit,
        array $regime,
        array $country,
        string $account,
        float $compliance,
        float $yearFraction,
    ): array {
        $socialBase = self::socialBase($regime, $revenue, $profit, $profit);
        $social = self::socialOn($socialBase, $regime, $yearFraction);
        $deductible = (bool) ($regime['social_deductible'] ?? false);
        $taxable = max(0.0, $deductible ? $profit - $social : $profit);
        $incomeTax = self::progressiveTax($taxable, is_array($regime['brackets'] ?? null) ? $regime['brackets'] : []);
        $incomeTax += $incomeTax * (float) ($regime['surtax_rate'] ?? 0);
        $incomeTax += $taxable * (float) ($regime['additional_rate'] ?? 0);
        $localTax = $profit * (float) ($regime['local_tax_rate'] ?? 0);
        $totalTax = $incomeTax + $social + $localTax;
        $net = $revenue - $costs - $totalTax - $compliance;

        $assumptions = array_values(array_filter([
            $regime['notes'] ?? null,
            $deductible ? 'Social contributions are deducted from the income-tax base.' : null,
        ]));

        return self::payload([
            'model' => 'progressive',
            'account' => $account,
            'country' => $country['code'] ?? null,
            'regime' => $regime['id'] ?? null,
            'revenue' => $revenue,
            'costs' => $costs,
            'profit' => $profit,
            'taxable' => $taxable,
            'income_tax' => $incomeTax,
            'social' => $social,
            'local_tax' => $localTax,
            'personal_tax' => 0.0,
            'dividend_tax' => 0.0,
            'legal_reserve' => 0.0,
            'compliance' => $compliance,
            'total_tax' => $totalTax,
            'net_to_owner' => $net,
            'over_cap' => false,
            'revenue_cap' => $regime['revenue_cap'] ?? null,
            'forced_exit' => false,
            'notes' => $regime['notes'] ?? null,
            'assumptions' => $assumptions,
            'extraction' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected static function corporate(
        float $revenue,
        float $costs,
        float $profit,
        array $regime,
        array $country,
        string $account,
        float $compliance,
        float $yearFraction,
        array $options,
    ): array {
        $supportsMix = ($regime['extraction_model'] ?? null) === 'salary_dividend';
        $extraction = strtolower((string) ($options['extraction'] ?? 'auto'));
        if (! in_array($extraction, ['auto', 'dividends', 'mixed'], true)) {
            $extraction = 'auto';
        }

        $ownerWorking = array_key_exists('owner_working', $options)
            ? (bool) $options['owner_working']
            : true;

        $wages = [0.0];
        if ($supportsMix && $extraction !== 'dividends') {
            $cap = (float) ($regime['director_wage_cap'] ?? 0);
            if ($cap <= 0) {
                $cap = min(28000.0, max(12000.0, $profit * 0.35));
            }
            foreach ([12000.0, 15000.0, 18000.0, 24000.0, 28000.0, 35000.0, 50000.0] as $wage) {
                if ($wage <= $cap && $wage < $profit * 0.92) {
                    $wages[] = $wage;
                }
            }
        }
        if ($extraction === 'mixed') {
            $wages = array_values(array_filter($wages, static fn (float $wage): bool => $wage > 0));
            if ($wages === []) {
                $wages = [0.0];
            }
        }
        if ($extraction === 'dividends' || ! $supportsMix) {
            $wages = [0.0];
        }

        $best = null;
        $alternatives = [];

        foreach ($wages as $wage) {
            $row = self::corporateAtWage(
                $revenue,
                $costs,
                $profit,
                $wage,
                $regime,
                $country,
                $account,
                $compliance,
                $yearFraction,
                $ownerWorking,
            );

            if ($row === null) {
                continue;
            }

            $alternatives[] = [
                'method' => $wage <= 0.0 ? 'dividends' : 'mixed',
                'director_gross' => $row['extraction']['director_gross'] ?? 0.0,
                'net_to_owner' => $row['net_to_owner'],
                'effective_rate_pct' => $row['effective_rate_pct'],
            ];

            if ($best === null || $row['net_to_owner'] > $best['net_to_owner']) {
                $best = $row;
            }
        }

        if ($best === null) {
            $best = self::corporateAtWage(
                $revenue,
                $costs,
                $profit,
                0.0,
                $regime,
                $country,
                $account,
                $compliance,
                $yearFraction,
                $ownerWorking,
            );
        }

        if ($best === null) {
            return self::payload([
                'model' => 'corporate',
                'account' => $account,
                'country' => $country['code'] ?? null,
                'regime' => $regime['id'] ?? null,
                'revenue' => $revenue,
                'costs' => $costs,
                'profit' => $profit,
                'taxable' => 0.0,
                'income_tax' => 0.0,
                'social' => 0.0,
                'local_tax' => 0.0,
                'personal_tax' => 0.0,
                'dividend_tax' => 0.0,
                'legal_reserve' => 0.0,
                'compliance' => $compliance,
                'total_tax' => 0.0,
                'net_to_owner' => 0.0,
                'over_cap' => false,
                'revenue_cap' => null,
                'forced_exit' => false,
                'notes' => $regime['notes'] ?? null,
                'assumptions' => [],
                'extraction' => null,
            ]);
        }

        $best['extraction']['alternatives'] = $alternatives;
        $best['extraction']['owner_working'] = $ownerWorking;
        $best['extraction']['requested'] = $extraction;

        return $best;
    }

    /**
     * @param  array<string, mixed>  $regime
     * @param  array<string, mixed>  $country
     * @return array<string, mixed>|null
     */
    protected static function corporateAtWage(
        float $revenue,
        float $costs,
        float $production,
        float $wage,
        array $regime,
        array $country,
        string $account,
        float $compliance,
        float $yearFraction,
        bool $ownerWorking,
    ): ?array {
        $gsRate = $ownerWorking
            ? (float) ($regime['director_gs_rate_working'] ?? $regime['director_gs_rate'] ?? 0.24)
            : (float) ($regime['director_gs_rate'] ?? 0.0);
        $companyShare = (float) ($regime['director_gs_company_share'] ?? (2 / 3));
        $companyShare = self::clamp($companyShare, 0.0, 1.0);

        $companyGs = $wage > 0.0 ? $wage * $gsRate * $companyShare : 0.0;
        $employeeGs = $wage > 0.0 ? $wage * $gsRate * (1 - $companyShare) : 0.0;
        $ebit = $production - $wage - $companyGs;

        if ($ebit < 0.0) {
            return null;
        }

        $ires = self::corporateTax($ebit, $regime);
        $ires += $ires * (float) ($regime['surtax_rate'] ?? 0);
        $irapBase = ((string) ($regime['local_tax_base'] ?? 'profit')) === 'production' ? $production : $ebit;
        $irap = $irapBase * (float) ($regime['local_tax_rate'] ?? 0);
        $netProfit = $ebit - $ires - $irap;

        if ($netProfit < 0.0 && $wage > 0.0) {
            return null;
        }

        $netProfit = max(0.0, $netProfit);
        $reserveRate = (float) ($regime['legal_reserve_rate'] ?? 0);
        $reserveCap = (float) ($regime['legal_reserve_cap'] ?? 0);
        $reserve = max(0.0, $netProfit * $reserveRate);
        if ($reserveCap > 0) {
            $reserve = min($reserve, $reserveCap);
        }

        $divGross = max(0.0, $netProfit - $reserve);
        $divTax = $divGross * (float) ($regime['dividend_rate'] ?? 0);
        $divNet = $divGross - $divTax;

        // A shareholder who does not work in the company owes no owner-level
        // contributions on its profit, in any regime.
        $ownerSocial = $ownerWorking ? self::socialOn($ebit, $regime, $yearFraction) : 0.0;

        $personalBase = max(0.0, $wage - $employeeGs - $ownerSocial);
        $brackets = is_array($regime['personal_brackets'] ?? null) ? $regime['personal_brackets'] : [];
        $irpef = $brackets !== [] ? self::progressiveTax($personalBase, $brackets) : 0.0;
        $addizionali = $personalBase * (float) ($regime['personal_additional_rate'] ?? 0);
        $personalTax = $irpef + $addizionali;

        $social = $ownerSocial + $companyGs + $employeeGs;
        $totalTax = $ires + $irap + $social + $personalTax + $divTax;
        $net = $revenue - $costs - $totalTax - $compliance - $reserve;

        $method = $wage > 0.0 ? 'mixed' : 'dividends';
        $assumptions = self::corporateAssumptions($regime, $ownerWorking, $method, $reserve, $gsRate);

        return self::payload([
            'model' => 'corporate',
            'account' => $account,
            'country' => $country['code'] ?? null,
            'regime' => $regime['id'] ?? null,
            'revenue' => $revenue,
            'costs' => $costs,
            'profit' => $production,
            'taxable' => $ebit,
            'income_tax' => $ires,
            'social' => $social,
            'local_tax' => $irap,
            'personal_tax' => $personalTax,
            'dividend_tax' => $divTax,
            'legal_reserve' => $reserve,
            'compliance' => $compliance,
            'total_tax' => $totalTax,
            'net_to_owner' => $net,
            'over_cap' => false,
            'revenue_cap' => null,
            'forced_exit' => false,
            'notes' => $regime['notes'] ?? null,
            'assumptions' => $assumptions,
            'extraction' => [
                'method' => $method,
                'director_gross' => round($wage, 2),
                'director_net' => round($wage - $employeeGs - $personalTax, 2),
                'director_gs_company' => round($companyGs, 2),
                'director_gs_owner' => round($employeeGs, 2),
                'dividends_gross' => round($divGross, 2),
                'dividends_net' => round($divNet, 2),
                'legal_reserve' => round($reserve, 2),
                'owner_social' => round($ownerSocial, 2),
                'personal_tax' => round($personalTax, 2),
                'gs_rate' => $gsRate,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $regime
     * @return list<string>
     */
    protected static function corporateAssumptions(array $regime, bool $ownerWorking, string $method, float $reserve, float $gsRate): array
    {
        $lines = [];

        if (($regime['social_kind'] ?? '') === 'commercianti') {
            $lines[] = $ownerWorking
                ? 'Working shareholder (habitual + prevalent): Gestione Commercianti on the IRES base (INPS circ. 84/2021; rates circ. 14/2026).'
                : 'Owner treated as non-prevalent / investor: no Gestione Commercianti.';
        }

        if ($method === 'mixed') {
            $lines[] = 'Extraction mix: deductible director pay (Gestione Separata '.rtrim(rtrim(number_format($gsRate * 100, 2), '0'), '.').'%, 2/3 company) plus dividends.';
        } else {
            $lines[] = 'Extraction: dividends after corporate tax. Director pay is not modelled in this scenario.';
        }

        if ($reserve > 0) {
            $lines[] = '5% legal reserve withheld from distributable profit (capped; cash is received only after the annual accounts are approved).';
        }

        if (((string) ($regime['local_tax_base'] ?? '')) === 'production') {
            $lines[] = 'IRAP is on production value (director pay is not IRAP-deductible). Approximation for an SRL with no employees.';
        }

        $lines[] = (string) ($regime['notes'] ?? 'Statutory snapshot for planning.');

        return array_values(array_filter($lines));
    }

    /**
     * @param  array<string, mixed>  $regime
     */
    protected static function socialBase(array $regime, float $revenue, float $profit, float $taxable): float
    {
        return match ((string) ($regime['social_base'] ?? 'profit')) {
            'revenue' => $revenue,
            'taxable' => $taxable,
            default => $profit,
        };
    }

    /**
     * @param  array<string, mixed>  $regime
     */
    protected static function socialOn(float $base, array $regime, float $yearFraction): float
    {
        $kind = (string) ($regime['social_kind'] ?? 'rate');
        $cap = isset($regime['social_cap']) ? (float) $regime['social_cap'] : null;
        $capped = $cap !== null ? min(max(0.0, $base), $cap) : max(0.0, $base);

        if ($kind === 'commercianti') {
            return self::commercianti($capped, $regime, $yearFraction);
        }

        $rate = (float) ($regime['social_rate'] ?? 0);
        $fixed = (float) ($regime['social_fixed_annual'] ?? 0) * $yearFraction;

        return ($capped * $rate) + $fixed;
    }

    /**
     * @param  array<string, mixed>  $regime
     */
    protected static function commercianti(float $income, array $regime, float $yearFraction): float
    {
        $floorIncome = (float) ($regime['social_floor_income'] ?? 18808.0);
        $minimale = (float) ($regime['social_fixed_annual'] ?? 4611.64);
        $rate = (float) ($regime['social_rate'] ?? 0.2448);
        $upperRate = (float) ($regime['social_rate_upper'] ?? 0.2548);
        $threshold = (float) ($regime['social_threshold'] ?? 56224.0);
        $maternity = (float) ($regime['social_maternity_annual'] ?? 7.44);

        if ($income <= $floorIncome) {
            return $minimale * $yearFraction;
        }

        $band1 = min($income, $threshold);
        $band2 = max(0.0, $income - $threshold);

        return ($band1 * $rate) + ($band2 * $upperRate) + $maternity;
    }

    /**
     * @param  list<array{up_to: float|int|null, rate: float}>  $brackets
     */
    public static function progressiveTax(float $income, array $brackets): float
    {
        if ($income <= 0 || $brackets === []) {
            return 0.0;
        }

        $tax = 0.0;
        $previous = 0.0;

        foreach ($brackets as $bracket) {
            $limit = $bracket['up_to'] ?? null;
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
    public static function corporateTax(float $profit, array $regime): float
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function payload(array $data): array
    {
        $revenue = (float) ($data['revenue'] ?? 0);
        $totalTax = (float) ($data['total_tax'] ?? 0);
        $money = [
            'revenue', 'costs', 'profit', 'taxable', 'income_tax', 'social', 'local_tax',
            'personal_tax', 'dividend_tax', 'legal_reserve', 'compliance', 'total_tax',
            'total_withheld', 'net_to_owner',
        ];

        foreach ($money as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = round((float) $data[$key], 2);
            }
        }

        $withheld = $totalTax + (float) ($data['compliance'] ?? 0) + (float) ($data['legal_reserve'] ?? 0);
        $net = (float) ($data['net_to_owner'] ?? 0);

        // Tax rate is tax over revenue; compliance and the legal reserve are
        // costs, not tax, so they stay out of the rate and inside `total_withheld`
        // — which is what revenue minus costs minus net actually equals.
        $data['effective_rate_pct'] = $revenue > 0 ? round($totalTax / $revenue * 100, 1) : 0.0;
        $data['total_withheld'] = round($withheld, 2);
        $data['withheld_rate_pct'] = $revenue > 0 ? round($withheld / $revenue * 100, 1) : 0.0;
        $data['net_to_owner'] = round($net, 2);
        $data['loss'] = $net < 0.0;
        $data['forced_exit'] = (bool) ($data['forced_exit'] ?? false);
        $data['over_cap'] = (bool) ($data['over_cap'] ?? false);
        $data['assumptions'] = array_values(array_filter(is_array($data['assumptions'] ?? null) ? $data['assumptions'] : []));

        return $data;
    }

    protected static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
