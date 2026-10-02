<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Which migrations ran and which are still to run, as `migrate:status`
 * tells it: the files of the application and of its packages against the
 * `migrations` table of the connection the viewer reads. It only reads —
 * nothing is migrated from the dashboard.
 */
class DatabaseMigrationService
{
    public function __construct(protected DatabaseViewerService $database) {}

    /**
     * Every migration: the ones still to run, then the ones that ran with
     * their batch — a file that is no longer there among them — the
     * newest first.
     *
     * @return array{migrations: list<array{name: string, title: string, date: string|null, state: string, batch: int|null, path: string|null, package: string|null}>, ran: int, pending: int, missing: int, batch: int|null, table: string, installed: bool, error: string|null}
     */
    public function status(): array
    {
        $result = [
            'migrations' => [],
            'ran' => 0,
            'pending' => 0,
            'missing' => 0,
            'batch' => null,
            'table' => $this->table(),
            'installed' => false,
            'error' => null,
        ];

        try {
            $migrator = app('migrator');

            if (! $migrator instanceof Migrator) {
                throw new \RuntimeException('The application has no migrator.');
            }

            $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));

            // Without its table nothing ran on this connection: every file is still to run.
            /** @var array{bool, array<string, int|string>} $read */
            $read = $migrator->usingConnection($this->database->connectionName(), static function () use ($migrator): array {
                $installed = $migrator->repositoryExists();

                return [$installed, $installed ? $migrator->getRepository()->getMigrationBatches() : []];
            });

            [$result['installed'], $batches] = $read;
        } catch (Throwable $e) {
            $message = trim($e->getMessage());
            $result['error'] = $message === '' ? class_basename($e).' while reading the migrations.' : $message;

            return $result;
        }

        foreach ($files as $name => $path) {
            $ran = array_key_exists($name, $batches);

            $result['migrations'][] = $this->describe((string) $name, $ran ? 'ran' : 'pending', $ran ? (int) $batches[$name] : null, (string) $path);
        }

        foreach ($batches as $name => $batch) {
            if (! isset($files[$name])) {
                $result['migrations'][] = $this->describe((string) $name, 'missing', (int) $batch, null);
            }
        }

        // What is still to run comes first; within each group, the newest first.
        usort($result['migrations'], static fn (array $a, array $b): int => [$b['state'] === 'pending', $b['name']] <=> [$a['state'] === 'pending', $a['name']]);

        foreach ($result['migrations'] as $migration) {
            $result[$migration['state']]++;
        }

        $result['batch'] = $batches === [] ? null : (int) max($batches);

        return $result;
    }

    /**
     * @return array{name: string, title: string, date: string|null, state: string, batch: int|null, path: string|null, package: string|null}
     */
    protected function describe(string $name, string $state, ?int $batch, ?string $path): array
    {
        $title = $name;
        $date = null;

        // 2024_05_17_093000_create_orders_table: the day it was written, and what it does.
        // Laravel's own start at 0001_01_01, which is a place in the order and no day at all.
        if (preg_match('/^(\d{4})_(\d{2})_(\d{2})_\d+_(.+)$/', $name, $match) === 1) {
            $date = (int) $match[1] >= 1970 ? $match[1].'-'.$match[2].'-'.$match[3] : null;
            $title = $match[4];
        }

        $relative = $path === null ? null : $this->relative($path);

        return [
            'name' => $name,
            'title' => ucfirst(str_replace('_', ' ', $title)),
            'date' => $date,
            'state' => $state,
            'batch' => $batch,
            'path' => $relative,
            'package' => $relative !== null && preg_match('#^vendor/([^/]+/[^/]+)/#', $relative, $match) === 1 ? $match[1] : null,
        ];
    }

    /**
     * The folder of a migration, from the root of the application where it
     * is inside it.
     */
    protected function relative(string $path): string
    {
        $folder = str_replace('\\', '/', dirname($path));
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        return (str_starts_with($folder.'/', $base) ? substr($folder.'/', strlen($base)) : $folder.'/').basename($path);
    }

    /**
     * The table Laravel keeps the migrations that ran in.
     */
    public function table(): string
    {
        $table = config('database.migrations');
        $table = is_array($table) ? ($table['table'] ?? null) : $table;

        return is_string($table) && $table !== '' ? $table : 'migrations';
    }
}
