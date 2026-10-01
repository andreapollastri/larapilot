<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Illuminate\Console\Command;
use Larapilot\Services\EconomicsService;
use Larapilot\Services\HookService;
use Larapilot\Support\Envelope as EnvelopeWriter;

abstract class LarapilotCommand extends Command
{
    /**
     * Set on commands that change a quote input — specs, plans, tasks, PRD,
     * inception answers, usage, or settings. Those commands recompute the
     * Economics snapshot on success, so the cost board follows the backlog
     * without anyone triggering it by hand.
     */
    protected bool $refreshesEconomics = false;

    /**
     * What the workflow hooks of this run reported, by phase. Folded into
     * the answer as `data.hooks`; absent when no hook had anything to do.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $hookReport = null;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function success(string $kind, array $data): int
    {
        if ($this->hookReport !== null && ! array_key_exists('hooks', $data)) {
            $data['hooks'] = $this->hookReport;
        }

        $this->line(EnvelopeWriter::success($kind, $data));
        $this->refreshEconomics();

        return self::SUCCESS;
    }

    /**
     * Economics is advisory: a snapshot refresh never fails the command that
     * triggered it.
     */
    protected function refreshEconomics(): void
    {
        if (! $this->refreshesEconomics) {
            return;
        }

        try {
            app(EconomicsService::class)->refreshIfStale();
        } catch (\Throwable) {
            // the profile or catalogue is misconfigured — the Economics
            // surfaces report that themselves.
        }
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    protected function failure(string $code, string $message, int $exitCode = 1, ?string $hint = null, ?array $details = null): int
    {
        $this->error(EnvelopeWriter::error($code, $message, $hint, $details));

        return $exitCode;
    }

    /**
     * Run the `before` hooks of an event, once every guard of the command has
     * passed and nothing is written yet. An exit code when one of them blocks
     * the transition; null to go on.
     *
     * @param  array<string, mixed>  $context
     */
    protected function beforeHooks(string $event, array $context): ?int
    {
        $report = app(HookService::class)->fire($event, 'before', $context, $this->skillHooksDone());

        if ($report === null) {
            return null;
        }

        $this->hookReport = ['event' => $event, 'before' => $this->hookPhase($report)];

        if (! $report['blocked']) {
            return null;
        }

        return $this->failure(
            'E_PRECONDITION',
            (string) $report['reason'],
            $this->exitForCode('E_PRECONDITION'),
            $report['hint'],
            ['hooks' => $this->hookReport]
        );
    }

    /**
     * Run the `after` hooks of an event once the state is written. A failure
     * is reported in the answer and never undoes the transition.
     *
     * @param  array<string, mixed>  $context
     */
    protected function afterHooks(string $event, array $context): void
    {
        try {
            $report = app(HookService::class)->fire($event, 'after', $context);
        } catch (\Throwable $exception) {
            $report = ['ran' => [], 'skills' => [], 'warnings' => ['The after hooks could not run: '.$exception->getMessage()]];
        }

        if ($report === null) {
            return;
        }

        $this->hookReport = ($this->hookReport ?? ['event' => $event]) + ['after' => $this->hookPhase($report)];
    }

    /**
     * What a hook is told about the spec of a transition, and the move it makes.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function specHookContext(string $code, array $spec, ?string $to = null): array
    {
        $from = strtoupper(trim((string) ($spec['status'] ?? '')));

        return [
            'spec' => array_filter([
                'code' => $code,
                'title' => trim((string) ($spec['title'] ?? '')),
                'status' => $from,
                'priority' => is_scalar($spec['priority'] ?? null) ? (string) $spec['priority'] : null,
                'epic' => is_scalar($spec['epic'] ?? null) ? (string) $spec['epic'] : null,
            ], static fn (?string $value): bool => $value !== null && $value !== ''),
            'status' => ['from' => $from, 'to' => $to ?? $from],
        ];
    }

    /**
     * The skill hooks the caller reports run, from `--skill-hooks-done`.
     *
     * @return list<string>
     */
    protected function skillHooksDone(): array
    {
        if (! $this->hasOption('skill-hooks-done')) {
            return [];
        }

        $raw = $this->option('skill-hooks-done');

        return is_string($raw)
            ? array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $name): bool => $name !== ''))
            : [];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    protected function hookPhase(array $report): array
    {
        return array_filter([
            'ran' => $report['ran'] ?? [],
            'skills' => $report['skills'] ?? [],
            'warnings' => $report['warnings'] ?? [],
            'findings' => $report['findings'] ?? [],
        ], static fn (array $part): bool => $part !== []);
    }

    protected function exitForCode(string $code): int
    {
        return match ($code) {
            'E_INVALID_INPUT' => 2,
            'E_CONNECTOR' => 3,
            'E_PRECONDITION', 'E_NOT_FOUND' => 4,
            default => 1,
        };
    }

    /**
     * Emit a validation_result envelope; exit non-zero when validation failed.
     *
     * @param  array{ok: bool, findings: array<int, array<string, string>>}  $result
     */
    protected function validationResult(array $result): int
    {
        $this->line(EnvelopeWriter::success('validation_result', $result));

        return $result['ok'] ? self::SUCCESS : $this->exitForCode('E_INVALID_INPUT');
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<string>  $allowed
     */
    protected function guardStatus(array $spec, array $allowed, string $action): ?int
    {
        $status = strtoupper(trim((string) ($spec['status'] ?? '')));
        $allowedUpper = array_map('strtoupper', $allowed);

        if (in_array($status, $allowedUpper, true)) {
            return null;
        }

        return $this->failure(
            'E_PRECONDITION',
            "Cannot {$action} a spec in status ".($status === '' ? '(none)' : $status).'.',
            $this->exitForCode('E_PRECONDITION'),
            'Expected status: '.implode(' or ', $allowed).'.'
        );
    }
}
