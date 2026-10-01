<?php

declare(strict_types=1);

namespace Larapilot\Services\Sbom;

use Larapilot\Services\ConfigService;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\FileLock;
use Symfony\Component\Yaml\Yaml;

/**
 * What was decided about each vulnerable dependency, in
 * `.larapilot/vendor-audit.yaml`: the spec that fixes it, or the reason it
 * was accepted. The file also keeps the summary of each check, so the trend
 * survives a clone.
 *
 * It is committed: a waiver given on one machine holds on every other. It
 * keeps advisory ids and decisions; the advisories themselves are in the
 * cache, rebuilt by the next check.
 */
class VendorAuditLedger
{
    public const IN_BACKLOG = 'in_backlog';

    public const WAIVED = 'waived';

    private const HISTORY = 30;

    public function __construct(protected ConfigService $config) {}

    public function path(): string
    {
        return $this->config->absolutePath('.larapilot/vendor-audit.yaml');
    }

    public function relativePath(): string
    {
        return '.larapilot/vendor-audit.yaml';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function decisions(): array
    {
        $decisions = [];

        foreach ((array) ($this->read()['decisions'] ?? []) as $id => $decision) {
            if (is_array($decision)) {
                $decisions[(string) $id] = $decision;
            }
        }

        return $decisions;
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $decision
     */
    public function record(array $ids, array $decision): void
    {
        $this->change(function (array $state) use ($ids, $decision): array {
            foreach ($ids as $id) {
                $state['decisions'][$id] = array_filter(
                    array_merge($decision, ['at' => now()->toIso8601String()]),
                    static fn (mixed $value): bool => $value !== null && $value !== ''
                );
            }

            return $state;
        });
    }

    /**
     * @param  list<string>  $ids
     */
    public function forget(array $ids): void
    {
        $this->change(function (array $state) use ($ids): array {
            foreach ($ids as $id) {
                unset($state['decisions'][$id]);
            }

            return $state;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(): array
    {
        return array_values(array_filter((array) ($this->read()['history'] ?? []), 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function remember(array $summary): void
    {
        $this->change(function (array $state) use ($summary): array {
            $history = array_values(array_filter((array) ($state['history'] ?? []), 'is_array'));
            $history[] = $summary;
            $state['history'] = array_slice($history, -self::HISTORY);

            return $state;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (\Throwable) {
            return [];
        }

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    protected function change(callable $change): void
    {
        $path = $this->path();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        FileLock::withLock($path, function () use ($path, $change): void {
            $state = $change($this->read());
            $state = array_merge(['decisions' => [], 'history' => []], $state);

            $header = "# What was decided about each vulnerable dependency, and the trend of the checks.\n"
                ."# Written by php artisan larapilot:vendor-audit and larapilot:vendor-link: commit it.\n";

            AtomicFile::write($path, $header.Yaml::dump($state, 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
        });
    }
}
