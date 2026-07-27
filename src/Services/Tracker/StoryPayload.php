<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker;

/**
 * A backlog spec normalized for the remote tracker. `status` is already
 * mapped to the provider's own label — drivers never see Larapilot statuses.
 */
final readonly class StoryPayload
{
    /**
     * @param  list<TaskPayload>  $tasks
     */
    public function __construct(
        public string $code,
        public string $title,
        public string $description,
        public ?string $status,
        public ?string $priority = null,
        public int $points = 0,
        public array $tasks = [],
        // Whether the local status is the workflow's DONE status. Trackers
        // with a completion flag separate from their status vocabulary
        // (Asana, Trello) need this; status-only tools ignore it.
        public bool $completed = false,
    ) {}

    /**
     * Title as it appears in the tracker: the spec code stays in front so a
     * person scanning a board can trace a card back to `.larapilot/`.
     */
    public function remoteTitle(): string
    {
        return $this->code.' — '.$this->title;
    }

    /**
     * Everything that, when changed, warrants an update call. Task state is
     * deliberately excluded — subtasks are reconciled on their own.
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode("\0", [
            $this->remoteTitle(),
            $this->description,
            (string) $this->status,
            (string) $this->priority,
            (string) $this->points,
            $this->completed ? '1' : '0',
        ]));
    }
}
