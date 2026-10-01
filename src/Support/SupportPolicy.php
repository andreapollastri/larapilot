<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * How long each version of the stack is looked after upstream: Laravel, PHP,
 * the database engines Laravel speaks to, and Node for the frontend.
 *
 * A row says until when a version receives bug fixes (`active`) and until
 * when it receives security fixes; past the second date it is end of life.
 * The tables are a snapshot taken on {@see self::CHECKED}: a version released
 * after that day is reported as unknown, never guessed.
 *
 * Sources: laravel.com/docs/releases (Support Policy), php.net/supported-versions,
 * the MySQL lifecycle policy, mariadb.org maintenance policy, postgresql.org
 * versioning policy, nodejs.org release schedule.
 */
final class SupportPolicy
{
    public const CHECKED = '2026-10-01';

    /**
     * Days before the end of security support when a version is reported as
     * ending: enough to plan an upgrade, not so many it is noise.
     */
    public const ENDING_DAYS = 180;

    /**
     * Majors such as `12` become integer keys; `8.4` stays a string.
     *
     * @var array<string, array{label: string, latest: string, versions: array<int|string, array{released: string, active_until: string|null, security_until: string, php?: string, note?: string}>}>
     */
    private const PRODUCTS = [
        'laravel' => [
            'label' => 'Laravel',
            'latest' => '13',
            'versions' => [
                '8' => ['released' => '2020-09-08', 'active_until' => '2022-07-26', 'security_until' => '2023-01-24', 'php' => '7.3 - 8.1'],
                '9' => ['released' => '2022-02-08', 'active_until' => '2023-08-08', 'security_until' => '2024-02-06', 'php' => '8.0 - 8.2'],
                '10' => ['released' => '2023-02-14', 'active_until' => '2024-08-06', 'security_until' => '2025-02-04', 'php' => '8.1 - 8.3'],
                '11' => ['released' => '2024-03-12', 'active_until' => '2025-09-03', 'security_until' => '2026-03-12', 'php' => '8.2 - 8.4'],
                '12' => ['released' => '2025-02-24', 'active_until' => '2026-08-13', 'security_until' => '2027-02-24', 'php' => '8.2 - 8.5'],
                // Bug fixes are announced as "Q3 2027": the end of the quarter is used.
                '13' => ['released' => '2026-03-17', 'active_until' => '2027-09-30', 'security_until' => '2028-03-17', 'php' => '8.3 - 8.5', 'note' => 'Bug fixes until Q3 2027'],
            ],
        ],
        'php' => [
            'label' => 'PHP',
            'latest' => '8.5',
            'versions' => [
                '7.4' => ['released' => '2019-11-28', 'active_until' => '2021-11-28', 'security_until' => '2022-11-28'],
                '8.0' => ['released' => '2020-11-26', 'active_until' => '2022-11-26', 'security_until' => '2023-11-26'],
                '8.1' => ['released' => '2021-11-25', 'active_until' => '2023-11-25', 'security_until' => '2025-12-31'],
                '8.2' => ['released' => '2022-12-08', 'active_until' => '2024-12-31', 'security_until' => '2026-12-31'],
                '8.3' => ['released' => '2023-11-23', 'active_until' => '2025-12-31', 'security_until' => '2027-12-31'],
                '8.4' => ['released' => '2024-11-21', 'active_until' => '2026-12-31', 'security_until' => '2028-12-31'],
                '8.5' => ['released' => '2025-11-20', 'active_until' => '2027-12-31', 'security_until' => '2029-12-31'],
            ],
        ],
        'mysql' => [
            'label' => 'MySQL',
            'latest' => '8.4',
            'versions' => [
                '5.7' => ['released' => '2015-10-21', 'active_until' => '2020-10-21', 'security_until' => '2023-10-31'],
                '8.0' => ['released' => '2018-04-19', 'active_until' => '2023-04-30', 'security_until' => '2026-04-30'],
                // The first LTS: premier support, then extended support.
                '8.4' => ['released' => '2024-04-30', 'active_until' => '2029-04-30', 'security_until' => '2032-04-30', 'note' => 'LTS'],
            ],
        ],
        'mariadb' => [
            'label' => 'MariaDB',
            'latest' => '11.8',
            'versions' => [
                '10.5' => ['released' => '2020-06-24', 'active_until' => null, 'security_until' => '2025-06-24', 'note' => 'LTS'],
                '10.6' => ['released' => '2021-07-06', 'active_until' => null, 'security_until' => '2026-07-06', 'note' => 'LTS'],
                '10.11' => ['released' => '2023-02-16', 'active_until' => null, 'security_until' => '2028-02-16', 'note' => 'LTS'],
                '11.4' => ['released' => '2024-05-29', 'active_until' => null, 'security_until' => '2029-05-29', 'note' => 'LTS'],
                '11.8' => ['released' => '2025-06-04', 'active_until' => null, 'security_until' => '2028-06-04', 'note' => 'LTS'],
            ],
        ],
        'pgsql' => [
            'label' => 'PostgreSQL',
            'latest' => '18',
            'versions' => [
                '12' => ['released' => '2019-10-03', 'active_until' => null, 'security_until' => '2024-11-21'],
                '13' => ['released' => '2020-09-24', 'active_until' => null, 'security_until' => '2025-11-13'],
                '14' => ['released' => '2021-09-30', 'active_until' => null, 'security_until' => '2026-11-12'],
                '15' => ['released' => '2022-10-13', 'active_until' => null, 'security_until' => '2027-11-11'],
                '16' => ['released' => '2023-09-14', 'active_until' => null, 'security_until' => '2028-11-09'],
                '17' => ['released' => '2024-09-26', 'active_until' => null, 'security_until' => '2029-11-08'],
                '18' => ['released' => '2025-09-25', 'active_until' => null, 'security_until' => '2030-11-14'],
            ],
        ],
        'node' => [
            'label' => 'Node.js',
            'latest' => '24',
            'versions' => [
                '18' => ['released' => '2022-04-19', 'active_until' => '2023-10-18', 'security_until' => '2025-04-30'],
                '20' => ['released' => '2023-04-18', 'active_until' => '2024-10-22', 'security_until' => '2026-04-30'],
                '22' => ['released' => '2024-04-24', 'active_until' => '2025-10-21', 'security_until' => '2027-04-30'],
                '24' => ['released' => '2025-05-06', 'active_until' => '2026-10-20', 'security_until' => '2028-04-30'],
            ],
        ],
    ];

    /**
     * The products the table knows, with their label.
     *
     * @return array<string, string>
     */
    public static function products(): array
    {
        return array_map(static fn (array $product): string => $product['label'], self::PRODUCTS);
    }

    public static function label(string $product): string
    {
        return self::PRODUCTS[self::product($product)]['label'] ?? ucfirst($product);
    }

    /**
     * The newest version the table holds for a product.
     */
    public static function latest(string $product): ?string
    {
        return self::PRODUCTS[self::product($product)]['latest'] ?? null;
    }

    /**
     * The cycle a version belongs to: the major for Laravel, PostgreSQL and
     * Node, major.minor for PHP, MySQL and MariaDB.
     */
    public static function cycle(string $product, ?string $version): ?string
    {
        if ($version === null || preg_match('/(\d+)(?:\.(\d+))?/', ltrim(trim($version), 'vV^~>=< '), $match) !== 1) {
            return null;
        }

        return match (self::product($product)) {
            'laravel', 'pgsql', 'node' => $match[1],
            default => $match[1].'.'.($match[2] ?? '0'),
        };
    }

    /**
     * Where a version stands today: `active` (bug and security fixes),
     * `security` (security fixes only), `eol`, or `unknown` when the table
     * does not hold it.
     *
     * @return array{product: string, label: string, cycle: string|null, state: string, released: string|null, active_until: string|null, security_until: string|null, days_left: int|null, ending: bool, latest: string|null, is_latest: bool, php: string|null, note: string|null}
     */
    public static function status(string $product, ?string $version, ?string $today = null): array
    {
        $key = self::product($product);
        $cycle = self::cycle($key, $version);
        $row = $cycle !== null ? (self::PRODUCTS[$key]['versions'][$cycle] ?? null) : null;
        $today ??= date('Y-m-d');
        $latest = self::latest($key);

        $status = [
            'product' => $key,
            'label' => self::label($key),
            'cycle' => $cycle,
            'state' => 'unknown',
            'released' => null,
            'active_until' => null,
            'security_until' => null,
            'days_left' => null,
            'ending' => false,
            'latest' => $latest,
            'is_latest' => $cycle !== null && $latest !== null && $cycle === $latest,
            'php' => null,
            'note' => null,
        ];

        if ($row === null) {
            // A version newer than the table is not end of life: it is unknown.
            return $status;
        }

        $status['released'] = $row['released'];
        $status['active_until'] = $row['active_until'];
        $status['security_until'] = $row['security_until'];
        $status['php'] = $row['php'] ?? null;
        $status['note'] = $row['note'] ?? null;

        $security = $row['security_until'];
        $active = $row['active_until'];

        $status['state'] = match (true) {
            $today > $security => 'eol',
            // Engines that support a version one way until its end have no
            // date for bug fixes alone.
            $active === null, $today <= $active => 'active',
            default => 'security',
        };

        $days = (int) floor((strtotime($security) - strtotime($today)) / 86400);
        $status['days_left'] = $days;
        $status['ending'] = $status['state'] !== 'eol' && $days <= self::ENDING_DAYS;

        return $status;
    }

    /**
     * The PHP versions a Laravel major supports, as `[min, max]`.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function laravelPhpRange(string $laravelMajor): ?array
    {
        $php = self::PRODUCTS['laravel']['versions'][self::cycle('laravel', $laravelMajor) ?? '']['php'] ?? null;

        if ($php === null || preg_match('/^(\d+\.\d+)\s*-\s*(\d+\.\d+)$/', $php, $match) !== 1) {
            return null;
        }

        return [$match[1], $match[2]];
    }

    /**
     * Every cycle the table holds for a product, oldest first.
     *
     * @return list<string>
     */
    public static function cycles(string $product): array
    {
        return array_map('strval', array_keys(self::PRODUCTS[self::product($product)]['versions'] ?? []));
    }

    /**
     * The words a page shows for a state.
     */
    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'active' => 'Supported',
            'security' => 'Security fixes only',
            'eol' => 'End of life',
            default => 'Unknown',
        };
    }

    private static function product(string $product): string
    {
        return match (strtolower(trim($product))) {
            'postgres', 'postgresql', 'pgsql' => 'pgsql',
            'nodejs', 'node.js', 'node' => 'node',
            default => strtolower(trim($product)),
        };
    }
}
