<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\DiagnosticsService;
use Larapilot\Support\LarapilotCommand;

class DiagnosticsCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:diagnostics
                            {--lines= : Number of log lines to return (default from config)}
                            {--no-logs : Skip log tail; return status and checks only}';

    protected $description = 'Collect a read-only runtime diagnostics snapshot (status, health checks, redacted log tail)';

    public function handle(DiagnosticsService $diagnostics): int
    {
        $linesOption = $this->option('lines');
        $lines = is_numeric($linesOption) ? (int) $linesOption : null;
        $includeLogs = ! (bool) $this->option('no-logs');

        return $this->success('diagnostics', $diagnostics->snapshot($lines, $includeLogs));
    }
}
