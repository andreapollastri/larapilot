<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker;

/**
 * Adapter for one project tracker. Drivers are pure API translation: they
 * receive payloads whose statuses are already mapped to the provider's own
 * vocabulary, and they never decide *what* to sync — that is the sync
 * service's job.
 */
interface TrackerDriver
{
    /**
     * Config key for this provider (`linear`, `jira`, …).
     */
    public function key(): string;

    /**
     * Human label used in CLI output.
     */
    public function label(): string;

    /**
     * Env var names this driver needs but does not have. Empty means ready.
     *
     * @return list<string>
     */
    public function missingConfig(): array;

    /**
     * Credential + destination check. Never throws — a failed ping is a
     * reportable state, not an error.
     *
     * @return array{ok: bool, detail: string, target: string|null}
     */
    public function ping(): array;

    public function createStory(StoryPayload $story): RemoteRef;

    public function updateStory(RemoteRef $ref, StoryPayload $story): RemoteRef;

    /**
     * Current remote state, or null when the record no longer exists (it was
     * deleted in the tracker and the link is stale).
     */
    public function readStory(RemoteRef $ref): ?RemoteStory;

    public function createTask(RemoteRef $parent, TaskPayload $task): RemoteRef;

    public function updateTask(RemoteRef $parent, RemoteRef $ref, TaskPayload $task): RemoteRef;

    /**
     * Drop a subtask whose plan task no longer exists.
     */
    public function removeTask(RemoteRef $parent, RemoteRef $ref): void;

    /**
     * @return list<RemoteComment>
     */
    public function readComments(RemoteRef $ref): array;
}
