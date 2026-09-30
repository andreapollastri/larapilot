<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * Rollbar: the active items of one project, over the REST API, with a
 * project access token that has the `read` scope — `write` to resolve
 * an item from here.
 */
class RollbarDriver extends AbstractErrorDriver
{
    protected const BASE = 'https://api.rollbar.com';

    protected const PAGE = 100;

    public function provider(): string
    {
        return 'rollbar';
    }

    public function label(): string
    {
        return 'Rollbar';
    }

    public function host(): string
    {
        return 'https://rollbar.com';
    }

    protected function requiredConfig(): array
    {
        return [
            'access_token' => 'Set LARAPILOT_ROLLBAR_ACCESS_TOKEN in .env: a project access token of Rollbar with the read scope (write to resolve items), not the one the application posts with.',
        ];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('larapilot.errors.rollbar.'.$key, $default);
    }

    public function supportsRemoteResolve(): bool
    {
        return true;
    }

    public function cacheKey(string $projectRoot): string
    {
        return $this->buildCacheKey($projectRoot, substr(sha1($this->token()), 0, 12));
    }

    public function project(): ?array
    {
        if ($this->token() === '') {
            return null;
        }

        $name = trim((string) $this->config('project_name', ''));

        return [
            'id' => 'rollbar',
            'title' => $name !== '' ? $name : 'Rollbar project',
            'url' => 'https://rollbar.com/',
            'group' => '',
            'uptime' => false,
            'provider' => $this->provider(),
        ];
    }

    public function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new ErrorTrackerException('The access token of Rollbar is not set.', 'Set LARAPILOT_ROLLBAR_ACCESS_TOKEN in .env.');
        }

        $response = $this->wrapConnection(fn () => $this->client()
            ->withHeaders($this->headers())
            ->get(self::BASE.'/api/1/items', ['status' => 'active', 'page' => 1]));

        $body = $this->json($response, 'Rollbar');
        $result = is_array($body['result'] ?? null) ? $body['result'] : [];
        $rows = is_array($result['items'] ?? null) ? $result['items'] : (array_is_list($result) ? $result : []);
        $occurrences = [];

        foreach ($rows as $item) {
            if (is_array($item) && isset($item['id'])) {
                $occurrences[] = $this->fromItem($item);
            }
        }

        return $this->pack($project, $occurrences, count($rows) >= self::PAGE);
    }

    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void
    {
        $id = trim((string) ($occurrence['id'] ?? ''));

        if ($id === '') {
            throw new ErrorTrackerException('The item of Rollbar has no id.');
        }

        $response = $this->wrapConnection(fn () => $this->client()
            ->withHeaders($this->headers())
            ->patch(self::BASE.'/api/1/item/'.rawurlencode($id), ['status' => 'resolved']));

        $this->check($response, 'Rollbar');
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function fromItem(array $item): array
    {
        // The title of an item thrown by PHP is `Class: message`.
        $title = trim((string) ($item['title'] ?? ''));
        $class = '';
        $message = $title;

        if (preg_match('/^(?P<class>[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)(?::\s*(?P<message>.*))?$/s', $title, $match) === 1) {
            $class = $match['class'];
            $message = trim((string) ($match['message'] ?? ''));
        }

        $counter = trim((string) ($item['counter'] ?? ''));

        return $this->occurrence(
            (string) $item['id'],
            $counter !== '' ? '#RB'.$counter : null,
            $class,
            $message,
            '',
            null,
            $item['last_occurrence_timestamp'] ?? $item['first_occurrence_timestamp'] ?? null,
            times: (int) ($item['total_occurrences'] ?? 1),
            group: (string) $item['id'],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return ['X-Rollbar-Access-Token' => $this->token()];
    }

    protected function token(): string
    {
        return trim((string) $this->config('access_token'));
    }
}
