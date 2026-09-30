<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * Datadog: the open issues of one service in Error Tracking — or, when
 * the project only sends logs, the error logs of the last two weeks —
 * with an API key and an application key.
 */
class DatadogDriver extends AbstractErrorDriver
{
    protected const PAGE = 100;

    /** @var list<string> */
    protected const TRACKS = ['trace', 'logs', 'rum'];

    public function provider(): string
    {
        return 'datadog';
    }

    public function label(): string
    {
        return 'Datadog';
    }

    public function host(): string
    {
        return 'https://app.'.$this->site();
    }

    protected function requiredConfig(): array
    {
        return [
            'api_key' => 'Set LARAPILOT_DATADOG_API_KEY (or DD_API_KEY) in .env.',
            'application_key' => 'Set LARAPILOT_DATADOG_APP_KEY (or DD_APP_KEY) in .env: an application key of a user who can read Error Tracking.',
        ];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('larapilot.errors.datadog.'.$key, $default);
    }

    public function granularity(): string
    {
        return $this->source() === 'logs' ? self::OCCURRENCE : self::ISSUE;
    }

    public function supportsRemoteResolve(): bool
    {
        return $this->source() !== 'logs';
    }

    public function cacheKey(string $projectRoot): string
    {
        return $this->buildCacheKey($projectRoot, $this->site(), $this->service(), $this->source(), $this->track());
    }

    public function project(): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        return [
            'id' => $this->service(),
            'title' => $this->service(),
            'url' => $this->host().'/'.($this->source() === 'logs' ? 'logs' : 'error-tracking'),
            'group' => 'Datadog',
            'uptime' => false,
            'provider' => $this->provider(),
        ];
    }

    public function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new ErrorTrackerException('The keys of Datadog are not set.', 'Set LARAPILOT_DATADOG_API_KEY and LARAPILOT_DATADOG_APP_KEY in .env.');
        }

        return $this->source() === 'logs'
            ? $this->downloadFromLogs($project)
            : $this->downloadFromErrorTracking($project);
    }

    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void
    {
        if ($this->source() === 'logs') {
            parent::resolveOccurrence($occurrence, $status, $comment, $project);
        }

        $id = trim((string) ($occurrence['id'] ?? ''));

        if ($id === '') {
            throw new ErrorTrackerException('The issue of Datadog has no id.');
        }

        $response = $this->wrapConnection(fn () => $this->client()
            ->withHeaders($this->headers())
            ->put($this->api().'/api/v2/error-tracking/issues/'.rawurlencode($id).'/state', [
                'data' => ['type' => 'issue', 'id' => $id, 'attributes' => ['state' => 'RESOLVED']],
            ]));

        $this->check($response, 'Datadog');
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array{project: array<string, mixed>, errors: list<array<string, mixed>>, fetched_at: string, truncated: bool, granularity: string}
     */
    protected function downloadFromErrorTracking(array $project): array
    {
        [$from, $to] = $this->window();

        $response = $this->wrapConnection(fn () => $this->client()
            ->withHeaders($this->headers())
            ->post($this->api().'/api/v2/error-tracking/issues/search?include=issue', [
                'data' => [
                    'type' => 'search_request',
                    'attributes' => [
                        'from' => $from,
                        'to' => $to,
                        'query' => 'service:'.$this->service(),
                        'track' => $this->track(),
                        'states' => ['OPEN'],
                    ],
                ],
            ]));

        $body = $this->json($response, 'Datadog Error Tracking');
        $results = is_array($body['data'] ?? null) ? $body['data'] : [];
        $issues = [];

        foreach (is_array($body['included'] ?? null) ? $body['included'] : [] as $included) {
            if (is_array($included) && ($included['type'] ?? '') === 'issue' && isset($included['id'])) {
                $issues[(string) $included['id']] = is_array($included['attributes'] ?? null) ? $included['attributes'] : [];
            }
        }

        $occurrences = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $attributes = is_array($result['attributes'] ?? null) ? $result['attributes'] : [];
            $id = trim((string) ($attributes['issue_id'] ?? $result['relationships']['issue']['data']['id'] ?? $result['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $occurrences[] = $this->fromIssue($id, $issues[$id] ?? [], (int) ($attributes['total_count'] ?? 1));
        }

        return $this->pack($project, $occurrences, count($results) >= self::PAGE);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function fromIssue(string $id, array $attributes, int $times): array
    {
        return $this->occurrence(
            $id,
            '#DD'.substr($id, 0, 8),
            trim((string) ($attributes['error_type'] ?? '')),
            trim((string) ($attributes['error_message'] ?? '')),
            trim((string) ($attributes['file_path'] ?? '')),
            null,
            $attributes['last_seen'] ?? $attributes['first_seen'] ?? null,
            times: $times,
            group: $id,
            url: $this->host().'/error-tracking?issueId='.rawurlencode($id),
        );
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array{project: array<string, mixed>, errors: list<array<string, mixed>>, fetched_at: string, truncated: bool, granularity: string}
     */
    protected function downloadFromLogs(array $project): array
    {
        $response = $this->wrapConnection(fn () => $this->client()
            ->withHeaders($this->headers())
            ->post($this->api().'/api/v2/logs/events/search', [
                'filter' => [
                    'from' => 'now-14d',
                    'to' => 'now',
                    'query' => 'service:'.$this->service().' status:error',
                ],
                'sort' => '-timestamp',
                'page' => ['limit' => self::PAGE],
            ]));

        $body = $this->json($response, 'Datadog');
        $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
        $occurrences = [];

        foreach ($rows as $row) {
            if (is_array($row) && isset($row['id'])) {
                $occurrences[] = $this->fromLog($row);
            }
        }

        return $this->pack($project, $occurrences, count($rows) >= self::PAGE);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function fromLog(array $row): array
    {
        $attributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];
        $custom = is_array($attributes['attributes'] ?? null) ? $attributes['attributes'] : [];
        $error = is_array($custom['error'] ?? null) ? $custom['error'] : [];
        $class = trim((string) ($custom['error.kind'] ?? $error['kind'] ?? ''));
        $message = trim((string) ($custom['error.message'] ?? $error['message'] ?? $attributes['message'] ?? ''));
        [$file, $line] = $this->helper->frame((string) ($custom['error.stack'] ?? $error['stack'] ?? $attributes['message'] ?? ''));

        return $this->occurrence(
            (string) $row['id'],
            null,
            $class !== '' ? $class : 'LogError',
            $message,
            $file,
            $line,
            $attributes['timestamp'] ?? null,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'DD-API-KEY' => trim((string) $this->config('api_key')),
            'DD-APPLICATION-KEY' => trim((string) $this->config('application_key')),
        ];
    }

    protected function api(): string
    {
        return 'https://api.'.$this->site();
    }

    protected function site(): string
    {
        $site = trim((string) $this->config('site', 'datadoghq.com'));

        return $site !== '' ? $site : 'datadoghq.com';
    }

    protected function service(): string
    {
        $service = trim((string) $this->config('service'));

        return $service !== '' ? $service : trim((string) config('app.name', 'laravel'));
    }

    protected function source(): string
    {
        return strtolower(trim((string) $this->config('source', 'error_tracking'))) === 'logs' ? 'logs' : 'error_tracking';
    }

    protected function track(): string
    {
        $track = strtolower(trim((string) $this->config('track', 'trace')));

        return in_array($track, self::TRACKS, true) ? $track : 'trace';
    }
}
