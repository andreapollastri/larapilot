<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\BoogleService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class ErrorsResolveCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:errors-resolve
                            {errors : The errors, by their code or by their key, separated by commas: BUG12 or BUG12,BUG15}
                            {--status=FIXED : What they become in Boogle: FIXED or DONE. Another tracker has one way to close}
                            {--comment= : The line Boogle keeps in the history of each one. Default: the spec that fixed it}';

    /**
     * The name it had when Boogle was the only tracker.
     *
     * @var list<string>
     */
    protected $aliases = ['larapilot:boogle-resolve'];

    protected $description = 'Close errors in the tracker once their fix is released: every open occurrence of each one, with what fixed it (Boogle, Sentry, Bugsnag, Flare, Datadog, Rollbar, Honeybadger)';

    public function handle(BoogleService $boogle, ConfigService $config): int
    {
        if (! $config->errorsEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Production errors are off for this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --errors=YES --errors-provider=boogle (or sentry, bugsnag, flare, datadog, rollbar, honeybadger, cloudwatch)'
            );
        }

        $names = array_values(array_filter(array_map('trim', explode(',', (string) $this->argument('errors'))), static fn (string $name): bool => $name !== ''));

        try {
            $result = $boogle->resolve($names, (string) $this->option('status'), $this->option('comment'));
        } catch (BoogleException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            $code = str_contains($e->getMessage(), 'holds no open error') ? 'E_NOT_FOUND' : 'E_INVALID_INPUT';

            return $this->failure($code, $e->getMessage(), $this->exitForCode($code));
        }

        return $this->success('errors_resolve', $result);
    }
}
