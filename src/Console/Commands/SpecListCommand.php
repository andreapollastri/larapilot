<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecListCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-list
                            {--status= : Filter by workflow status}
                            {--full : Every field of every spec, bodies and status history included}';

    protected $description = 'List backlog specs and summary metadata';

    public function handle(SpecService $specs): int
    {
        $status = $this->option('status');

        return $this->success(
            'spec_list',
            $this->option('full') ? $specs->list($status) : $specs->overview($status)
        );
    }
}
