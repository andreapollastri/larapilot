<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * The base score of a CVSS 3.x vector, as FIRST specifies it, and the
 * severity band it falls in. Used when an advisory carries a vector and no
 * severity word of its own.
 */
final class Cvss
{
    private const WEIGHTS = [
        'AV' => ['N' => 0.85, 'A' => 0.62, 'L' => 0.55, 'P' => 0.2],
        'AC' => ['L' => 0.77, 'H' => 0.44],
        'UI' => ['N' => 0.85, 'R' => 0.62],
        'CIA' => ['H' => 0.56, 'L' => 0.22, 'N' => 0.0],
    ];

    /**
     * The base score, or null when the vector is not CVSS 3.x or misses a
     * base metric.
     */
    public static function score(string $vector): ?float
    {
        if (preg_match('#^CVSS:3\.[01]/#', $vector) !== 1) {
            return null;
        }

        $metrics = [];

        foreach (explode('/', substr($vector, 9)) as $part) {
            [$key, $value] = array_pad(explode(':', $part, 2), 2, '');
            $metrics[$key] = $value;
        }

        foreach (['AV', 'AC', 'PR', 'UI', 'S', 'C', 'I', 'A'] as $required) {
            if (! isset($metrics[$required])) {
                return null;
            }
        }

        $changed = $metrics['S'] === 'C';
        $pr = match ($metrics['PR']) {
            'N' => 0.85,
            'L' => $changed ? 0.68 : 0.62,
            'H' => $changed ? 0.5 : 0.27,
            default => null,
        };

        $av = self::WEIGHTS['AV'][$metrics['AV']] ?? null;
        $ac = self::WEIGHTS['AC'][$metrics['AC']] ?? null;
        $ui = self::WEIGHTS['UI'][$metrics['UI']] ?? null;
        $c = self::WEIGHTS['CIA'][$metrics['C']] ?? null;
        $i = self::WEIGHTS['CIA'][$metrics['I']] ?? null;
        $a = self::WEIGHTS['CIA'][$metrics['A']] ?? null;

        if (in_array(null, [$pr, $av, $ac, $ui, $c, $i, $a], true)) {
            return null;
        }

        $iss = 1 - ((1 - $c) * (1 - $i) * (1 - $a));
        $impact = $changed
            ? 7.52 * ($iss - 0.029) - 3.25 * (($iss - 0.02) ** 15)
            : 6.42 * $iss;
        $exploitability = 8.22 * $av * $ac * $pr * $ui;

        if ($impact <= 0) {
            return 0.0;
        }

        return self::roundUp(min(($changed ? 1.08 : 1.0) * ($impact + $exploitability), 10.0));
    }

    public static function severity(float $score): string
    {
        return match (true) {
            $score >= 9.0 => 'critical',
            $score >= 7.0 => 'high',
            $score >= 4.0 => 'medium',
            $score > 0.0 => 'low',
            default => 'none',
        };
    }

    /**
     * CVSS 3.1 rounding: up to one decimal, without floating-point drift.
     */
    private static function roundUp(float $value): float
    {
        $integer = (int) round($value * 100000);

        if ($integer % 10000 === 0) {
            return $integer / 100000.0;
        }

        return (floor($integer / 10000) + 1) / 10.0;
    }
}
