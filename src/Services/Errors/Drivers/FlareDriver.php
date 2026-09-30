<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * Flare, by Spatie: the open errors of one project, over the REST API,
 * with a personal access token that has the `read` scope — `write` to
 * close an error from here.
 */
class FlareDriver extends AbstractErrorDriver
{
    protected const PAGE = 100;

    protected const MAX_PAGES = 3;

    /** @var list<string> */
    protected const CLOSED = ['resolved', 'snoozed', 'ignored'];

    public function provider(): string
    {
        return 'flare';
    }

    public function label(): string
    {
        return 'Flare';
    }

    public function host(): string
    {
        return 'https://flareapp.io';
    }

    protected function requiredConfig(): array
    {
        return [
            'token' => 'Set LARAPILOT_FLARE_TOKEN in .env: a personal access token of Flare with the read scope (write to close errors), not the FLARE_KEY the application reports with.',
            'project_id' => 'Set LARAPILOT_FLARE_PROJECT_ID in .env: the id of the project in Flare.',
        ];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('larapilot.errors.flare.'.$key, $default);
    }

    public function supportsRemoteResolve(): bool
    {
        return true;
    }

    public function cacheKey(string $projectRoot): string
    {
        return $this->buildCacheKey($projectRoot, $this->base(), $this->projectId());
    }

    public function project(): ?array
    {
        if ($this->projectId() === '') {
            return null;
        }

        $name = trim((string) $this->config('project_name', ''));

        return [
            'id' => $this->projectId(),
            'title' => $name !== '' ? $name : 'Flare project '.$this->projectId(),
            'url' => 'https://flareapp.io/projects/'.rawurlencode($this->projectId()),
            'group' => '',
            'uptime' => false,
            'provider' => $this->provider(),
        ];
    }

    public function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new ErrorTrackerException('The project of Flare is not set.', 'Set LARAPILOT_FLARE_PROJECT_ID in .env.');
        }

        $occurrences = [];
        $truncated = false;

        // The list holds every error of the project; the ones closed in
        // Flare are left out here.
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = $this->wrapConnection(fn () => $this->client()
                ->withToken($this->token())
                ->get($this->base().'/projects/'.rawurlencode($this->projectId()).'/errors', [
                    'page[number]' => $page,
                    'page[size]' => self::PAGE,
                ]));

            $body = $this->json($response, 'Flare');
            $rows = is_array($body['data'] ?? null) ? $body['data'] : $body;

            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['id'])) {
                    continue;
                }

                if (in_array(strtolower(trim((string) ($row['status'] ?? 'open'))), self::CLOSED, true)) {
                    continue;
                }

                $occurrences[] = $this->fromRow($row);
            }

            if (count($rows) < self::PAGE) {
                $truncated = false;

                break;
            }

            $truncated = true;
        }

        return $this->pack($project, $occurrences, $truncated);
    }

    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void
    {
        $id = trim((string) ($occurrence['id'] ?? ''));

        if ($id === '') {
            throw new ErrorTrackerException('The error of Flare has no id.');
        }

        $response = $this->wrapConnection(fn () => $this->client()
            ->withToken($this->token())
            ->post($this->base().'/errors/'.rawurlencode($id).'/resolve'));

        $this->check($response, 'Flare');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function fromRow(array $row): array
    {
        return $this->occurrence(
            (string) $row['id'],
            '#FL'.$row['id'],
            trim((string) ($row['exception_class'] ?? '')),
            trim((string) ($row['exception_message'] ?? $row['message'] ?? '')),
            trim((string) ($row['file'] ?? '')),
            isset($row['line']) && is_numeric($row['line']) ? (int) $row['line'] : null,
            $row['last_seen_at'] ?? $row['first_seen_at'] ?? null,
            times: (int) ($row['occurrence_count'] ?? 1),
            group: (string) $row['id'],
            url: isset($row['url']) ? (string) $row['url'] : null,
        );
    }

    protected function base(): string
    {
        $base = rtrim(trim((string) $this->config('url', 'https://flareapp.io/api')), '/');

        return $base !== '' ? $base : 'https://flareapp.io/api';
    }

    protected function token(): string
    {
        return trim((string) $this->config('token'));
    }

    protected function projectId(): string
    {
        return trim((string) $this->config('project_id'));
    }
}
