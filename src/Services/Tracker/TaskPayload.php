<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker;

/**
 * One plan task, mirrored as a native sub-issue/subtask under its story.
 */
final readonly class TaskPayload
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description = '',
        public bool $done = false,
        public ?string $type = null,
    ) {}

    public function remoteTitle(): string
    {
        return $this->id.' — '.$this->title;
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode("\0", [
            $this->remoteTitle(),
            $this->description,
            $this->done ? '1' : '0',
        ]));
    }
}
