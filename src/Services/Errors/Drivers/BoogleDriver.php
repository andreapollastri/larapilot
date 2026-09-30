<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Larapilot\Services\Boogle\BoogleClient;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\Errors\ErrorDataHelper;
use Larapilot\Services\Errors\ErrorOccurrenceGrouper;
use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * Boogle, the exception tracker and uptime monitor the team hosts: one
 * row for each time an exception was thrown, read over the admin API.
 */
class BoogleDriver extends AbstractErrorDriver
{
    /**
     * What Boogle calls open: nobody fixed it, whether or not it was seen.
     *
     * @var list<string>
     */
    public const OPEN = ['OPEN', 'READ'];

    /** @var list<string> */
    public const CLOSED = ['FIXED', 'DONE'];

    protected const MAX_PAGES = 6;

    public function __construct(ErrorDataHelper $helper, protected BoogleClient $client)
    {
        parent::__construct($helper);
    }

    public function provider(): string
    {
        return 'boogle';
    }

    public function label(): string
    {
        return 'Boogle';
    }

    public function host(): string
    {
        return $this->client->host();
    }

    protected function requiredConfig(): array
    {
        return [];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('larapilot.boogle.'.$key, $default);
    }

    public function missingConfig(): array
    {
        $missing = [];

        if ($this->client->host() === '') {
            $missing[] = 'Set LARAPILOT_BOOGLE_URL in .env: the address of the Boogle of the team, such as https://boogle.example.com.';
        }

        if (! $this->client->hasToken()) {
            $missing[] = 'Set LARAPILOT_BOOGLE_TOKEN in .env. Create it in Boogle, as an admin user, in the profile under API tokens.';
        }

        return $missing;
    }

    public function projectHint(): string
    {
        return 'Name it with LARAPILOT_BOOGLE_PROJECT (its id or its title), or set BOOGLE_PROJECT_KEY as the client package asks.';
    }

    public function readsProjectFromTracker(): bool
    {
        return true;
    }

    public function granularity(): string
    {
        return self::OCCURRENCE;
    }

    public function supportsOutages(): bool
    {
        return true;
    }

    public function supportsRemoteResolve(): bool
    {
        return true;
    }

    public function cacheKey(string $projectRoot): string
    {
        return $this->buildCacheKey($projectRoot, $this->client->host(), $this->wanted());
    }

    public function project(): ?array
    {
        $wanted = $this->wanted();
        $sendsWith = trim((string) $this->config('project_key', ''));
        $address = $this->helper->address((string) config('app.url', ''));

        try {
            $projects = $this->client->pages('/projects', [], 8)['rows'];
        } catch (BoogleException $e) {
            throw new ErrorTrackerException($e->getMessage(), $e->hint(), $e->status());
        }

        $picked = null;

        foreach ([
            static fn (array $project): bool => $wanted !== '' && (string) ($project['id'] ?? '') === $wanted,
            static fn (array $project): bool => $wanted !== '' && strcasecmp(trim((string) ($project['title'] ?? '')), $wanted) === 0,
            static fn (array $project): bool => $wanted === '' && $sendsWith !== '' && hash_equals($sendsWith, (string) ($project['key'] ?? '')),
            fn (array $project): bool => $wanted === '' && $address !== '' && ! in_array($address, ['localhost', '127.0.0.1'], true)
                && $this->helper->address((string) ($project['url'] ?? '')) === $address,
        ] as $matches) {
            foreach ($projects as $project) {
                if (isset($project['id']) && $matches($project)) {
                    $picked = $project;

                    break 2;
                }
            }
        }

        if ($picked === null) {
            return null;
        }

        // The key and the token of the project, which Boogle sends with
        // the list, are not kept: compared above, and dropped here.
        return [
            'id' => (string) $picked['id'],
            'title' => trim((string) ($picked['title'] ?? '')),
            'url' => trim((string) ($picked['url'] ?? '')),
            'group' => is_array($picked['group'] ?? null) ? trim((string) ($picked['group']['title'] ?? '')) : '',
            'uptime' => (bool) ($picked['uptime_enabled'] ?? false),
            'provider' => $this->provider(),
        ];
    }

    public function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new ErrorTrackerException(
                'No project of Boogle matches this application'.($this->wanted() !== '' ? ' ("'.$this->wanted().'")' : '').'.',
                $this->projectHint()
            );
        }

        $occurrences = [];
        $truncated = false;

        try {
            foreach (self::OPEN as $status) {
                $read = $this->client->pages('/projects/'.rawurlencode($project['id']).'/exceptions', ['status' => $status], self::MAX_PAGES);
                $truncated = $truncated || $read['truncated'];

                foreach ($read['rows'] as $row) {
                    if (isset($row['id'])) {
                        $occurrences[(string) $row['id']] = $this->normalize($row);
                    }
                }
            }
        } catch (BoogleException $e) {
            throw new ErrorTrackerException($e->getMessage(), $e->hint(), $e->status());
        }

        return $this->pack($project, array_values($occurrences), $truncated);
    }

    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void
    {
        $status = strtoupper(trim($status));

        if (! in_array($status, self::CLOSED, true)) {
            throw new ErrorTrackerException('An error is closed in Boogle as FIXED or DONE.');
        }

        try {
            $this->client->patch('/projects/'.rawurlencode((string) $project['id']).'/exceptions/'.rawurlencode((string) $occurrence['id']).'/status', [
                'status' => $status,
                'comment' => mb_substr($comment, 0, 2000),
            ]);
        } catch (BoogleException $e) {
            throw new ErrorTrackerException($e->getMessage(), $e->hint(), $e->status());
        }
    }

    protected function wanted(): string
    {
        return trim((string) $this->config('project', ''));
    }

    /**
     * One time an exception was thrown, without what it says about a
     * person.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalize(array $row): array
    {
        $class = trim((string) ($row['exception'] ?? '')) ?: 'Unknown exception';
        $code = trim((string) ($row['issue_code'] ?? ''));
        $prefix = strtoupper(trim((string) ($row['issue_prefix'] ?? '')));
        $http = is_array($row['http'] ?? null) ? $row['http'] : [];
        $line = (int) ($row['line'] ?? 0);

        $outage = $prefix === 'OUT'
            || str_starts_with(strtoupper($code), '#OUT')
            || str_ends_with($class, 'ProjectOfflineException');

        $method = strtoupper(trim((string) ($http['method'] ?? '')));
        $path = $this->helper->path((string) ($http['url'] ?? $http['fullUrl'] ?? $http['full_url'] ?? ''));
        $request = $path !== '' ? trim(($method !== '' && preg_match('/^[A-Z]{3,7}$/', $method) === 1 ? $method : '').' '.$path) : null;

        return $this->occurrence(
            (string) $row['id'],
            $code !== '' ? $code : null,
            $class,
            (string) ($row['error'] ?? ''),
            (string) ($row['file'] ?? ''),
            $line > 0 ? $line : null,
            $row['created_at'] ?? null,
            $request,
            $outage ? ErrorOccurrenceGrouper::OUTAGE : ErrorOccurrenceGrouper::ERROR,
            strtoupper((string) ($row['status'] ?? 'OPEN')),
        );
    }
}
