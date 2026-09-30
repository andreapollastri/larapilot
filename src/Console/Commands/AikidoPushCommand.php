<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class AikidoPushCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:aikido-push';

    protected $description = 'Tell Aikido the decisions it was not told yet: ignore the waived findings with their reason, leave a note on the ones in the backlog';

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

        if (! $aikido->pushesDecisions()) {
            return $this->failure(
                'E_PRECONDITION',
                'This project keeps its decisions to itself.',
                $this->exitForCode('E_PRECONDITION'),
                'Remove LARAPILOT_AIKIDO_PUSH_DECISIONS=false from .env to tell Aikido.'
            );
        }

        try {
            $result = $aikido->push();
        } catch (AikidoException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        }

        $refused = array_values(array_filter($result['aikido'], static fn (array $told): bool => ! $told['sent']));

        if ($refused !== []) {
            // The one that stopped the rest says the most: it is the last.
            $last = $refused[count($refused) - 1];

            return $this->failure('E_CONNECTOR', $last['error'], $this->exitForCode('E_CONNECTOR'), $last['hint'] ?? null, $result);
        }

        return $this->success('aikido_push', $result);
    }
}
