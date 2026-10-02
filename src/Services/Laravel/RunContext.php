<?php

declare(strict_types=1);

namespace Larapilot\Services\Laravel;

use Throwable;

/**
 * What the process was doing when it sent a mail or dumped a value: the
 * request it was answering, or the artisan command it was running.
 */
final class RunContext
{
    public static function describe(): string
    {
        try {
            if (app()->runningInConsole()) {
                // The name of the command and no more: an argument may be a
                // password.
                $command = ((array) ($_SERVER['argv'] ?? []))[1] ?? null;

                return mb_substr('artisan '.(is_string($command) && $command !== '' ? $command : '(no command)'), 0, 200);
            }

            $request = request();

            return mb_substr($request->method().' /'.ltrim($request->path(), '/'), 0, 200);
        } catch (Throwable) {
            return '';
        }
    }
}
