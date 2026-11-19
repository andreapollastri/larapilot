<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeTraceabilityService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\PlanService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SpecCode;

class TraceCodeCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:trace-code
                            {spec : User story/spec code, e.g. US-123}
                            {--task= : Optional plan task id, e.g. TASK-01}
                            {--see=* : Technical documentation path or URL (repeatable)}
                            {--path=* : PHP file or directory to update (repeatable; default: app)}
                            {--dry-run : Report changes without writing files}';

    protected $description = 'Add spec/task and technical-documentation references to existing PHP methods';

    public function handle(
        ConfigService $config,
        SpecService $specs,
        PlanService $plans,
        CodeTraceabilityService $traceability,
    ): int {
        if (! $config->traceabilityCommentsEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Traceability comments are disabled.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable them with: php artisan larapilot:settings-set --traceability-comments=YES'
            );
        }

        $code = SpecCode::ensure((string) $this->argument('spec'));
        $spec = $specs->find($code);

        if ($spec === null) {
            return $this->failure(
                'E_NOT_FOUND',
                "Spec {$code} was not found.",
                $this->exitForCode('E_NOT_FOUND')
            );
        }

        $references = array_values(array_filter(array_map(
            static fn (mixed $reference): string => trim((string) $reference),
            (array) $this->option('see')
        )));

        if ($references === []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide at least one --see technical documentation path or URL.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $title = trim((string) ($spec['title'] ?? ''));
        $trace = implode(' | ', array_filter([$code, $title]));
        $taskId = strtoupper(trim((string) ($this->option('task') ?? '')));

        if ($taskId !== '') {
            $plan = $plans->read($code);
            $task = collect(is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [])
                ->first(static fn (mixed $candidate): bool => is_array($candidate) && strtoupper((string) ($candidate['id'] ?? '')) === $taskId);

            if (! is_array($task)) {
                return $this->failure(
                    'E_NOT_FOUND',
                    "Task {$taskId} was not found in the plan for {$code}.",
                    $this->exitForCode('E_NOT_FOUND')
                );
            }

            $taskTitle = trim((string) ($task['title'] ?? ''));
            $trace .= ' | '.$taskId.($taskTitle !== '' ? ': '.$taskTitle : '');
        }

        $paths = array_values(array_filter(array_map(
            static fn (mixed $path): string => trim((string) $path),
            (array) $this->option('path')
        )));

        if ($paths === []) {
            $paths = ['app'];
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $traceability->apply($paths, $trace, $references, ! $dryRun);

        if ($result['scanned_files'] === 0) {
            return $this->failure(
                'E_NOT_FOUND',
                'No PHP files were found in the requested paths.',
                $this->exitForCode('E_NOT_FOUND')
            );
        }

        return $this->success('code_traceability', $result + [
            'trace' => $trace,
            'references' => $references,
            'dry_run' => $dryRun,
        ]);
    }
}
