<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SecurityService;
use Larapilot\Support\SecurityCommand;

class SecurityStatusCommand extends SecurityCommand
{
    protected $signature = 'larapilot:security-status';

    protected $description = 'Whether the security scanner of this project (Aikido or Hackly) can be read, and what is missing when it cannot';

    public function handle(SecurityService $security): int
    {
        if ($security->provider() === '') {
            return $this->success('security_status', [
                'provider' => null,
                'enabled' => false,
                'ready' => false,
                'providers' => SecurityService::PROVIDERS,
                'hints' => ['No scanner is associated. Choose one: php artisan larapilot:settings-set --security-provider=aikido or --security-provider=hackly'],
            ]);
        }

        return $this->success('security_status', $security->status());
    }
}
