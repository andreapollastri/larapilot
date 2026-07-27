<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker;

/**
 * What a story looks like on the remote side right now — the raw material
 * for the drift report. `status` is the provider's own label; mapping it
 * back to a Larapilot status is the sync service's job, not the driver's.
 */
final readonly class RemoteStory
{
    public function __construct(
        public RemoteRef $ref,
        public ?string $status = null,
        public ?string $title = null,
        public ?string $updatedAt = null,
    ) {}
}
