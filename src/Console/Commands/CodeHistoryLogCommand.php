<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeHistoryService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class CodeHistoryLogCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:code-log
                            {--spec= : US-XXX the work belongs to}
                            {--task= : TASK-XX within the spec plan}
                            {--skill= : Larapilot skill / phase name}
                            {--commit= : Explicit commit sha (diffed against its parent)}
                            {--range= : Explicit git range, e.g. develop..HEAD}
                            {--note= : Short note}
                            {--ts= : ISO timestamp override}';

    protected $description = 'Append a code change-history entry (files + line ranges) to .larapilot/code-history.yaml';

    public function handle(ConfigService $config, CodeHistoryService $history): int
    {
        if (! $config->codeHistoryEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Code change history is disabled (settings.code_history = NO).',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --code-history=YES'
            );
        }

        $entry = $history->log([
            'spec' => $this->option('spec'),
            'task' => $this->option('task'),
            'skill' => $this->option('skill'),
            'commit' => $this->option('commit'),
            'range' => $this->option('range'),
            'note' => $this->option('note'),
            'ts' => $this->option('ts'),
        ]);

        return $this->success('code_history', [
            'entry' => $entry,
            'path' => $history->path(),
        ]);
    }
}
