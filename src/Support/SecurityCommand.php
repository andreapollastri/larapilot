<?php

declare(strict_types=1);

namespace Larapilot\Support;

use InvalidArgumentException;
use Larapilot\Services\SecurityService;
use Throwable;

/**
 * What every `larapilot:security-*` command shares: the scanner must be on,
 * and a failure of the scanner is told apart from a wrong argument.
 */
abstract class SecurityCommand extends LarapilotCommand
{
    protected function scannerOff(SecurityService $security): ?int
    {
        if ($security->enabled()) {
            return null;
        }

        return $this->failure(
            'E_PRECONDITION',
            'No security scanner is set for this project.',
            $this->exitForCode('E_PRECONDITION'),
            'Associate one with: php artisan larapilot:settings-set --security-provider=aikido (SaaS, https://www.aikido.dev) or --security-provider=hackly (self-hosted, https://hackly.web.ap.it).'
        );
    }

    protected function failed(SecurityService $security, Throwable $e): int
    {
        if ($security->isScannerError($e)) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $security->hint($e));
        }

        if ($e instanceof InvalidArgumentException) {
            $missing = str_starts_with($e->getMessage(), 'No spec') || str_contains($e->getMessage(), 'lists no open finding') || str_contains($e->getMessage(), 'reports no open finding') || str_contains($e->getMessage(), 'has no repository');
            $code = $missing ? 'E_NOT_FOUND' : 'E_INVALID_INPUT';

            return $this->failure($code, $e->getMessage(), $this->exitForCode($code));
        }

        throw $e;
    }
}
