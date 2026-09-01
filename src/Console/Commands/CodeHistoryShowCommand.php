<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CodeHistoryService;
use Larapilot\Support\LarapilotCommand;

class CodeHistoryShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:code-history
                            {--file= : Filter to entries that touched this path (substring match)}
                            {--spec= : Filter to a US-XXX}
                            {--limit=0 : Cap the returned entry list}';

    protected $description = 'Read-only: where the codebase has been worked on — entries and per-file touchpoints from .larapilot/code-history.yaml';

    public function handle(CodeHistoryService $history): int
    {
        $entries = $history->query([
            'file' => $this->option('file'),
            'spec' => $this->option('spec'),
            'limit' => (int) $this->option('limit'),
        ]);

        return $this->success('code_history', [
            'entries' => $entries,
            'dashboard' => $history->dashboard(),
            'path' => $history->path(),
        ]);
    }
}
