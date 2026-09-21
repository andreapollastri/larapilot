<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\EconomicsService;
use Larapilot\Support\LarapilotCommand;

class EconomicsShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:economics-show
                            {--format=json : json or md}';

    protected $description = 'Show the Economics quote, tax, payback, and SaaS forecast';

    public function handle(EconomicsService $economics): int
    {
        $format = strtolower(trim((string) $this->option('format')));

        if ($format === 'md') {
            $this->line($economics->reportMarkdown());

            return self::SUCCESS;
        }

        return $this->success('economics', $economics->snapshot());
    }
}
