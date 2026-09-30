<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * Honeybadger: the unresolved faults of one project, over the Data API,
 * with the personal authentication token of a user.
 */
class HoneybadgerDriver extends AbstractErrorDriver
{
    protected const BASE = 'https://app.honeybadger.io';

    protected const PAGE = 25;

    protected const MAX_PAGES = 4;

    public function provider(): string
    {
        return 'honeybadger';
    }

    public function label(): string
    {
        return 'Honeybadger';
    }

    public function host(): string
    {
        return self::BASE;
    }

    protected function requiredConfig(): array
    {
        return [
            'auth_token' => 'Set LARAPILOT_HONEYBADGER_AUTH_TOKEN in .env: the personal authentication token of a user of Honeybadger (profile → Authentication), not the API key the application reports with.',
            'project_id' => 'Set LARAPILOT_HONEYBADGER_PROJECT_ID in .env: the id of the project, as in the address of its page.',
        ];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('larapilot.errors.honeybadger.'.$key, $default);
    }

    public function supportsRemoteResolve(): bool
    {
        return true;
    }

    public function cacheKey(string $projectRoot): string
    {
        return $this->buildCacheKey($projectRoot, $this->projectId());
    }

    public function project(): ?array
    {
        if ($this->projectId() === '') {
            return null;
        }

        $name = trim((string) $this->config('project_name', ''));

        return [
            'id' => $this->projectId(),
            'title' => $name !== '' ? $name : 'Honeybadger project '.$this->projectId(),
            'url' => self::BASE.'/projects/'.rawurlencode($this->projectId()),
            'group' => '',
            'uptime' => false,
            'provider' => $this->provider(),
        ];
    }

    public function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new ErrorTrackerException('The project of Honeybadger is not set.', 'Set LARAPILOT_HONEYBADGER_PROJECT_ID in .env.');
        }

        $url = self::BASE.'/v2/projects/'.rawurlencode($this->projectId()).'/faults';
        $query = ['q' => '-is:resolved -is:ignored', 'order' => 'frequent', 'limit' => self::PAGE];
        $occurrences = [];
        $truncated = false;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = $this->wrapConnection(fn () => $this->client()
                ->withBasicAuth($this->token(), '')
                ->get($url, $query));

            $body = $this->json($response, 'Honeybadger');
            $rows = is_array($body['results'] ?? null) ? $body['results'] : (array_is_list($body) ? $body : []);

            foreach ($rows as $fault) {
                if (is_array($fault) && isset($fault['id']) && ! ($fault['resolved'] ?? false) && ! ($fault['ignored'] ?? false)) {
                    $occurrences[] = $this->fromFault($fault);
                }
            }

            $next = trim((string) ($body['links']['next'] ?? ''));

            if ($next === '' || count($rows) < self::PAGE) {
                break;
            }

            $truncated = $page === self::MAX_PAGES;
            $url = $next;
            $query = [];
        }

        return $this->pack($project, $occurrences, $truncated);
    }

    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void
    {
        $id = trim((string) ($occurrence['id'] ?? ''));

        if ($id === '') {
            throw new ErrorTrackerException('The fault of Honeybadger has no id.');
        }

        $response = $this->wrapConnection(fn () => $this->client()
            ->withBasicAuth($this->token(), '')
            ->put(self::BASE.'/v2/projects/'.rawurlencode((string) $project['id']).'/faults/'.rawurlencode($id), [
                'fault' => ['resolved' => true],
            ]));

        $this->check($response, 'Honeybadger');
    }

    /**
     * @param  array<string, mixed>  $fault
     * @return array<string, mixed>
     */
    protected function fromFault(array $fault): array
    {
        return $this->occurrence(
            (string) $fault['id'],
            '#HB'.$fault['id'],
            trim((string) ($fault['klass'] ?? '')),
            trim((string) ($fault['message'] ?? '')),
            '',
            null,
            $fault['last_notice_at'] ?? $fault['created_at'] ?? null,
            times: (int) ($fault['notices_count'] ?? 1),
            group: (string) $fault['id'],
            url: isset($fault['url']) ? (string) $fault['url'] : null,
        );
    }

    protected function token(): string
    {
        return trim((string) $this->config('auth_token'));
    }

    protected function projectId(): string
    {
        return trim((string) $this->config('project_id'));
    }
}
