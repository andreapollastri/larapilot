<?php

declare(strict_types=1);

namespace Larapilot\Services\Aikido;

use Larapilot\Services\ConfigService;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\FileLock;
use Symfony\Component\Yaml\Yaml;

/**
 * What was decided about each finding of Aikido, kept in
 * `.larapilot/aikido.yaml`: the spec that fixes it, or the reason it was
 * waived, and whether Aikido was told. The file also holds the repository
 * of the workspace the user named as this project, when the git remote
 * does not find it.
 *
 * The file is committed, so a finding handed to the backlog on one machine
 * is not handed over again on another. It holds identifiers and decisions —
 * never a credential, never the detail of a vulnerability.
 */
class AikidoLedger
{
    public const IN_BACKLOG = 'in_backlog';

    public const WAIVED = 'waived';

    public function __construct(protected ConfigService $config) {}

    public function path(): string
    {
        return $this->config->absolutePath('.larapilot/aikido.yaml');
    }

    public function relativePath(): string
    {
        return '.larapilot/aikido.yaml';
    }

    /**
     * Every decision, keyed by the id of the finding.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $issues = $this->read()['issues'] ?? [];
        $decisions = [];

        foreach (is_array($issues) ? $issues : [] as $id => $decision) {
            if (is_array($decision)) {
                $decisions[(string) $id] = $decision;
            }
        }

        return $decisions;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->all()[(string) $id] ?? null;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $repository
     */
    public function record(array $ids, array $decision, array $repository = []): void
    {
        $this->change(function (array $state) use ($ids, $decision, $repository): array {
            foreach ($ids as $id) {
                $state['issues'][(string) $id] = array_filter(
                    array_merge($state['issues'][(string) $id] ?? [], $decision, ['at' => now()->toIso8601String()]),
                    static fn (mixed $value): bool => $value !== null && $value !== ''
                );
            }

            if ($repository !== []) {
                $state['repository'] = $repository;
            }

            return $state;
        });
    }

    /**
     * Add to what is kept about a finding without deciding again: whether
     * Aikido was told, and how. A value of `null` drops the key.
     *
     * @param  array<string, mixed>  $fields
     */
    public function annotate(int $id, array $fields): void
    {
        $this->change(function (array $state) use ($id, $fields): array {
            if (! is_array($state['issues'][(string) $id] ?? null)) {
                return $state;
            }

            $state['issues'][(string) $id] = array_filter(
                array_merge($state['issues'][(string) $id], $fields),
                static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
            );

            return $state;
        });
    }

    /**
     * The repository of the workspace the user named as this project.
     *
     * @return array{id: int, name: string}|null
     */
    public function chosenRepository(): ?array
    {
        $chosen = $this->read()['chosen_repository'] ?? null;

        return is_array($chosen) && is_numeric($chosen['id'] ?? null) && (int) $chosen['id'] > 0
            ? ['id' => (int) $chosen['id'], 'name' => (string) ($chosen['name'] ?? '')]
            : null;
    }

    /**
     * @param  array{id: int, name: string}|null  $repository
     */
    public function chooseRepository(?array $repository): void
    {
        $this->change(function (array $state) use ($repository): array {
            if ($repository === null) {
                unset($state['chosen_repository']);

                return $state;
            }

            $state['chosen_repository'] = [
                'id' => $repository['id'],
                'name' => $repository['name'],
                'at' => now()->toIso8601String(),
            ];

            return $state;
        });
    }

    /**
     * @param  list<int>  $ids
     */
    public function forget(array $ids): void
    {
        $this->change(function (array $state) use ($ids): array {
            foreach ($ids as $id) {
                unset($state['issues'][(string) $id]);
            }

            return $state;
        });
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    protected function change(callable $change): void
    {
        FileLock::withLock($this->path(), function () use ($change): void {
            $state = $this->read();
            $state['issues'] = is_array($state['issues'] ?? null) ? $state['issues'] : [];
            $state = $change($state);

            ksort($state['issues'], SORT_NATURAL);
            $state['updated_at'] = now()->toIso8601String();

            AtomicFile::write(
                $this->path(),
                "# Generated by Larapilot (php artisan larapilot:aikido-link, larapilot:aikido-repos --use).\n"
                ."# What was decided about each finding of Aikido: the spec that fixes it, or why it was waived.\n"
                ."# Safe to commit: identifiers and decisions only, never API credentials.\n"
                .Yaml::dump($state, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function read(): array
    {
        if (! is_file($this->path())) {
            return ['issues' => []];
        }

        try {
            $parsed = Yaml::parseFile($this->path());
        } catch (\Throwable) {
            return ['issues' => []];
        }

        return is_array($parsed) ? $parsed : ['issues' => []];
    }
}
