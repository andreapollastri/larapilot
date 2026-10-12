<?php

declare(strict_types=1);

namespace Larapilot\Services\Auth;

use Larapilot\Services\ConfigService;
use Larapilot\Support\AtomicFile;

/**
 * Append-only trail of what happens at the door of the dashboard: a
 * sign-in that passed or failed, a second factor enrolled or reset, a
 * user made, renamed, or removed, a token issued or revoked, an
 * anonymization map saved, a data export. One JSON object per line in
 * `.larapilot/auth-audit.log`, out of Git — who, when, from which
 * address, what. Never a password, a code, or a token.
 */
class AuthAuditService
{
    public function __construct(protected ConfigService $config) {}

    public function enabled(): bool
    {
        return (bool) config('larapilot.dashboard_route.auth.audit', true);
    }

    public function path(): string
    {
        $configured = config('larapilot.dashboard_route.auth.audit_file');

        if (is_string($configured) && $configured !== '') {
            return $this->config->absolutePath($configured);
        }

        return $this->config->absolutePath('.larapilot/auth-audit.log');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $event, array $context = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $entry = ['at' => now()->toIso8601String(), 'event' => $event];
        $ip = null;

        try {
            $ip = app()->bound('request') ? request()->ip() : null;
        } catch (\Throwable) {
            // no request to speak of
        }

        if (is_string($ip) && $ip !== '') {
            $entry['ip'] = $ip;
        } elseif (app()->runningInConsole()) {
            $entry['via'] = 'cli';
        }

        $line = json_encode($entry + $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            return;
        }

        try {
            $path = $this->path();
            $existing = is_file($path) ? (string) file_get_contents($path) : '';

            AtomicFile::write($path, $existing.$line."\n");

            $this->ensureGitignored($path);
        } catch (\Throwable) {
            // A trail that cannot be written never blocks the sign-in.
        }
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

        $entries = [];

        foreach (array_reverse(array_slice($lines, -max(1, $limit))) as $line) {
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
            $existing.$prefix."\n# Larapilot dashboard sign-in audit log — never commit\n/".$relative."\n"
        );
    }
}
