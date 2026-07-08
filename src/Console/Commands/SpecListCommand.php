<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecListCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-list
                            {--status= : Filter by workflow status}';

    protected $description = 'List backlog specs and summary metadata';

    public function handle(SpecService $specs): int
    {
        $data = $specs->list($this->option('status'));

        return $this->success('spec_list', $data);
    }
}
