<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Http\Request;
use Larapilot\Support\AtomicFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Append-only audit trail for **mutating** Larapilot JSON API requests
 * (currently `POST /larapilot/api/specs/{code}/comments`).
 *
 * One JSON object per line in `.larapilot/api-audit.log` — timestamp, method,
 * path, client IP, whether a token was presented, and the response status.
 * No request bodies are stored. The file is added to `.gitignore` on first
 * write. Disable with `larapilot.api.audit` / `LARAPILOT_API_AUDIT=false`.
 */
class ApiAuditService
{
    public function __construct(protected ConfigService $config) {}

    public function enabled(): bool
    {
        return (bool) config('larapilot.api.audit', true);
    }

    public function path(): string
    {
        $configured = config('larapilot.api.audit_file');

        if (is_string($configured) && $configured !== '') {
            return $this->config->absolutePath($configured);
        }

        return $this->config->absolutePath('.larapilot/api-audit.log');
    }

    public function record(Request $request, Response $response): void
    {
        if (! $this->enabled() || $request->isMethodSafe()) {
            return;
        }

        $token = $request->bearerToken() ?? $request->header('X-Larapilot-Token');

        $line = json_encode([
            'at' => now()->toIso8601String(),
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($request->path(), '/'),
            'ip' => $request->ip(),
            'token' => is_string($token) && $token !== '',
            'status' => $response->getStatusCode(),
        ], JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }

        $path = $this->path();
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        AtomicFile::write($path, $existing.$line."\n");

        $this->ensureGitignored($path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(int $limit = 100): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) ?: [],
            static fn (string $line): bool => trim($line) !== '',
        ));

        $lines = array_slice($lines, -max(1, $limit));

        $entries = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    protected function ensureGitignored(string $path): void
    {
        $relative = str_replace('\\', '/', $this->config->relativePath($path));

        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:/', $relative) === 1) {
            return;
        }

        $gitignore = rtrim($this->config->projectRoot(), '/\\').'/.gitignore';
        $existing = is_file($gitignore) ? (string) file_get_contents($gitignore) : '';

        foreach (preg_split('/\r\n|\r|\n/', $existing) ?: [] as $entry) {
            $trimmed = trim($entry);

            if ($trimmed === $relative || $trimmed === '/'.$relative) {
                return;
            }
        }

        $prefix = ($existing !== '' && ! str_ends_with($existing, "\n")) ? "\n" : '';

        @file_put_contents(
            $gitignore,
            $existing.$prefix."\n# Larapilot API audit log — never commit\n/".$relative."\n"
        );
    }
}
