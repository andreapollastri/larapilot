<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class AikidoScanCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:aikido-scan';

    protected $description = 'Ask Aikido to scan this repository again (dependencies, code, infrastructure, secrets)';

    public function handle(AikidoService $aikido, ConfigService $config): int
    {
        if (! $config->aikidoEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Aikido is off for this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --aikido=YES'
            );
        }

        try {
            $result = $aikido->scan();
        } catch (AikidoException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        }

        return $this->success('aikido_scan', array_merge($result, [
            'hint' => 'The scan runs at Aikido and takes a few minutes. Read the result with: php artisan larapilot:aikido-issues',
        ]));
    }
}
