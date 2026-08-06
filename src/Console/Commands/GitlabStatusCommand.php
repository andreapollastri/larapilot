<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\GitlabService;
use Larapilot\Support\LarapilotCommand;

class GitlabStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:gitlab-status';

    protected $description = 'Probe optional GitLab integration (glab CLI, auth, origin remote)';

    public function handle(GitlabService $gitlab): int
    {
        return $this->success('gitlab_status', $gitlab->status());
    }
}
