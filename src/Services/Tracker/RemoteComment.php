<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker;

final readonly class RemoteComment
{
    public function __construct(
        public string $body,
        public string $author = 'unknown',
        public ?string $createdAt = null,
    ) {}

    /**
     * Comments are imported incrementally, so anything without a usable
     * timestamp is treated as older than the cursor rather than re-imported
     * on every pull.
     */
    public function isNewerThan(?string $cursor): bool
    {
        if ($cursor === null) {
            return true;
        }

        if ($this->createdAt === null) {
            return false;
        }

        return strtotime($this->createdAt) > strtotime($cursor);
    }
}
