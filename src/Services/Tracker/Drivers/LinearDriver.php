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
 * Linear (https://linear.app) over its GraphQL API.
 *
 * Stories become issues on the configured team; plan tasks become native
 * sub-issues via `parentId`. Statuses map onto the team's workflow states.
 */
class LinearDriver extends Driver
{
    protected const ENDPOINT = 'https://api.linear.app/graphql';

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $team = null;

    public function key(): string
    {
        return 'linear';
    }

    public function label(): string
    {
        return 'Linear';
    }

    protected function requiredConfig(): array
    {
        return [
            'api_key' => 'LARAPILOT_LINEAR_API_KEY',
            'team' => 'LARAPILOT_LINEAR_TEAM',
        ];
    }

    protected function client(): PendingRequest
    {
        // Personal API keys go in Authorization verbatim — no Bearer prefix.
        return $this->base()->withHeaders([
            'Authorization' => $this->required('api_key'),
        ]);
    }

    public function ping(): array
    {
        return $this->probe(function (): array {
            $team = $this->team();

            return $this->pong(
                true,
                'Connected to Linear team '.(string) ($team['key'] ?? '?').'.',
                (string) ($team['name'] ?? $team['key'] ?? '')
            );
        });
    }

    public function createStory(StoryPayload $story): RemoteRef
    {
        $input = [
            'teamId' => (string) ($this->team()['id'] ?? ''),
            'title' => $story->remoteTitle(),
            'description' => $story->description,
        ];

        if (($stateId = $this->stateIdForName($story->status)) !== null) {
            $input['stateId'] = $stateId;
        }

        if (($project = $this->setting('project')) !== null) {
            $input['projectId'] = $project;
        }

        if (($priority = $this->priority($story->priority)) !== null) {
            $input['priority'] = $priority;
        }

        if ($story->points > 0) {
            $input['estimate'] = $story->points;
        }

        return $this->issueFrom($this->mutateIssue('issueCreate', ['input' => $input], 'create story'), 'create story');
    }

    public function updateStory(RemoteRef $ref, StoryPayload $story): RemoteRef
    {
        $input = [
            'title' => $story->remoteTitle(),
            'description' => $story->description,
        ];

        if (($stateId = $this->stateIdForName($story->status)) !== null) {
            $input['stateId'] = $stateId;
        }

        if (($priority = $this->priority($story->priority)) !== null) {
            $input['priority'] = $priority;
        }

        if ($story->points > 0) {
            $input['estimate'] = $story->points;
        }

        return $this->issueFrom(
            $this->mutateIssue('issueUpdate', ['id' => $ref->id, 'input' => $input], 'update story'),
            'update story'
        );
    }

    public function readStory(RemoteRef $ref): ?RemoteStory
    {
        // Filtering rather than issue(id:) so a deleted issue comes back as an
        // empty node list instead of a GraphQL error.
        $data = $this->graphql(self::ENDPOINT, <<<'GQL'
            query LarapilotIssue($id: ID!) {
              issues(filter: { id: { eq: $id } }, first: 1) {
                nodes { id identifier url title updatedAt state { name } }
              }
            }
        GQL, ['id' => $ref->id], 'read story');

        $nodes = $this->rows(is_array($data['issues'] ?? null) ? $data['issues'] : [], 'nodes');
        $issue = $nodes[0] ?? null;

        if ($issue === null) {
            return null;
        }

        $state = is_array($issue['state'] ?? null) ? $issue['state'] : [];

        return new RemoteStory(
            $this->refFrom($issue),
            $this->str($state, 'name'),
            $this->str($issue, 'title'),
            $this->str($issue, 'updatedAt'),
        );
    }

    public function createTask(RemoteRef $parent, TaskPayload $task): RemoteRef
    {
        $input = [
            'teamId' => (string) ($this->team()['id'] ?? ''),
            'parentId' => $parent->id,
            'title' => $task->remoteTitle(),
            'description' => $task->description,
        ];

        if (($stateId = $this->stateIdForType($task->done ? 'completed' : 'unstarted')) !== null) {
            $input['stateId'] = $stateId;
        }

        return $this->issueFrom($this->mutateIssue('issueCreate', ['input' => $input], 'create task'), 'create task');
    }

    public function updateTask(RemoteRef $parent, RemoteRef $ref, TaskPayload $task): RemoteRef
    {
        $input = [
            'title' => $task->remoteTitle(),
            'description' => $task->description,
        ];

        if (($stateId = $this->stateIdForType($task->done ? 'completed' : 'unstarted')) !== null) {
            $input['stateId'] = $stateId;
        }

        return $this->issueFrom(
            $this->mutateIssue('issueUpdate', ['id' => $ref->id, 'input' => $input], 'update task'),
            'update task'
        );
    }

    public function removeTask(RemoteRef $parent, RemoteRef $ref): void
    {
        $this->graphql(self::ENDPOINT, <<<'GQL'
            mutation LarapilotIssueDelete($id: String!) {
              issueDelete(id: $id) { success }
            }
        GQL, ['id' => $ref->id], 'remove task');
    }

    public function readComments(RemoteRef $ref): array
    {
        $data = $this->graphql(self::ENDPOINT, <<<'GQL'
            query LarapilotComments($id: ID!) {
              issues(filter: { id: { eq: $id } }, first: 1) {
                nodes { comments { nodes { body createdAt user { name } } } }
              }
            }
        GQL, ['id' => $ref->id], 'read comments');

        $nodes = $this->rows(is_array($data['issues'] ?? null) ? $data['issues'] : [], 'nodes');
        $issue = $nodes[0] ?? null;

        if ($issue === null) {
            return [];
        }

        $comments = [];

        foreach ($this->rows(is_array($issue['comments'] ?? null) ? $issue['comments'] : [], 'nodes') as $node) {
            $body = $this->str($node, 'body');

            if ($body === null) {
                continue;
            }

            $user = is_array($node['user'] ?? null) ? $node['user'] : [];

            $comments[] = new RemoteComment(
                $body,
                $this->str($user, 'name') ?? 'Linear',
                $this->str($node, 'createdAt'),
            );
        }

        return $comments;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------------- */

    /**
     * Team identity plus its workflow states, fetched once per run.
     *
     * @return array<string, mixed>
     */
    protected function team(): array
    {
        if ($this->team !== null) {
            return $this->team;
        }

        $data = $this->graphql(self::ENDPOINT, <<<'GQL'
            query LarapilotTeam($key: String!) {
              teams(filter: { key: { eq: $key } }, first: 1) {
                nodes { id key name states { nodes { id name type position } } }
              }
            }
        GQL, ['key' => $this->required('team')], 'resolve team');

        $nodes = $this->rows(is_array($data['teams'] ?? null) ? $data['teams'] : [], 'nodes');
        $team = $nodes[0] ?? null;

        if ($team === null) {
            throw new TrackerException(
                'Linear team "'.$this->required('team').'" not found. LARAPILOT_LINEAR_TEAM expects the team key (e.g. ENG).'
            );
        }

        return $this->team = $team;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function states(): array
    {
        $states = $this->team()['states'] ?? [];

        return $this->rows(is_array($states) ? $states : [], 'nodes');
    }

    /**
     * A mapped status that matches no workflow state is a configuration
     * error, not a reason to silently drop the field — Linear would happily
     * file the issue in its default state and nobody would notice.
     */
    protected function stateIdForName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $state = $this->matchByName($this->states(), $name);

        if ($state === null) {
            $names = array_values(array_filter(array_map(
                fn (array $row): ?string => $this->str($row, 'name'),
                $this->states()
            )));

            throw new TrackerException(
                'Linear team '.$this->required('team').' has no workflow state named "'.$name.'".'
                .($names === [] ? '' : ' Existing states: '.implode(', ', $names).'.')
                .' Rename the state or adjust larapilot.tracker.providers.linear.status_map.'
            );
        }

        return $this->str($state, 'id');
    }

    /**
     * Sub-issues have no configured status map, so they follow the team's
     * first state of the right kind (`completed` when the task is done).
     */
    protected function stateIdForType(string $type): ?string
    {
        $matches = array_values(array_filter(
            $this->states(),
            fn (array $state): bool => $this->str($state, 'type') === $type
        ));

        usort($matches, fn (array $a, array $b): int => ((float) ($a['position'] ?? 0)) <=> ((float) ($b['position'] ?? 0)));

        return $matches === [] ? null : $this->str($matches[0], 'id');
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    protected function mutateIssue(string $mutation, array $variables, string $action): array
    {
        $signature = $mutation === 'issueCreate'
            ? 'mutation LarapilotIssue($input: IssueCreateInput!) { issueCreate(input: $input)'
            : 'mutation LarapilotIssue($id: String!, $input: IssueUpdateInput!) { issueUpdate(id: $id, input: $input)';

        $data = $this->graphql(
            self::ENDPOINT,
            $signature.' { success issue { id identifier url } } }',
            $variables,
            $action
        );

        $result = $data[$mutation] ?? [];

        return is_array($result) ? $result : [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function issueFrom(array $result, string $action): RemoteRef
    {
        $issue = is_array($result['issue'] ?? null) ? $result['issue'] : [];
        $id = $this->str($issue, 'id');

        if (($result['success'] ?? false) !== true || $id === null) {
            throw TrackerException::api($this->key(), $action, 200, 'Linear reported no issue in the response.');
        }

        return $this->refFrom($issue);
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    protected function refFrom(array $issue): RemoteRef
    {
        return new RemoteRef(
            (string) $this->str($issue, 'id'),
            $this->str($issue, 'identifier'),
            $this->str($issue, 'url'),
        );
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
}
