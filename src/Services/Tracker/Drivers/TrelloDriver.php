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
 * Trello (https://trello.com) over REST v1, authenticated with an API key
 * and token pair passed as query parameters.
 *
 * Trello has no status field: a card's column *is* its status, so the status
 * map names lists on the configured board. Trello also has no sub-cards, so
 * plan tasks become check items on a dedicated checklist — the closest thing
 * Trello has to a native subtask.
 */
class TrelloDriver extends Driver
{
    protected const ENDPOINT = 'https://api.trello.com/1';

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $lists = null;

    public function key(): string
    {
        return 'trello';
    }

    public function label(): string
    {
        return 'Trello';
    }

    protected function requiredConfig(): array
    {
        return [
            'api_key' => 'LARAPILOT_TRELLO_KEY',
            'token' => 'LARAPILOT_TRELLO_TOKEN',
            'board' => 'LARAPILOT_TRELLO_BOARD',
        ];
    }

    protected function client(): PendingRequest
    {
        return $this->base();
    }

    public function ping(): array
    {
        return $this->probe(function (): array {
            $board = $this->get($this->url('/boards/'.$this->required('board'), ['fields' => 'name']), [], 'resolve board');

            return $this->pong(
                true,
                'Connected to Trello board '.($this->str($board, 'name') ?? $this->required('board')).'.',
                $this->str($board, 'name'),
            );
        });
    }

    public function createStory(StoryPayload $story): RemoteRef
    {
        $card = $this->post($this->url('/cards', [
            'idList' => $this->listIdFor($story->status),
            'name' => $story->remoteTitle(),
            'desc' => $story->description,
        ]), [], 'create story');

        return $this->refFrom($card);
    }

    public function updateStory(RemoteRef $ref, StoryPayload $story): RemoteRef
    {
        $card = $this->put($this->url('/cards/'.$ref->id, [
            'idList' => $this->listIdFor($story->status),
            'name' => $story->remoteTitle(),
            'desc' => $story->description,
        ]), [], 'update story');

        return $this->refFrom($card, $ref);
    }

    public function readStory(RemoteRef $ref): ?RemoteStory
    {
        $card = $this->getOrMissing(
            $this->url('/cards/'.$ref->id, ['fields' => 'name,desc,idList,url,dateLastActivity']),
            [],
            'read story'
        );

        if ($card === null) {
            return null;
        }

        return new RemoteStory(
            $this->refFrom($card, $ref),
            $this->listNameFor($this->str($card, 'idList')),
            $this->str($card, 'name'),
            $this->str($card, 'dateLastActivity'),
        );
    }

    public function createTask(RemoteRef $parent, TaskPayload $task): RemoteRef
    {
        $item = $this->post($this->url('/checklists/'.$this->checklistId($parent).'/checkItems', [
            'name' => $task->remoteTitle(),
            'checked' => $task->done ? 'true' : 'false',
        ]), [], 'create task');

        $id = $this->str($item, 'id');

        if ($id === null) {
            throw TrackerException::api($this->key(), 'create task', 200, 'Trello returned a check item without an id.');
        }

        return new RemoteRef($id, null, $parent->url);
    }

    public function updateTask(RemoteRef $parent, RemoteRef $ref, TaskPayload $task): RemoteRef
    {
        $this->put($this->url('/cards/'.$parent->id.'/checkItem/'.$ref->id, [
            'name' => $task->remoteTitle(),
            'state' => $task->done ? 'complete' : 'incomplete',
        ]), [], 'update task');

        return $ref;
    }

    public function removeTask(RemoteRef $parent, RemoteRef $ref): void
    {
        $this->delete($this->url('/cards/'.$parent->id.'/checkItem/'.$ref->id), [], 'remove task');
    }

    public function readComments(RemoteRef $ref): array
    {
        $actions = $this->getOrMissing(
            $this->url('/cards/'.$ref->id.'/actions', ['filter' => 'commentCard']),
            [],
            'read comments'
        );

        if ($actions === null) {
            return [];
        }

        $comments = [];

        foreach (array_values(array_filter($actions, 'is_array')) as $action) {
            $data = is_array($action['data'] ?? null) ? $action['data'] : [];
            $text = $this->str($data, 'text');

            if ($text === null) {
                continue;
            }

            $member = is_array($action['memberCreator'] ?? null) ? $action['memberCreator'] : [];

            $comments[] = new RemoteComment(
                $text,
                $this->str($member, 'fullName') ?? 'Trello',
                $this->str($action, 'date'),
            );
        }

        return $comments;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------------- */

    /**
     * Trello authenticates with query parameters, so credentials ride on the
     * URL rather than in a header.
     *
     * @param  array<string, string>  $query
     */
    protected function url(string $path, array $query = []): string
    {
        return self::ENDPOINT.$path.'?'.http_build_query(array_merge($query, [
            'key' => $this->required('api_key'),
            'token' => $this->required('token'),
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function lists(): array
    {
        if ($this->lists !== null) {
            return $this->lists;
        }

        $payload = $this->get(
            $this->url('/boards/'.$this->required('board').'/lists', ['fields' => 'name']),
            [],
            'resolve lists'
        );

        return $this->lists = array_values(array_filter($payload, 'is_array'));
    }

    protected function listIdFor(?string $status): string
    {
        $list = $this->matchByName($this->lists(), $status);

        if ($list === null) {
            $names = array_values(array_filter(array_map(
                fn (array $row): ?string => $this->str($row, 'name'),
                $this->lists()
            )));

            throw new TrackerException(
                'Trello board has no list named "'.(string) $status.'".'
                .($names === [] ? '' : ' Existing lists: '.implode(', ', $names).'.')
                .' Rename the list or adjust larapilot.tracker.providers.trello.status_map.'
            );
        }

        return (string) $this->str($list, 'id');
    }

    protected function listNameFor(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        foreach ($this->lists() as $list) {
            if ($this->str($list, 'id') === $id) {
                return $this->str($list, 'name');
            }
        }

        return null;
    }

    /**
     * The card's plan-task checklist, created on first use so a card synced
     * before it had a plan picks one up later.
     */
    protected function checklistId(RemoteRef $card): string
    {
        $name = $this->setting('checklist') ?? 'Plan tasks';

        $existing = $this->get($this->url('/cards/'.$card->id.'/checklists', ['fields' => 'name']), [], 'resolve checklist');
        $match = $this->matchByName(array_values(array_filter($existing, 'is_array')), $name);

        if ($match !== null) {
            return (string) $this->str($match, 'id');
        }

        $created = $this->post($this->url('/cards/'.$card->id.'/checklists', ['name' => $name]), [], 'create checklist');
        $id = $this->str($created, 'id');

        if ($id === null) {
            throw TrackerException::api($this->key(), 'create checklist', 200, 'Trello returned a checklist without an id.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    protected function refFrom(array $card, ?RemoteRef $fallback = null): RemoteRef
    {
        $id = $this->str($card, 'id') ?? $fallback?->id;

        if ($id === null) {
            throw TrackerException::api($this->key(), 'read card', 200, 'Trello returned a card without an id.');
        }

        return new RemoteRef(
            $id,
            $this->str($card, 'idShort') ?? $fallback?->key,
            $this->str($card, 'url') ?? $fallback?->url,
        );
    }
}
