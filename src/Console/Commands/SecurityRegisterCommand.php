<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SecurityService;
use Larapilot\Support\SecurityCommand;
use Throwable;

class SecurityRegisterCommand extends SecurityCommand
{
    protected $signature = 'larapilot:security-register
                            {--fresh : Ask the scanner again instead of what was read a few minutes ago}';

    protected $description = 'Write the register of security findings for the client — open, resolved, ignored with the reason — in the language of the PRD';

    public function handle(SecurityService $security): int
    {
        if (($off = $this->scannerOff($security)) !== null) {
            return $off;
        }

        try {
            $register = $security->register((bool) $this->option('fresh'));
            $written = $security->writeRegister($register);
        } catch (Throwable $e) {
            return $this->failed($security, $e);
        }

        return $this->success('security_register', [
            'provider' => $security->provider(),
            'path' => $written['relative'],
            'counts' => $register['counts'],
            'truncated' => $register['truncated'],
        ]);
    }
}
