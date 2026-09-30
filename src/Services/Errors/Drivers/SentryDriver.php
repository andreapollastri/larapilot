<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * Sentry: the unresolved issues of one project, over the REST API, with
 * an auth token that has the `event:read` scope — `event:write` to close
 * an issue from here.
 */
class SentryDriver extends AbstractErrorDriver
{
    protected const PAGE = 100;

    public function provider(): string
    {
        return 'sentry';
    }

    public function label(): string
    {
        return 'Sentry';
    }

    public function host(): string
    {
        return $this->base();
    }

    protected function requiredConfig(): array
    {
        return [
            'token' => 'Set LARAPILOT_SENTRY_AUTH_TOKEN (or SENTRY_AUTH_TOKEN) in .env: an auth token of Sentry with the event:read scope, event:write to close issues.',
            'organization' => 'Set LARAPILOT_SENTRY_ORGANIZATION (or SENTRY_ORG) in .env: the slug of the organization.',
            'project' => 'Set LARAPILOT_SENTRY_PROJECT (or SENTRY_PROJECT) in .env: the slug of the project.',
        ];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('larapilot.errors.sentry.'.$key, $default);
    }

    public function supportsRemoteResolve(): bool
    {
        return true;
    }

    public function cacheKey(string $projectRoot): string
    {
        return $this->buildCacheKey($projectRoot, $this->base(), $this->organization(), $this->slug());
    }

    public function project(): ?array
    {
        if ($this->organization() === '' || $this->slug() === '') {
            return null;
        }

        return [
            'id' => $this->organization().'/'.$this->slug(),
            'title' => $this->slug(),
            'url' => $this->base().'/organizations/'.rawurlencode($this->organization()).'/issues/?project='.rawurlencode($this->slug()),
            'group' => $this->organization(),
            'uptime' => false,
            'provider' => $this->provider(),
        ];
    }

    public function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new ErrorTrackerException('The organization or the project of Sentry is not set.', 'Set LARAPILOT_SENTRY_ORGANIZATION and LARAPILOT_SENTRY_PROJECT in .env.');
        }

        $response = $this->wrapConnection(fn () => $this->client()
            ->withToken($this->token())
            ->get($this->base().'/api/0/projects/'.rawurlencode($this->organization()).'/'.rawurlencode($this->slug()).'/issues/', [
                'query' => 'is:unresolved',
                'sort' => 'freq',
                'limit' => self::PAGE,
                'statsPeriod' => '',
            ]));

        $rows = $this->json($response, 'Sentry');
        $occurrences = [];

        foreach ($rows as $issue) {
            if (is_array($issue) && isset($issue['id'])) {
                $occurrences[] = $this->fromIssue($issue);
            }
        }

        return $this->pack($project, $occurrences, count($rows) >= self::PAGE);
    }

    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void
    {
        $id = trim((string) ($occurrence['id'] ?? ''));

        if ($id === '') {
            throw new ErrorTrackerException('The issue of Sentry has no id.');
        }

        $response = $this->wrapConnection(fn () => $this->client()
            ->withToken($this->token())
            ->put($this->base().'/api/0/organizations/'.rawurlencode($this->organization()).'/issues/'.rawurlencode($id).'/', [
                'status' => 'resolved',
            ]));

        $this->check($response, 'Sentry');
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    protected function fromIssue(array $issue): array
    {
        $metadata = is_array($issue['metadata'] ?? null) ? $issue['metadata'] : [];
        $shortId = trim((string) ($issue['shortId'] ?? ''));
        $line = null;
        $file = trim((string) ($metadata['filename'] ?? ''));

        if ($file === '') {
            [$file, $line] = $this->helper->frame((string) ($issue['culprit'] ?? ''));
        }

        return $this->occurrence(
            (string) $issue['id'],
            $shortId !== '' ? $shortId : null,
            trim((string) ($metadata['type'] ?? '')),
            trim((string) ($metadata['value'] ?? $issue['title'] ?? '')),
            $file,
            $line,
            $issue['lastSeen'] ?? $issue['firstSeen'] ?? null,
            times: (int) ($issue['count'] ?? 1),
            group: (string) $issue['id'],
            url: isset($issue['permalink']) ? (string) $issue['permalink'] : null,
        );
    }

    protected function base(): string
    {
        $base = rtrim(trim((string) $this->config('url', 'https://sentry.io')), '/');

        return $base !== '' ? $base : 'https://sentry.io';
    }

    protected function token(): string
    {
        return trim((string) $this->config('token'));
    }

    protected function organization(): string
    {
        return trim((string) $this->config('organization'));
    }

    protected function slug(): string
    {
        return trim((string) $this->config('project'));
    }
}
