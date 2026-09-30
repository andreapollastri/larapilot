<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * Bugsnag: the open errors of one project, over the Data Access API,
 * with a personal auth token.
 */
class BugsnagDriver extends AbstractErrorDriver
{
    protected const BASE = 'https://api.bugsnag.com';

    protected const PAGE = 100;

    public function provider(): string
    {
        return 'bugsnag';
    }

    public function label(): string
    {
        return 'Bugsnag';
    }

    public function host(): string
    {
        return 'https://app.bugsnag.com';
    }

    protected function requiredConfig(): array
    {
        return [
            'token' => 'Set LARAPILOT_BUGSNAG_AUTH_TOKEN in .env: a personal auth token of Bugsnag (My account → Personal auth tokens), not the API key the application notifies with.',
            'project_id' => 'Set LARAPILOT_BUGSNAG_PROJECT_ID in .env: the id of the project (Project settings → General).',
        ];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('larapilot.errors.bugsnag.'.$key, $default);
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
            'title' => $name !== '' ? $name : $this->projectId(),
            'url' => 'https://app.bugsnag.com/',
            'group' => '',
            'uptime' => false,
            'provider' => $this->provider(),
        ];
    }

    public function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new ErrorTrackerException('The project of Bugsnag is not set.', 'Set LARAPILOT_BUGSNAG_PROJECT_ID in .env.');
        }

        // Bugsnag reads `filters[error.status][]` as a list only without
        // an index between the brackets, which PHP would add.
        $query = http_build_query([
            'sort' => 'last_seen',
            'direction' => 'desc',
            'per_page' => self::PAGE,
            'filters' => ['error.status' => [['type' => 'eq', 'value' => 'open']]],
        ]);
        $query = (string) preg_replace('/%5B\d+%5D/', '%5B%5D', $query);

        $response = $this->wrapConnection(fn () => $this->client()
            ->withHeaders($this->headers())
            ->get(self::BASE.'/projects/'.rawurlencode($this->projectId()).'/errors?'.$query));

        $rows = $this->json($response, 'Bugsnag');
        $occurrences = [];

        foreach ($rows as $error) {
            if (is_array($error) && isset($error['id'])) {
                $occurrences[] = $this->fromError($error);
            }
        }

        return $this->pack($project, $occurrences, count($rows) >= self::PAGE);
    }

    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void
    {
        $id = trim((string) ($occurrence['id'] ?? ''));

        if ($id === '') {
            throw new ErrorTrackerException('The error of Bugsnag has no id.');
        }

        $response = $this->wrapConnection(fn () => $this->client()
            ->withHeaders($this->headers())
            ->patch(self::BASE.'/projects/'.rawurlencode((string) $project['id']).'/errors/'.rawurlencode($id), [
                'operation' => 'fix',
            ]));

        $this->check($response, 'Bugsnag');
    }

    /**
     * @param  array<string, mixed>  $error
     * @return array<string, mixed>
     */
    protected function fromError(array $error): array
    {
        $grouping = is_array($error['grouping_fields'] ?? null) ? $error['grouping_fields'] : [];
        $line = isset($grouping['lineNumber']) && is_numeric($grouping['lineNumber']) ? (int) $grouping['lineNumber'] : null;

        return $this->occurrence(
            (string) $error['id'],
            '#'.$error['id'],
            trim((string) ($error['error_class'] ?? '')),
            trim((string) ($error['message'] ?? '')),
            trim((string) ($grouping['file'] ?? '')),
            $line,
            $error['last_seen'] ?? $error['first_seen'] ?? null,
            $this->request((string) ($error['context'] ?? '')),
            times: (int) ($error['events'] ?? 1),
            group: (string) $error['id'],
            url: isset($error['url']) ? (string) $error['url'] : null,
        );
    }

    /**
     * The context of Bugsnag is the route when the notifier knows it:
     * `GET /admin/customers`. Anything else is not a request.
     */
    protected function request(string $context): ?string
    {
        if (preg_match('/^(?P<method>[A-Z]{3,7})\s+(?P<url>\S+)$/', trim($context), $match) !== 1) {
            return null;
        }

        $path = $this->helper->path($match['url']);

        return $path !== '' ? $match['method'].' '.$path : null;
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'Authorization' => 'token '.trim((string) $this->config('token')),
            'X-Version' => '2',
        ];
    }

    protected function projectId(): string
    {
        return trim((string) $this->config('project_id'));
    }
}
