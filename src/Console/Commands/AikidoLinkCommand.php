<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SpecCode;

class AikidoLinkCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:aikido-link
                            {issues : The findings, by their number in Aikido, separated by commas: 24 or 24,31}
                            {--spec= : The spec of the backlog that fixes them, e.g. US-012}
                            {--waive : Accept the findings as they are instead of fixing them}
                            {--reason= : Why they are accepted — required with --waive}
                            {--forget : Drop what was decided about them}';

    protected $description = 'Record what was decided about findings of Aikido: the spec that fixes them, or why they are waived';

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

        $ids = [];

        foreach (explode(',', (string) $this->argument('issues')) as $id) {
            $id = trim($id);

            if ($id === '') {
                continue;
            }

            if (! ctype_digit($id)) {
                return $this->failure(
                    'E_INVALID_INPUT',
                    "“{$id}” is not a finding: a finding is named by its number in Aikido, such as 24.",
                    $this->exitForCode('E_INVALID_INPUT')
                );
            }

            $ids[] = (int) $id;
        }

        $spec = trim((string) $this->option('spec'));
        $chosen = count(array_filter([$spec !== '', (bool) $this->option('waive'), (bool) $this->option('forget')]));

        if ($chosen !== 1) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Say one thing about the findings: --spec=US-XXX, --waive --reason="…", or --forget.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        if ($spec !== '' && ! SpecCode::isValid($spec)) {
            return $this->failure('E_INVALID_INPUT', "Invalid spec code: {$spec}.", $this->exitForCode('E_INVALID_INPUT'));
        }

        try {
            $result = match (true) {
                $spec !== '' => $aikido->link($ids, $spec),
                (bool) $this->option('waive') => $aikido->waive($ids, (string) $this->option('reason')),
                default => $aikido->forget($ids),
            };
        } catch (AikidoException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            $missing = str_starts_with($e->getMessage(), 'No spec') || str_starts_with($e->getMessage(), 'Aikido reports no open finding');
            $code = $missing ? 'E_NOT_FOUND' : 'E_INVALID_INPUT';

            return $this->failure($code, $e->getMessage(), $this->exitForCode($code));
        }

        return $this->success('aikido_link', $result);
    }
}
