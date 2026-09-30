<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors;

use RuntimeException;

class ErrorTrackerException extends RuntimeException
{
    public function __construct(
        string $message,
        protected ?string $hint = null,
        protected int $httpStatus = 0,
    ) {
        parent::__construct($message);
    }

    public function hint(): ?string
    {
        return $this->hint;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}
