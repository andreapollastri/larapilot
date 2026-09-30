<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\AikidoRegisterWriter;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class AikidoRegisterCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:aikido-register';

    protected $description = 'Write the register of the security findings for the client: every finding that is open, resolved, or ignored with its reason';

    public function handle(AikidoService $aikido, AikidoRegisterWriter $writer, ConfigService $config): int
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
            $register = $aikido->register(true);
        } catch (AikidoException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        }

        $written = $writer->write($register);

        return $this->success('aikido_register', [
            'register' => $written['relative'],
            'language' => $writer->language(),
            'repository' => $register['repository'],
            'generated_at' => $register['generated_at'],
            'truncated' => $register['truncated'],
            'open' => $register['counts']['open']['all'],
            'resolved' => $register['counts']['resolved']['all'],
            'ignored' => $register['counts']['ignored']['all'],
            'without_reason' => count(array_filter($register['ignored'], static fn (array $entry): bool => trim((string) ($entry['reason'] ?? '')) === '')),
        ]);
    }
}
