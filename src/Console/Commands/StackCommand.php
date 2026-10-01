<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ProjectStackService;
use Larapilot\Support\LarapilotCommand;

class StackCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:stack
                            {--only= : Comma-separated sections: project, php, laravel, database, drivers, packages, frontend, tooling, pins}
                            {--no-db : Do not connect to the database to read its version}';

    protected $description = 'What the project runs on — PHP, Laravel, database, packages, frontend, tooling — and where each version stands in its support window';

    public function handle(ProjectStackService $stack): int
    {
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $unknown = array_values(array_diff($only, ProjectStackService::SECTIONS));

        if ($unknown !== []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Unknown section: '.implode(', ', $unknown).'.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Sections: '.implode(', ', ProjectStackService::SECTIONS)
            );
        }

        return $this->success('stack', $stack->facts($only, ! $this->option('no-db')));
    }
}
