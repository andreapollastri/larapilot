<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\BoogleService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SpecCode;

class ErrorsLinkCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:errors-link
                            {errors : The errors, by their code or by their key, separated by commas: BUG12 or BUG12,BUG15}
                            {--spec= : The spec of the backlog that fixes them, e.g. US-012}
                            {--ignore : Leave the errors as they are instead of fixing them}
                            {--reason= : Why they are left as they are — required with --ignore}
                            {--forget : Drop what was decided about them}';

    /**
     * The name it had when Boogle was the only tracker.
     *
     * @var list<string>
     */
    protected $aliases = ['larapilot:boogle-link'];

    protected $description = 'Record what was decided about errors of production: the spec that fixes them, or why they are left as they are';

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
        $spec = trim((string) $this->option('spec'));
        $chosen = count(array_filter([$spec !== '', (bool) $this->option('ignore'), (bool) $this->option('forget')]));

        if ($chosen !== 1) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Say one thing about the errors: --spec=US-XXX, --ignore --reason="…", or --forget.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        if ($spec !== '' && ! SpecCode::isValid($spec)) {
            return $this->failure('E_INVALID_INPUT', "Invalid spec code: {$spec}.", $this->exitForCode('E_INVALID_INPUT'));
        }

        try {
            $result = match (true) {
                $spec !== '' => $boogle->link($names, $spec),
                (bool) $this->option('ignore') => $boogle->ignore($names, (string) $this->option('reason')),
                default => $boogle->forget($names),
            };
        } catch (BoogleException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            $missing = str_starts_with($e->getMessage(), 'No spec') || str_contains($e->getMessage(), 'holds no open error');
            $code = $missing ? 'E_NOT_FOUND' : 'E_INVALID_INPUT';

            return $this->failure($code, $e->getMessage(), $this->exitForCode($code));
        }

        return $this->success('errors_link', $result);
    }
}
