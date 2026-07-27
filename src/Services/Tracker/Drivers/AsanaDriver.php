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
 * Asana (https://asana.com) over REST v1.0 with a personal access token.
 *
 * Stories become tasks in the configured project and are filed into the
 * section their status maps to; plan tasks become native Asana subtasks.
 * Asana tracks completion separately from sections, so a DONE story is both
 * moved to the mapped section and marked complete.
 */
class AsanaDriver extends Driver
{
    protected const ENDPOINT = 'https://app.asana.com/api/1.0';

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $sections = null;

    public function key(): string
    {
        return 'asana';
    }

    public function label(): string
    {
        return 'Asana';
    }

    protected function requiredConfig(): array
    {
        return [
            'api_key' => 'LARAPILOT_ASANA_TOKEN',
            'project' => 'LARAPILOT_ASANA_PROJECT',
        ];
    }

    protected function client(): PendingRequest
    {
        return $this->base()->withToken($this->required('api_key'));
    }

    public function ping(): array
    {
        return $this->probe(function (): array {
            $project = $this->data($this->get(
                self::ENDPOINT.'/projects/'.$this->required('project'),
                ['opt_fields' => 'name'],
                'resolve project'
            ));

            return $this->pong(
                true,
                'Connected to Asana project '.($this->str($project, 'name') ?? $this->required('project')).'.',
                $this->str($project, 'name'),
            );
        });
    }

    public function createStory(StoryPayload $story): RemoteRef
    {
        $created = $this->data($this->post(self::ENDPOINT.'/tasks', [
            'data' => [
                'name' => $story->remoteTitle(),
                'notes' => $story->description,
                'projects' => [$this->required('project')],
                'completed' => $story->completed,
            ],
        ], 'create story'));

        $ref = $this->refFrom($created);

        $this->moveToSection($ref, $story->status);

        return $ref;
    }

    public function updateStory(RemoteRef $ref, StoryPayload $story): RemoteRef
    {
        $updated = $this->data($this->put(self::ENDPOINT.'/tasks/'.$ref->id, [
            'data' => [
                'name' => $story->remoteTitle(),
                'notes' => $story->description,
                'completed' => $story->completed,
            ],
        ], 'update story'));

        $this->moveToSection($ref, $story->status);

        return $this->refFrom($updated, $ref);
    }

    public function readStory(RemoteRef $ref): ?RemoteStory
    {
        $payload = $this->getOrMissing(self::ENDPOINT.'/tasks/'.$ref->id, [
            'opt_fields' => 'name,completed,modified_at,permalink_url,memberships.section.name,memberships.project.gid',
        ], 'read story');

        if ($payload === null) {
            return null;
        }

        $task = $this->data($payload);

        return new RemoteStory(
            $this->refFrom($task, $ref),
            $this->sectionOf($task),
            $this->str($task, 'name'),
            $this->str($task, 'modified_at'),
        );
    }

    public function createTask(RemoteRef $parent, TaskPayload $task): RemoteRef
    {
        $created = $this->data($this->post(self::ENDPOINT.'/tasks/'.$parent->id.'/subtasks', [
            'data' => [
                'name' => $task->remoteTitle(),
                'notes' => $task->description,
                'completed' => $task->done,
            ],
        ], 'create task'));

        return $this->refFrom($created);
    }

    public function updateTask(RemoteRef $parent, RemoteRef $ref, TaskPayload $task): RemoteRef
    {
        $updated = $this->data($this->put(self::ENDPOINT.'/tasks/'.$ref->id, [
            'data' => [
                'name' => $task->remoteTitle(),
                'notes' => $task->description,
                'completed' => $task->done,
            ],
        ], 'update task'));

        return $this->refFrom($updated, $ref);
    }

    public function removeTask(RemoteRef $parent, RemoteRef $ref): void
    {
        $this->delete(self::ENDPOINT.'/tasks/'.$ref->id, [], 'remove task');
    }

    public function readComments(RemoteRef $ref): array
    {
        $payload = $this->getOrMissing(self::ENDPOINT.'/tasks/'.$ref->id.'/stories', [
            'opt_fields' => 'text,type,created_at,created_by.name',
        ], 'read comments');

        if ($payload === null) {
            return [];
        }

        $comments = [];

        foreach ($this->rows($payload, 'data') as $story) {
            // Asana calls every activity entry a "story"; only the ones a
            // human typed are comments.
            if ($this->str($story, 'type') !== 'comment') {
                continue;
            }

            $text = $this->str($story, 'text');

            if ($text === null) {
                continue;
            }

            $author = is_array($story['created_by'] ?? null) ? $story['created_by'] : [];

            $comments[] = new RemoteComment(
                $text,
                $this->str($author, 'name') ?? 'Asana',
                $this->str($story, 'created_at'),
            );
        }

        return $comments;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function data(array $payload): array
    {
        $data = $payload['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Sections of the configured project, fetched once per run.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sections(): array
    {
        return $this->sections ??= $this->rows(
            $this->get(
                self::ENDPOINT.'/projects/'.$this->required('project').'/sections',
                ['opt_fields' => 'name'],
                'resolve sections'
            ),
            'data'
        );
    }

    protected function moveToSection(RemoteRef $ref, ?string $status): void
    {
        if ($status === null || trim($status) === '') {
            return;
        }

        $section = $this->matchByName($this->sections(), $status);

        if ($section === null) {
            $names = array_values(array_filter(array_map(
                fn (array $row): ?string => $this->str($row, 'name'),
                $this->sections()
            )));

            throw new TrackerException(
                'Asana project has no section named "'.$status.'".'
                .($names === [] ? '' : ' Existing sections: '.implode(', ', $names).'.')
                .' Rename the section or adjust larapilot.tracker.providers.asana.status_map.'
            );
        }

        $this->post(self::ENDPOINT.'/sections/'.$this->str($section, 'gid').'/addTask', [
            'data' => ['task' => $ref->id],
        ], 'move story to section');
    }

    /**
     * The section a task sits in, restricted to the configured project — a
     * task can be a member of several projects at once.
     *
     * @param  array<string, mixed>  $task
     */
    protected function sectionOf(array $task): ?string
    {
        $project = $this->required('project');

        foreach ($this->rows($task, 'memberships') as $membership) {
            $membershipProject = is_array($membership['project'] ?? null) ? $membership['project'] : [];

            if ($this->str($membershipProject, 'gid') !== $project) {
                continue;
            }

            $section = is_array($membership['section'] ?? null) ? $membership['section'] : [];

            return $this->str($section, 'name');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $task
     */
    protected function refFrom(array $task, ?RemoteRef $fallback = null): RemoteRef
    {
        $gid = $this->str($task, 'gid') ?? $fallback?->id;

        if ($gid === null) {
            throw TrackerException::api($this->key(), 'read task', 200, 'Asana returned a task without a gid.');
        }

        return new RemoteRef($gid, null, $this->str($task, 'permalink_url') ?? $fallback?->url);
    }
}
