<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Illuminate\Console\Command;
use Larapilot\Support\Envelope as EnvelopeWriter;

abstract class LarapilotCommand extends Command
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function success(string $kind, array $data): int
    {
        $this->line(EnvelopeWriter::success($kind, $data));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    protected function failure(string $code, string $message, int $exitCode = 1, ?string $hint = null, ?array $details = null): int
    {
        $this->error(EnvelopeWriter::error($code, $message, $hint, $details));

        return $exitCode;
    }

    protected function exitForCode(string $code): int
    {
        return match ($code) {
            'E_INVALID_INPUT' => 2,
            'E_CONNECTOR' => 3,
            'E_PRECONDITION', 'E_NOT_FOUND' => 4,
            default => 1,
        };
    }
}
