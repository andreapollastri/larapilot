<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\GithubService;
use Larapilot\Support\LarapilotCommand;

class GithubStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:github-status';

    protected $description = 'Probe optional GitHub integration (gh CLI, auth, origin remote)';

    public function handle(GithubService $github): int
    {
        return $this->success('github_status', $github->status());
    }
}
