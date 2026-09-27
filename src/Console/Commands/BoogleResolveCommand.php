<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\BoogleService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class BoogleResolveCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:boogle-resolve
                            {errors : The errors, by a code of Boogle or by their key, separated by commas: BUG12 or BUG12,BUG15}
                            {--status=FIXED : What they become in Boogle: FIXED or DONE}
                            {--comment= : The line Boogle keeps in the history of each one. Default: the spec that fixed it}';

    protected $description = 'Close errors in Boogle once their fix is released: every open occurrence of each one, with what fixed it';

    public function handle(BoogleService $boogle, ConfigService $config): int
    {
        if (! $config->boogleEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Boogle is off for this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --boogle=YES'
            );
        }

        $names = array_values(array_filter(array_map('trim', explode(',', (string) $this->argument('errors'))), static fn (string $name): bool => $name !== ''));

        try {
            $result = $boogle->resolve($names, (string) $this->option('status'), $this->option('comment'));
        } catch (BoogleException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            $code = str_starts_with($e->getMessage(), 'Boogle holds no open error') ? 'E_NOT_FOUND' : 'E_INVALID_INPUT';

            return $this->failure($code, $e->getMessage(), $this->exitForCode($code));
        }

        return $this->success('boogle_resolve', $result);
    }
}
