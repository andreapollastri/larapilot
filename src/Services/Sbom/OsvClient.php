<?php

declare(strict_types=1);

namespace Larapilot\Services\Sbom;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Larapilot\Services\ConfigService;

/**
 * OSV.dev — the open vulnerability database Google runs over the GitHub
 * advisories, FriendsOfPHP, and the npm registry, among others. It needs no
 * account and no key: `POST /v1/querybatch` takes up to 1000 packages with
 * their version and answers the ids of the advisories that affect each;
 * `GET /v1/vulns/{id}` answers one advisory.
 *
 * What is sent is the name and the version of each package, nothing else.
 * Advisories are cached in `.larapilot/cache/osv/` by id and modification
 * date, so a second check downloads only what changed.
 */
class OsvClient
{
    public const BASE_URL = 'https://api.osv.dev/v1/';

    private const BATCH = 500;

    private const CONCURRENCY = 8;

    public function __construct(protected ConfigService $config) {}

    /**
     * The advisory ids for each query, in the order of the queries.
     *
     * @param  list<array{ecosystem: string, name: string, version: string}>  $queries
     * @return list<list<array{id: string, modified: string|null}>>
     *
     * @throws \RuntimeException
     */
    public function query(array $queries): array
    {
        $results = [];

        foreach (array_chunk($queries, self::BATCH) as $chunk) {
            $body = ['queries' => array_map(static fn (array $query): array => [
                'package' => ['name' => $query['name'], 'ecosystem' => $query['ecosystem']],
                'version' => $query['version'],
            ], $chunk)];

            try {
                $response = $this->http()->post(self::BASE_URL.'querybatch', $body);
            } catch (\Throwable $e) {
                throw new \RuntimeException('OSV.dev could not be reached: '.$e->getMessage(), 0, $e);
            }

            if (! $response->successful()) {
                throw new \RuntimeException('OSV.dev answered '.$response->status().' to the vulnerability query.');
            }

            $answers = $response->json('results');

            foreach ($chunk as $index => $query) {
                $vulns = is_array($answers[$index]['vulns'] ?? null) ? $answers[$index]['vulns'] : [];
                $token = $answers[$index]['next_page_token'] ?? null;

                // A package with more advisories than one page holds (lodash,
                // axios) continues on the next pages; bounded, in case of a loop.
                for ($page = 0; is_string($token) && $token !== '' && $page < 10; $page++) {
                    try {
                        $more = $this->http()->post(self::BASE_URL.'query', [
                            'package' => ['name' => $query['name'], 'ecosystem' => $query['ecosystem']],
                            'version' => $query['version'],
                            'page_token' => $token,
                        ])->json();
                    } catch (\Throwable) {
                        break;
                    }

                    $vulns = array_merge($vulns, is_array($more['vulns'] ?? null) ? $more['vulns'] : []);
                    $token = $more['next_page_token'] ?? null;
                }

                $results[] = array_values(array_filter(array_map(static fn (mixed $vuln): ?array => is_array($vuln) && is_string($vuln['id'] ?? null)
                    ? ['id' => $vuln['id'], 'modified' => is_string($vuln['modified'] ?? null) ? $vuln['modified'] : null]
                    : null, $vulns)));
            }
        }

        return $results;
    }

    /**
     * The advisories, by id. One that cannot be downloaded is left out.
     *
     * @param  array<string, string|null>  $ids  id => modified
     * @return array<string, array<string, mixed>>
     */
    public function vulnerabilities(array $ids): array
    {
        $found = [];
        $missing = [];

        foreach ($ids as $id => $modified) {
            $cached = $this->fromCache($id, $modified);

            if ($cached !== null) {
                $found[$id] = $cached;
            } else {
                $missing[] = $id;
            }
        }

        foreach (array_chunk($missing, self::CONCURRENCY) as $chunk) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($chunk): array {
                    return array_map(
                        fn (string $id) => $pool->as($id)->acceptJson()->timeout(20)->connectTimeout(8)->withUserAgent($this->userAgent())->get(self::BASE_URL.'vulns/'.rawurlencode($id)),
                        $chunk
                    );
                });
            } catch (\Throwable) {
                continue;
            }

            foreach ($chunk as $id) {
                $response = $responses[$id] ?? null;

                if (! $response instanceof Response || ! $response->successful()) {
                    continue;
                }

                $vuln = $response->json();

                if (is_array($vuln)) {
                    $found[$id] = $vuln;
                    $this->toCache($id, $vuln);
                }
            }
        }

        return $found;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout(30)->connectTimeout(8)->withUserAgent($this->userAgent());
    }

    protected function userAgent(): string
    {
        return 'larapilot (+https://github.com/andreapollastri/larapilot)';
    }

    protected function cachePath(string $id): string
    {
        return $this->config->absolutePath('.larapilot/cache/osv/'.preg_replace('/[^A-Za-z0-9._-]/', '_', $id).'.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fromCache(string $id, ?string $modified): ?array
    {
        $path = $this->cachePath($id);

        if (! is_file($path)) {
            return null;
        }

        $vuln = json_decode((string) file_get_contents($path), true);

        if (! is_array($vuln) || ($modified !== null && ($vuln['modified'] ?? null) !== $modified)) {
            return null;
        }

        return $vuln;
    }

    /**
     * @param  array<string, mixed>  $vuln
     */
    protected function toCache(string $id, array $vuln): void
    {
        $path = $this->cachePath($id);
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return;
        }

        if (! is_file(dirname($directory).'/.gitignore')) {
            @file_put_contents(dirname($directory).'/.gitignore', "*\n");
        }

        @file_put_contents($path, json_encode($vuln, JSON_UNESCAPED_SLASHES));
    }
}
