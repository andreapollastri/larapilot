<?php

declare(strict_types=1);

namespace Larapilot\Services\Aikido;

use RuntimeException;

/**
 * Aikido could not be read. The message is written for the person who has
 * to fix it: what went wrong, and what to check.
 */
class AikidoException extends RuntimeException
{
    public function __construct(string $message, protected ?string $hint = null, protected int $status = 0)
    {
        parent::__construct($message);
    }

    public function hint(): ?string
    {
        return $this->hint;
    }

    public function status(): int
    {
        return $this->status;
    }
}
