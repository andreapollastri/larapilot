<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Larapilot\Services\Tracker\RemoteComment;
use Larapilot\Services\Tracker\RemoteRef;
use Larapilot\Services\Tracker\RemoteStory;
use Larapilot\Services\Tracker\StoryPayload;
use Larapilot\Services\Tracker\TaskPayload;
use Larapilot\Services\Tracker\TrackerException;

/**
 * ClickUp (https://clickup.com) over API v2 with a personal token.
 *
 * Stories become tasks in the configured list; plan tasks become native
 * subtasks (a task carrying `parent`). Statuses map onto the list's own
 * status set, which ClickUp compares case-insensitively.
 */
class ClickUpDriver extends Driver
{
    protected const ENDPOINT = 'https://api.clickup.com/api/v2';

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $statuses = null;

    public function key(): string
    {
        return 'clickup';
    }

    public function label(): string
    {
        return 'ClickUp';
    }

    protected function requiredConfig(): array
    {
        return [
            'api_key' => 'LARAPILOT_CLICKUP_TOKEN',
            'list' => 'LARAPILOT_CLICKUP_LIST',
        ];
    }

    protected function client(): PendingRequest
    {
        // ClickUp personal tokens go in Authorization as-is — no Bearer.
        return $this->base()->withHeaders([
            'Authorization' => $this->required('api_key'),
        ]);
    }

    public function ping(): array
    {
        return $this->probe(function (): array {
            $list = $this->list();

            return $this->pong(
                true,
                'Connected to ClickUp list '.($this->str($list, 'name') ?? $this->required('list')).'.',
                $this->str($list, 'name'),
            );
        });
    }

    public function createStory(StoryPayload $story): RemoteRef
    {
        $payload = [
            'name' => $story->remoteTitle(),
            'markdown_description' => $story->description,
        ];

        if (($status = $this->statusFor($story->status)) !== null) {
            $payload['status'] = $status;
        }

        if (($priority = $this->priority($story->priority)) !== null) {
            $payload['priority'] = $priority;
        }

        return $this->refFrom($this->post(
            self::ENDPOINT.'/list/'.$this->required('list').'/task',
            $payload,
            'create story'
        ));
    }

    public function updateStory(RemoteRef $ref, StoryPayload $story): RemoteRef
    {
        $payload = [
            'name' => $story->remoteTitle(),
            'markdown_description' => $story->description,
        ];

        if (($status = $this->statusFor($story->status)) !== null) {
            $payload['status'] = $status;
        }

        if (($priority = $this->priority($story->priority)) !== null) {
            $payload['priority'] = $priority;
        }

        return $this->refFrom($this->put(self::ENDPOINT.'/task/'.$ref->id, $payload, 'update story'), $ref);
    }

    public function readStory(RemoteRef $ref): ?RemoteStory
    {
        $task = $this->getOrMissing(self::ENDPOINT.'/task/'.$ref->id, [], 'read story');

        if ($task === null) {
            return null;
        }

        $status = is_array($task['status'] ?? null) ? $task['status'] : [];

        return new RemoteStory(
            $this->refFrom($task, $ref),
            $this->str($status, 'status'),
            $this->str($task, 'name'),
            $this->timestamp($this->str($task, 'date_updated')),
        );
    }

    public function createTask(RemoteRef $parent, TaskPayload $task): RemoteRef
    {
        $payload = [
            'name' => $task->remoteTitle(),
            'markdown_description' => $task->description,
            'parent' => $parent->id,
        ];

        if (($status = $this->statusFor($this->doneStatus())) !== null && $task->done) {
            $payload['status'] = $status;
        }

        return $this->refFrom($this->post(
            self::ENDPOINT.'/list/'.$this->required('list').'/task',
            $payload,
            'create task'
        ));
    }

    public function updateTask(RemoteRef $parent, RemoteRef $ref, TaskPayload $task): RemoteRef
    {
        $payload = [
            'name' => $task->remoteTitle(),
            'markdown_description' => $task->description,
        ];

        $target = $task->done ? $this->doneStatus() : $this->openStatus();

        if (($status = $this->statusFor($target)) !== null) {
            $payload['status'] = $status;
        }

        return $this->refFrom($this->put(self::ENDPOINT.'/task/'.$ref->id, $payload, 'update task'), $ref);
    }

    public function removeTask(RemoteRef $parent, RemoteRef $ref): void
    {
        $this->delete(self::ENDPOINT.'/task/'.$ref->id, [], 'remove task');
    }

    public function readComments(RemoteRef $ref): array
    {
        $payload = $this->getOrMissing(self::ENDPOINT.'/task/'.$ref->id.'/comment', [], 'read comments');

        if ($payload === null) {
            return [];
        }

        $comments = [];

        foreach ($this->rows($payload, 'comments') as $comment) {
            $text = $this->str($comment, 'comment_text');

            if ($text === null) {
                continue;
            }

            $user = is_array($comment['user'] ?? null) ? $comment['user'] : [];

            $comments[] = new RemoteComment(
                $text,
                $this->str($user, 'username') ?? 'ClickUp',
                $this->timestamp($this->str($comment, 'date')),
            );
        }

        return $comments;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    protected function list(): array
    {
        return $this->get(self::ENDPOINT.'/list/'.$this->required('list'), [], 'resolve list');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function statuses(): array
    {
        return $this->statuses ??= $this->rows($this->list(), 'statuses');
    }

    /**
     * ClickUp rejects a status the list does not define, so the mapped value
     * is validated against the list before it is sent.
     */
    protected function statusFor(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        $match = $this->matchByName($this->statuses(), $status, 'status');

        if ($match === null) {
            $names = array_values(array_filter(array_map(
                fn (array $row): ?string => $this->str($row, 'status'),
                $this->statuses()
            )));

            throw new TrackerException(
                'ClickUp list has no status named "'.$status.'".'
                .($names === [] ? '' : ' Available statuses: '.implode(', ', $names).'.')
                .' Adjust larapilot.tracker.providers.clickup.status_map.'
            );
        }

        return $this->str($match, 'status');
    }

    protected function doneStatus(): ?string
    {
        $done = $this->statusMap()['DONE'] ?? null;

        return is_string($done) && trim($done) !== '' ? $done : null;
    }

    protected function openStatus(): ?string
    {
        $open = $this->statusMap()['IN PROGRESS'] ?? $this->statusMap()['TODO'] ?? null;

        return is_string($open) && trim($open) !== '' ? $open : null;
    }

    /**
     * ClickUp timestamps are milliseconds since the epoch as a string.
     */
    protected function timestamp(?string $value): ?string
    {
        if ($value === null || ! ctype_digit($value)) {
            return $value;
        }

        return date('c', (int) ((int) $value / 1000));
    }

    protected function priority(?string $priority): ?int
    {
        return match (strtoupper(trim((string) $priority))) {
            'CRITICAL' => 1,
            'HIGH' => 2,
            'MEDIUM' => 3,
            'LOW' => 4,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $task
     */
    protected function refFrom(array $task, ?RemoteRef $fallback = null): RemoteRef
    {
        $id = $this->str($task, 'id') ?? $fallback?->id;

        if ($id === null) {
            throw TrackerException::api($this->key(), 'read task', 200, 'ClickUp returned a task without an id.');
        }

        return new RemoteRef(
            $id,
            $this->str($task, 'custom_id') ?? $fallback?->key,
            $this->str($task, 'url') ?? $fallback?->url,
        );
    }
}
