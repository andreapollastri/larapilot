<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

abstract class ReleaseBranchCommand extends LarapilotCommand
{
    protected function guardReleaseMode(ConfigService $config): ?int
    {
        if ($config->releaseModeEnabled()) {
            return null;
        }

        return $this->failure(
            'E_PRECONDITION',
            'Release mode is disabled (settings.release_mode = NO).',
            $this->exitForCode('E_PRECONDITION'),
            'Enable with: php artisan larapilot:settings-set --release-mode=YES'
        );
    }

    protected function invalidRelease(\InvalidArgumentException $exception): int
    {
        $missing = str_contains($exception->getMessage(), 'not found');

        return $this->failure(
            $missing ? 'E_NOT_FOUND' : 'E_INVALID_INPUT',
            $exception->getMessage(),
            $this->exitForCode($missing ? 'E_NOT_FOUND' : 'E_INVALID_INPUT')
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function flowResult(string $kind, array $result): int
    {
        if (($result['ok'] ?? false) !== true) {
            return $this->failure(
                'E_PRECONDITION',
                (string) ($result['error'] ?? 'Release git step failed.'),
                $this->exitForCode('E_PRECONDITION'),
                null,
                $result
            );
        }

        return $this->success($kind, $result);
    }
}
