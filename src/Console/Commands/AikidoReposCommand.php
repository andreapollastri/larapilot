<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class AikidoReposCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:aikido-repos
                            {--search= : Only the repositories whose name holds these letters}
                            {--use= : Keep this repository as the one this project is, by its id or its exact name in Aikido}
                            {--forget : Drop the choice: the repository is found from the git remote again}';

    protected $description = 'List the repositories of the Aikido workspace, and say which one this project is when the git remote does not find it';

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

        $use = trim((string) $this->option('use'));

        if ($use !== '' && (bool) $this->option('forget')) {
            return $this->failure('E_INVALID_INPUT', 'Say one thing: --use={id} or --forget.', $this->exitForCode('E_INVALID_INPUT'));
        }

        try {
            if ((bool) $this->option('forget')) {
                $aikido->forgetRepository();

                return $this->success('aikido_repository', ['repository' => $aikido->repository(), 'source' => $aikido->repositorySource()]);
            }

            if ($use !== '') {
                $repository = $aikido->useRepository($use);
                $source = $aikido->repositorySource();

                return $this->success('aikido_repository', [
                    'repository' => $repository,
                    'source' => $source,
                    'hint' => $source === 'env'
                        ? 'LARAPILOT_AIKIDO_REPOSITORY is set in .env and wins on this machine: remove it to read the repository that was chosen.'
                        : 'The choice is in .larapilot/aikido.yaml: commit it, so every machine reads the same repository.',
                ]);
            }

            return $this->success('aikido_repositories', $aikido->repositories((string) $this->option('search')));
        } catch (AikidoException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            $code = str_starts_with($e->getMessage(), 'Aikido holds no repository') ? 'E_NOT_FOUND' : 'E_INVALID_INPUT';

            return $this->failure($code, $e->getMessage(), $this->exitForCode($code));
        }
    }
}
