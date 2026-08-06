<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\BitbucketService;
use Larapilot\Support\LarapilotCommand;

class BitbucketStatusCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:bitbucket-status';

    protected $description = 'Probe optional Bitbucket Cloud integration (tokens, origin remote)';

    public function handle(BitbucketService $bitbucket): int
    {
        return $this->success('bitbucket_status', $bitbucket->status());
    }
}
