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
 * Jira Cloud (https://www.atlassian.com) over REST v2.
 *
 * v2 rather than v3 on purpose: v3 requires descriptions in Atlassian
 * Document Format, while v2 accepts the plain text we already generate.
 *
 * Stories become issues of the configured type; plan tasks become native
 * Jira subtasks under them. Status changes go through workflow transitions,
 * so a status is only reachable if the workflow allows it from where the
 * issue currently sits.
 */
class JiraDriver extends Driver
{
    public function key(): string
    {
        return 'jira';
    }

    public function label(): string
    {
        return 'Jira';
    }

    protected function requiredConfig(): array
    {
        return [
            'base_url' => 'LARAPILOT_JIRA_BASE_URL',
            'email' => 'LARAPILOT_JIRA_EMAIL',
            'api_key' => 'LARAPILOT_JIRA_API_TOKEN',
            'project' => 'LARAPILOT_JIRA_PROJECT',
        ];
    }

    protected function client(): PendingRequest
    {
        return $this->base()->withBasicAuth($this->required('email'), $this->required('api_key'));
    }

    public function ping(): array
    {
        return $this->probe(function (): array {
            $project = $this->get($this->url('/project/'.$this->required('project')), [], 'resolve project');

            return $this->pong(
                true,
                'Connected to Jira project '.$this->required('project').'.',
                $this->str($project, 'name') ?? $this->required('project'),
            );
        });
    }

    public function createStory(StoryPayload $story): RemoteRef
    {
        $created = $this->post($this->url('/issue'), [
            'fields' => [
                'project' => ['key' => $this->required('project')],
                'summary' => $story->remoteTitle(),
                'description' => $story->description,
                'issuetype' => ['name' => $this->setting('issue_type') ?? 'Task'],
            ],
        ], 'create story');

        $ref = $this->refFrom($created);

        $this->transitionTo($ref, $story->status);

        return $ref;
    }

    public function updateStory(RemoteRef $ref, StoryPayload $story): RemoteRef
    {
        $this->put($this->url('/issue/'.$ref->id), [
            'fields' => [
                'summary' => $story->remoteTitle(),
                'description' => $story->description,
            ],
        ], 'update story');

        $this->transitionTo($ref, $story->status);

        return $ref;
    }

    public function readStory(RemoteRef $ref): ?RemoteStory
    {
        $issue = $this->getOrMissing(
            $this->url('/issue/'.$ref->id),
            ['fields' => 'summary,status,updated'],
            'read story'
        );

        if ($issue === null) {
            return null;
        }

        $fields = is_array($issue['fields'] ?? null) ? $issue['fields'] : [];
        $status = is_array($fields['status'] ?? null) ? $fields['status'] : [];

        return new RemoteStory(
            $this->refFrom($issue),
            $this->str($status, 'name'),
            $this->str($fields, 'summary'),
            $this->str($fields, 'updated'),
        );
    }

    public function createTask(RemoteRef $parent, TaskPayload $task): RemoteRef
    {
        $created = $this->post($this->url('/issue'), [
            'fields' => [
                'project' => ['key' => $this->required('project')],
                'parent' => ['key' => $parent->key ?? $parent->id],
                'summary' => $task->remoteTitle(),
                'description' => $task->description,
                'issuetype' => ['name' => $this->setting('subtask_type') ?? 'Sub-task'],
            ],
        ], 'create task');

        $ref = $this->refFrom($created);

        if ($task->done) {
            $this->transitionTo($ref, $this->doneStatus());
        }

        return $ref;
    }

    public function updateTask(RemoteRef $parent, RemoteRef $ref, TaskPayload $task): RemoteRef
    {
        $this->put($this->url('/issue/'.$ref->id), [
            'fields' => [
                'summary' => $task->remoteTitle(),
                'description' => $task->description,
            ],
        ], 'update task');

        $this->transitionTo($ref, $task->done ? $this->doneStatus() : null);

        return $ref;
    }

    public function removeTask(RemoteRef $parent, RemoteRef $ref): void
    {
        $this->delete($this->url('/issue/'.$ref->id), [], 'remove task');
    }

    public function readComments(RemoteRef $ref): array
    {
        $payload = $this->getOrMissing($this->url('/issue/'.$ref->id.'/comment'), [], 'read comments');

        if ($payload === null) {
            return [];
        }

        $comments = [];

        foreach ($this->rows($payload, 'comments') as $comment) {
            $body = $this->str($comment, 'body');

            if ($body === null) {
                continue;
            }

            $author = is_array($comment['author'] ?? null) ? $comment['author'] : [];

            $comments[] = new RemoteComment(
                $body,
                $this->str($author, 'displayName') ?? 'Jira',
                $this->str($comment, 'created'),
            );
        }

        return $comments;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------------- */

    protected function url(string $path): string
    {
        return rtrim($this->required('base_url'), '/').'/rest/api/2'.$path;
    }

    /**
     * Move an issue to the named status, if a transition leading there is
     * available. A workflow that does not allow the jump is reported rather
     * than silently ignored — a board stuck in the wrong column is worse
     * than a loud failure.
     */
    protected function transitionTo(RemoteRef $ref, ?string $status): void
    {
        if ($status === null || trim($status) === '') {
            return;
        }

        $current = $this->getOrMissing($this->url('/issue/'.$ref->id), ['fields' => 'status'], 'read status');

        if ($current !== null) {
            $fields = is_array($current['fields'] ?? null) ? $current['fields'] : [];
            $currentStatus = is_array($fields['status'] ?? null) ? $fields['status'] : [];

            if (mb_strtolower(trim((string) $this->str($currentStatus, 'name'))) === mb_strtolower(trim($status))) {
                return;
            }
        }

        $payload = $this->get($this->url('/issue/'.$ref->id.'/transitions'), [], 'read transitions');
        $transitions = $this->rows($payload, 'transitions');
        $match = null;

        foreach ($transitions as $transition) {
            $to = is_array($transition['to'] ?? null) ? $transition['to'] : [];

            if (mb_strtolower(trim((string) $this->str($to, 'name'))) === mb_strtolower(trim($status))) {
                $match = $transition;
                break;
            }
        }

        // Some workflows name the transition, not the destination.
        $match ??= $this->matchByName($transitions, $status);

        if ($match === null) {
            $available = array_values(array_filter(array_map(
                fn (array $transition): ?string => $this->str(
                    is_array($transition['to'] ?? null) ? $transition['to'] : [],
                    'name'
                ),
                $transitions
            )));

            throw new TrackerException(
                'Jira has no transition to "'.$status.'" from the current status of '.$ref->label().'.'
                .($available === [] ? '' : ' Reachable now: '.implode(', ', $available).'.')
            );
        }

        $this->post($this->url('/issue/'.$ref->id.'/transitions'), [
            'transition' => ['id' => (string) $this->str($match, 'id')],
        ], 'transition issue');
    }

    /**
     * Subtasks have no status map of their own, so a completed plan task
     * follows whatever the DONE story status is configured to be.
     */
    protected function doneStatus(): ?string
    {
        $map = $this->statusMap();
        $done = $map['DONE'] ?? null;

        return is_string($done) && trim($done) !== '' ? $done : null;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    protected function refFrom(array $issue): RemoteRef
    {
        $issueKey = $this->str($issue, 'key');

        return new RemoteRef(
            (string) ($this->str($issue, 'id') ?? $issueKey),
            $issueKey,
            $issueKey === null ? null : rtrim($this->required('base_url'), '/').'/browse/'.$issueKey,
        );
    }
}
