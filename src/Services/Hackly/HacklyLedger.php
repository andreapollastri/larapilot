<?php

declare(strict_types=1);

namespace Larapilot\Services\Hackly;

use Larapilot\Services\ConfigService;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\FileLock;
use Symfony\Component\Yaml\Yaml;

/**
 * What was decided about each finding of Hackly, kept in
 * `.larapilot/hackly.yaml`: the spec that fixes it, the reason it was
 * waived or called a false positive, and whether Hackly was told. The file
 * also holds the repository of Hackly the user named as this project.
 *
 * It is committed, so a finding handed to the backlog on one machine is
 * not handed over again on another. It holds identifiers and decisions —
 * never a credential, never the detail of a vulnerability.
 */
class HacklyLedger
{
    public const IN_BACKLOG = 'in_backlog';

    public const WAIVED = 'waived';

    public const FALSE_POSITIVE = 'false_positive';

    public function __construct(protected ConfigService $config) {}

    public function path(): string
    {
        return $this->config->absolutePath('.larapilot/hackly.yaml');
    }

    public function relativePath(): string
    {
        return '.larapilot/hackly.yaml';
    }

    /**
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
    public function find(string $id): ?array
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $decision
     */
    public function record(array $ids, array $decision): void
    {
        $this->update(function (array $data) use ($ids, $decision): array {
            foreach ($ids as $id) {
                $data['issues'][$id] = array_merge($decision, ['at' => now()->toIso8601String()]);
            }

            return $data;
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function annotate(string $id, array $fields): void
    {
        $this->update(function (array $data) use ($id, $fields): array {
            if (! isset($data['issues'][$id]) || ! is_array($data['issues'][$id])) {
                return $data;
            }

            foreach ($fields as $key => $value) {
                if ($value === null) {
                    unset($data['issues'][$id][$key]);
                } else {
                    $data['issues'][$id][$key] = $value;
                }
            }

            return $data;
        });
    }

    /**
     * @param  list<string>  $ids
     */
    public function forget(array $ids): void
    {
        $this->update(function (array $data) use ($ids): array {
            foreach ($ids as $id) {
                unset($data['issues'][$id]);
            }

            return $data;
        });
    }

    /**
     * @return array{id: string, full_name: string}|null
     */
    public function chosenRepository(): ?array
    {
        $repository = $this->read()['repository'] ?? null;

        return is_array($repository) && is_string($repository['id'] ?? null) && $repository['id'] !== ''
            ? ['id' => $repository['id'], 'full_name' => (string) ($repository['full_name'] ?? '')]
            : null;
    }

    /**
     * @param  array{id: string, full_name: string}|null  $repository
     */
    public function chooseRepository(?array $repository): void
    {
        $this->update(function (array $data) use ($repository): array {
            if ($repository === null) {
                unset($data['repository']);
            } else {
                $data['repository'] = ['id' => $repository['id'], 'full_name' => $repository['full_name']];
            }

            return $data;
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
    protected function update(callable $change): void
    {
        FileLock::withLock($this->path(), function () use ($change): void {
            $data = $change($this->read());

            if (isset($data['issues']) && is_array($data['issues'])) {
                ksort($data['issues']);
            }

            if (($data['issues'] ?? []) === []) {
                unset($data['issues']);
            }

            $header = "# What was decided here about the findings of Hackly: the spec that fixes each one,\n"
                ."# or why it was waived or called a false positive, and whether Hackly was told.\n"
                ."# Commit this file. It holds no credential and no detail of a vulnerability.\n\n";

            AtomicFile::write($this->path(), $header.Yaml::dump($data, 4, 2));
        });
    }
}
