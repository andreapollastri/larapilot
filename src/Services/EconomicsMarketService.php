<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Symfony\Component\Yaml\Yaml;

/**
 * Market intelligence behind the pricing tool: sector, competitor prices and
 * their trend, demand scenarios, and the packaging the product is sold with.
 *
 * Larapilot computes none of it — Jennifer and Benjamin research it during
 * `/larapilot-economics` and persist it through `economics-market-write`. The
 * engine only normalizes what they wrote so the dashboard can plot it against
 * the quote. Everything here is optional: without the file the pricing tool
 * falls back to heuristics and says so.
 */
class EconomicsMarketService
{
    public const SCENARIOS = ['pessimistic', 'realistic', 'optimistic'];

    public const TIERS = ['base', 'pro', 'premium'];

    public const TRENDS = ['up', 'flat', 'down'];

    protected const MAX_COMPETITORS = 24;

    protected const MAX_TIER_FEATURES = 24;

    public function __construct(protected ConfigService $config) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['economics_market'] ?? '.larapilot/economics.market.yaml');
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(): ?array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return null;
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed) || $parsed === []) {
            return null;
        }

        return $this->normalize($parsed) + [
            'path' => $this->config->relativePath($path),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function write(array $payload, ?string $inputs = null): array
    {
        $research = $this->normalize($payload);

        if ($research['competitors'] === [] && $research['sector'] === null && $research['demand'] === []) {
            throw new \InvalidArgumentException('Market research is empty: give at least a sector, one competitor, or one demand scenario.');
        }

        $research['researched_at'] = (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM);

        if ($inputs !== null) {
            $research['inputs'] = $inputs;
        }

        $directory = dirname($this->path());
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        AtomicFile::write(
            $this->path(),
            Yaml::dump($research, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        return [
            'path' => $this->config->relativePath($this->path()),
            'sector' => $research['sector'],
            'competitors' => count($research['competitors']),
            'tiers' => count($research['tiers']),
            'scenarios' => array_keys($research['demand']),
            'researched_at' => $research['researched_at'],
        ];
    }

    /**
     * Agent-written YAML is data, never trusted shape: every key is re-read,
     * clamped, and dropped when it is not something the dashboard can plot.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        return [
            'sector' => $this->text($payload['sector'] ?? null, 120),
            'segment' => $this->text($payload['segment'] ?? null, 160),
            'summary' => $this->text($payload['summary'] ?? null, 800),
            'currency' => $this->currency($payload['currency'] ?? null),
            'competitors' => $this->competitors($payload['competitors'] ?? null),
            'demand' => $this->demand($payload['demand'] ?? null),
            'tiers' => $this->tiers($payload['tiers'] ?? null),
            'risks' => $this->lines($payload['risks'] ?? null, 8, 240),
            'sources' => $this->lines($payload['sources'] ?? null, 12, 300),
            'researched_at' => $this->text($payload['researched_at'] ?? null, 40),
            'inputs' => $this->text($payload['inputs'] ?? null, 64),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function competitors(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = $this->text($entry['name'] ?? null, 80);

            if ($name === null) {
                continue;
            }

            $price = $this->positive($entry['price_monthly'] ?? null, 1000000.0);
            $trend = strtolower(trim((string) ($entry['trend'] ?? '')));

            $rows[] = [
                'name' => $name,
                'plan' => $this->text($entry['plan'] ?? null, 60),
                'price_monthly' => $price,
                'currency' => $this->currency($entry['currency'] ?? null),
                'trend' => in_array($trend, self::TRENDS, true) ? $trend : null,
                'change_pct' => $this->signed($entry['change_pct'] ?? null, 500.0),
                'url' => $this->url($entry['url'] ?? null),
                'notes' => $this->text($entry['notes'] ?? null, 240),
            ];

            if (count($rows) >= self::MAX_COMPETITORS) {
                break;
            }
        }

        usort($rows, static fn (array $a, array $b): int => ($a['price_monthly'] ?? 0) <=> ($b['price_monthly'] ?? 0));

        return $rows;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function demand(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $scenarios = [];

        foreach (self::SCENARIOS as $id) {
            $entry = $value[$id] ?? null;

            if (! is_array($entry)) {
                continue;
            }

            $customers = (int) round((float) ($entry['customers'] ?? 0));

            $scenarios[$id] = [
                'customers' => max(0, min(10000000, $customers)),
                'growth_monthly_pct' => $this->positive($entry['growth_monthly_pct'] ?? null, 200.0),
                'churn_monthly_pct' => $this->positive($entry['churn_monthly_pct'] ?? null, 100.0),
                'conversion_pct' => $this->positive($entry['conversion_pct'] ?? null, 100.0),
                'note' => $this->text($entry['note'] ?? null, 240),
            ];
        }

        return $scenarios;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function tiers(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $id = strtolower(trim((string) ($entry['id'] ?? (self::TIERS[$index] ?? ''))));

            if (! in_array($id, self::TIERS, true)) {
                continue;
            }

            $rows[$id] = [
                'id' => $id,
                'name' => $this->text($entry['name'] ?? null, 32) ?? strtoupper($id),
                'price_monthly' => $this->positive($entry['price_monthly'] ?? null, 1000000.0),
                'price_annual' => $this->positive($entry['price_annual'] ?? null, 10000000.0),
                'share_pct' => $this->positive($entry['share_pct'] ?? null, 100.0),
                'features' => $this->lines($entry['features'] ?? null, self::MAX_TIER_FEATURES, 160),
                'note' => $this->text($entry['note'] ?? null, 240),
            ];
        }

        $ordered = [];

        foreach (self::TIERS as $id) {
            if (isset($rows[$id])) {
                $ordered[] = $rows[$id];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    protected function lines(mixed $value, int $max, int $length): array
    {
        if (is_string($value)) {
            $value = preg_split('/\R+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $lines = [];

        foreach ($value as $entry) {
            if (! is_scalar($entry)) {
                continue;
            }

            $text = $this->text($entry, $length);

            if ($text !== null) {
                $lines[] = $text;
            }

            if (count($lines) >= $max) {
                break;
            }
        }

        return $lines;
    }

    protected function text(mixed $value, int $length): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $length);
    }

    protected function currency(mixed $value): ?string
    {
        $code = strtoupper((string) $this->text($value, 3));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }

    protected function url(mixed $value): ?string
    {
        $url = $this->text($value, 300);

        if ($url === null) {
            return null;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    protected function positive(mixed $value, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number <= 0) {
            return null;
        }

        return round(min($number, $max), 2);
    }

    protected function signed(mixed $value, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round(max(-$max, min($max, (float) $value)), 1);
    }
}
