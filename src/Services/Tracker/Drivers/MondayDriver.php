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
 * Monday (https://monday.com) over its GraphQL API.
 *
 * Stories become items on the configured board; plan tasks become native
 * subitems. Two Monday specifics shape this driver:
 *
 * - Items have no description field, so the spec body needs a long-text
 *   column (`description_column`). Without one, only title/status/subitems
 *   are pushed.
 * - Subitems live on their own auto-generated board with their own columns,
 *   so subitem status is applied only when that board actually has the
 *   configured status column.
 */
class MondayDriver extends Driver
{
    protected const ENDPOINT = 'https://api.monday.com/v2';

    protected ?string $subitemBoard = null;

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $columnCache = [];

    public function key(): string
    {
        return 'monday';
    }

    public function label(): string
    {
        return 'Monday';
    }

    protected function requiredConfig(): array
    {
        return [
            'api_key' => 'LARAPILOT_MONDAY_TOKEN',
            'board' => 'LARAPILOT_MONDAY_BOARD',
        ];
    }

    protected function client(): PendingRequest
    {
        return $this->base()->withHeaders([
            'Authorization' => $this->required('api_key'),
            'API-Version' => $this->setting('api_version') ?? '2025-04',
        ]);
    }

    public function ping(): array
    {
        return $this->probe(function (): array {
            $data = $this->graphql(self::ENDPOINT, <<<'GQL'
                query LarapilotBoard($ids: [ID!]) {
                  boards(ids: $ids) { id name }
                }
            GQL, ['ids' => [$this->required('board')]], 'resolve board');

            $board = $this->rows($data, 'boards')[0] ?? null;

            if ($board === null) {
                throw new TrackerException('Monday board '.$this->required('board').' not found or not visible to this token.');
            }

            return $this->pong(
                true,
                'Connected to Monday board '.($this->str($board, 'name') ?? $this->required('board')).'.',
                $this->str($board, 'name'),
            );
        });
    }

    public function createStory(StoryPayload $story): RemoteRef
    {
        $variables = [
            'boardId' => $this->required('board'),
            'name' => $story->remoteTitle(),
            'columnValues' => $this->columnValues($story->status, $story->description),
        ];

        if (($group = $this->setting('group')) !== null) {
            $variables['groupId'] = $group;
        }

        $data = $this->graphql(self::ENDPOINT, <<<'GQL'
            mutation LarapilotCreateItem($boardId: ID!, $groupId: String, $name: String!, $columnValues: JSON) {
              create_item(board_id: $boardId, group_id: $groupId, item_name: $name, column_values: $columnValues) {
                id
                url
              }
            }
        GQL, $variables, 'create story');

        return $this->refFrom(is_array($data['create_item'] ?? null) ? $data['create_item'] : [], 'create story');
    }

    public function updateStory(RemoteRef $ref, StoryPayload $story): RemoteRef
    {
        $this->graphql(self::ENDPOINT, <<<'GQL'
            mutation LarapilotUpdateItem($boardId: ID!, $itemId: ID!, $columnValues: JSON!) {
              change_multiple_column_values(board_id: $boardId, item_id: $itemId, column_values: $columnValues) {
                id
              }
            }
        GQL, [
            'boardId' => $this->required('board'),
            'itemId' => $ref->id,
            'columnValues' => $this->columnValues($story->status, $story->description, $story->remoteTitle()),
        ], 'update story');

        return $ref;
    }

    public function readStory(RemoteRef $ref): ?RemoteStory
    {
        $data = $this->graphql(self::ENDPOINT, <<<'GQL'
            query LarapilotItem($ids: [ID!]) {
              items(ids: $ids) {
                id
                name
                url
                updated_at
                column_values { id text }
              }
            }
        GQL, ['ids' => [$ref->id]], 'read story');

        $item = $this->rows($data, 'items')[0] ?? null;

        if ($item === null) {
            return null;
        }

        return new RemoteStory(
            $this->refFrom($item, 'read story', $ref),
            $this->columnText($item, $this->statusColumn()),
            $this->str($item, 'name'),
            $this->str($item, 'updated_at'),
        );
    }

    public function createTask(RemoteRef $parent, TaskPayload $task): RemoteRef
    {
        // The subitem board only exists once the first subitem does, so the
        // subitem is created bare and its status applied straight after.
        $data = $this->graphql(self::ENDPOINT, <<<'GQL'
            mutation LarapilotCreateSubitem($parentId: ID!, $name: String!) {
              create_subitem(parent_item_id: $parentId, item_name: $name) {
                id
                url
                board { id }
              }
            }
        GQL, [
            'parentId' => $parent->id,
            'name' => $task->remoteTitle(),
        ], 'create task');

        $subitem = is_array($data['create_subitem'] ?? null) ? $data['create_subitem'] : [];
        $board = is_array($subitem['board'] ?? null) ? $subitem['board'] : [];

        if (($boardId = $this->str($board, 'id')) !== null) {
            $this->subitemBoard = $boardId;
        }

        $ref = $this->refFrom($subitem, 'create task');

        $this->applyTaskStatus($ref, $task);

        return $ref;
    }

    public function updateTask(RemoteRef $parent, RemoteRef $ref, TaskPayload $task): RemoteRef
    {
        $board = $this->subitemBoardFor($ref);

        if ($board === null) {
            return $ref;
        }

        $values = ['name' => $task->remoteTitle()];

        if (($label = $this->taskStatusLabel($task)) !== null) {
            $values[$this->statusColumn()] = ['label' => $label];
        }

        $this->graphql(self::ENDPOINT, <<<'GQL'
            mutation LarapilotUpdateSubitem($boardId: ID!, $itemId: ID!, $columnValues: JSON!) {
              change_multiple_column_values(board_id: $boardId, item_id: $itemId, column_values: $columnValues) {
                id
              }
            }
        GQL, [
            'boardId' => $board,
            'itemId' => $ref->id,
            'columnValues' => $this->encode($values),
        ], 'update task');

        return $ref;
    }

    public function removeTask(RemoteRef $parent, RemoteRef $ref): void
    {
        $this->graphql(self::ENDPOINT, <<<'GQL'
            mutation LarapilotDeleteItem($itemId: ID!) {
              delete_item(item_id: $itemId) { id }
            }
        GQL, ['itemId' => $ref->id], 'remove task');
    }

    public function readComments(RemoteRef $ref): array
    {
        $data = $this->graphql(self::ENDPOINT, <<<'GQL'
            query LarapilotUpdates($ids: [ID!]) {
              items(ids: $ids) {
                updates { body created_at creator { name } }
              }
            }
        GQL, ['ids' => [$ref->id]], 'read comments');

        $item = $this->rows($data, 'items')[0] ?? null;

        if ($item === null) {
            return [];
        }

        $comments = [];

        foreach ($this->rows($item, 'updates') as $update) {
            $body = $this->str($update, 'body');

            if ($body === null) {
                continue;
            }

            $creator = is_array($update['creator'] ?? null) ? $update['creator'] : [];

            $comments[] = new RemoteComment(
                // Monday stores update bodies as HTML.
                trim(strip_tags($body)),
                $this->str($creator, 'name') ?? 'Monday',
                $this->str($update, 'created_at'),
            );
        }

        return $comments;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------------- */

    protected function statusColumn(): string
    {
        return $this->setting('status_column') ?? 'status';
    }

    /**
     * Monday takes column values as a JSON-encoded string, keyed by column id.
     * `name` is a pseudo-column that renames the item.
     */
    protected function columnValues(?string $status, string $description, ?string $title = null): string
    {
        $values = [];

        if ($title !== null) {
            $values['name'] = $title;
        }

        if ($status !== null && trim($status) !== '') {
            $values[$this->statusColumn()] = ['label' => $status];
        }

        if (($column = $this->setting('description_column')) !== null && $description !== '') {
            $values[$column] = $description;
        }

        return $this->encode($values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function encode(array $values): string
    {
        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function columnText(array $item, string $columnId): ?string
    {
        foreach ($this->rows($item, 'column_values') as $column) {
            if ($this->str($column, 'id') === $columnId) {
                return $this->str($column, 'text');
            }
        }

        return null;
    }

    protected function taskStatusLabel(TaskPayload $task): ?string
    {
        $map = $this->statusMap();
        $label = $task->done
            ? ($map['DONE'] ?? null)
            : ($map['IN PROGRESS'] ?? $map['TODO'] ?? null);

        return is_string($label) && trim($label) !== '' ? $label : null;
    }

    /**
     * Subitem boards are generated by Monday and need not carry the same
     * status column as the parent board, so status is applied only when the
     * column is really there — a missing column is not worth failing a push.
     */
    protected function applyTaskStatus(RemoteRef $ref, TaskPayload $task): void
    {
        $label = $this->taskStatusLabel($task);
        $board = $this->subitemBoardFor($ref);

        if ($label === null || $board === null || ! $this->hasStatusColumn($board)) {
            return;
        }

        $this->graphql(self::ENDPOINT, <<<'GQL'
            mutation LarapilotUpdateSubitem($boardId: ID!, $itemId: ID!, $columnValues: JSON!) {
              change_multiple_column_values(board_id: $boardId, item_id: $itemId, column_values: $columnValues) {
                id
              }
            }
        GQL, [
            'boardId' => $board,
            'itemId' => $ref->id,
            'columnValues' => $this->encode([$this->statusColumn() => ['label' => $label]]),
        ], 'update task status');
    }

    protected function subitemBoardFor(RemoteRef $ref): ?string
    {
        if ($this->subitemBoard !== null) {
            return $this->subitemBoard;
        }

        $data = $this->graphql(self::ENDPOINT, <<<'GQL'
            query LarapilotSubitemBoard($ids: [ID!]) {
              items(ids: $ids) { board { id } }
            }
        GQL, ['ids' => [$ref->id]], 'resolve subitem board');

        $item = $this->rows($data, 'items')[0] ?? null;
        $board = is_array($item['board'] ?? null) ? $item['board'] : [];

        return $this->subitemBoard = $this->str($board, 'id');
    }

    protected function hasStatusColumn(string $boardId): bool
    {
        $columns = $this->columnCache[$boardId] ??= $this->rows(
            $this->rows($this->graphql(self::ENDPOINT, <<<'GQL'
                query LarapilotColumns($ids: [ID!]) {
                  boards(ids: $ids) { columns { id title type } }
                }
            GQL, ['ids' => [$boardId]], 'resolve columns'), 'boards')[0] ?? [],
            'columns'
        );

        foreach ($columns as $column) {
            if ($this->str($column, 'id') === $this->statusColumn()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function refFrom(array $item, string $action, ?RemoteRef $fallback = null): RemoteRef
    {
        $id = $this->str($item, 'id') ?? $fallback?->id;

        if ($id === null) {
            throw TrackerException::api($this->key(), $action, 200, 'Monday returned an item without an id.');
        }

        return new RemoteRef($id, null, $this->str($item, 'url') ?? $fallback?->url);
    }
}
