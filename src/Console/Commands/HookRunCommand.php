<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\HookService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class HookRunCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:hook-run
                            {event : Event (ship, task.done, spec.review, …)}
                            {--phase=before : before or after}
                            {--spec= : Spec code the hooks are told about}
                            {--task= : Task id the hooks are told about}
                            {--release= : Release version the hooks are told about}
                            {--dry-run : List what would run, and run nothing}';

    protected $description = 'Fire the workflow hooks of one event without changing any state';

    public function handle(HookService $hooks, SpecService $specs): int
    {
        $event = trim((string) $this->argument('event'));
        $phase = strtolower(trim((string) $this->option('phase')));

        if (! array_key_exists($event, HookService::EVENTS)) {
            return $this->failure(
                'E_INVALID_INPUT',
                "Unknown event {$event}.",
                $this->exitForCode('E_INVALID_INPUT'),
                'Events: '.implode(', ', array_keys(HookService::EVENTS)).'.'
            );
        }

        if (! in_array($phase, HookService::PHASES, true)) {
            return $this->failure(
                'E_INVALID_INPUT',
                "Unknown phase {$phase}.",
                $this->exitForCode('E_INVALID_INPUT'),
                'Phases: '.implode(', ', HookService::PHASES).'.'
            );
        }

        $context = $this->eventContext($specs);

        if ((bool) $this->option('dry-run')) {
            $definitions = $hooks->definitions();

            return $this->success('hook_run_result', [
                'event' => $event,
                'phase' => $phase,
                'dry_run' => true,
                'active' => $hooks->active(),
                'ok' => $definitions['ok'],
                'would_run' => $definitions['hooks'][$event][$phase] ?? [],
                'environment' => $hooks->environment($event, $phase, $context),
                'findings' => $definitions['findings'],
            ]);
        }

        if (! $hooks->active()) {
            return $this->success('hook_run_result', [
                'event' => $event,
                'phase' => $phase,
                'active' => false,
                'ran' => [],
                'skills' => [],
                'hint' => 'Hooks are off (settings.hooks, or LARAPILOT_HOOKS_ENABLED on this machine): nothing ran. --dry-run lists what would.',
            ]);
        }

        $report = $hooks->fire($event, $phase, $context);

        if ($report !== null && $report['blocked']) {
            return $this->failure(
                'E_PRECONDITION',
                (string) $report['reason'],
                $this->exitForCode('E_PRECONDITION'),
                $report['hint'],
                ['hooks' => ['event' => $event, $phase => $this->hookPhase($report)]]
            );
        }

        return $this->success('hook_run_result', [
            'event' => $event,
            'phase' => $phase,
            'active' => true,
            'ran' => $report['ran'] ?? [],
            'skills' => $report['skills'] ?? [],
            'warnings' => $report['warnings'] ?? [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function eventContext(SpecService $specs): array
    {
        $context = [];
        $code = $this->option('spec');

        if (is_string($code) && trim($code) !== '') {
            $code = trim($code);
            $context = $this->specHookContext($code, $specs->find($code) ?? []);
        }

        foreach (['task', 'release'] as $key) {
            $value = $this->option($key);

            if (is_string($value) && trim($value) !== '') {
                $context[$key] = trim($value);
            }
        }

        return $context;
    }
}
