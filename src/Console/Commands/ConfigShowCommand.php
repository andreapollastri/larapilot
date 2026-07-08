<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class ConfigShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:config-show';

    protected $description = 'Show Larapilot project configuration and metadata';

    public function handle(ConfigService $config): int
    {
        return $this->success('setup', $config->setupInfo());
    }
}
