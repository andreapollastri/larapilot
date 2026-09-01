<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Facades\Hash;
use Larapilot\Support\AtomicFile;
use Symfony\Component\Yaml\Yaml;

/**
 * File-backed credential store for the optional HTTP Basic Auth gate on the
 * `/larapilot` dashboard UI.
 *
 * Passwords are only ever stored as bcrypt / argon2id hashes in
 * `.larapilot/auth.yaml`, which this service keeps out of version control.
 * Nothing here touches the database, the host app's `User` model, the JSON
 * API (`larapilot.api.token`), or the MCP server.
 */
class DashboardAuthService
{
    /** Cached throwaway hash, burned only to equalise verification timing. */
    protected static ?string $dummyHash = null;

    public function __construct(protected ConfigService $config) {}

    /**
     * Absolute path to the credential file.
     */
    public function path(): string
    {
        $configured = config('larapilot.dashboard_route.auth.file');

        if (is_string($configured) && $configured !== '') {
            return $this->config->absolutePath($configured);
        }

        return $this->config->absolutePath('.larapilot/auth.yaml');
    }

    /**
     * Configured users as `username => password hash`.
     *
     * @return array<string, string>
     */
    public function users(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        $parsed = Yaml::parseFile($path);
        $raw = is_array($parsed) && is_array($parsed['users'] ?? null) ? $parsed['users'] : [];

        $users = [];

        foreach ($raw as $name => $hash) {
            if (is_string($name) && $name !== '' && is_string($hash) && $hash !== '') {
                $users[$name] = $hash;
            }
        }

        return $users;
    }

    /**
     * @return list<string>
     */
    public function usernames(): array
    {
        return array_keys($this->users());
    }

    public function hasUsers(): bool
    {
        return $this->users() !== [];
    }

    /**
     * Verify a username / password pair. Always runs exactly one hash check so
     * an unknown username costs roughly the same as a wrong password.
     */
    public function validate(?string $username, ?string $password): bool
    {
        $username = (string) $username;
        $password = (string) $password;

        $hash = $this->users()[$username] ?? null;

        if ($hash === null) {
            Hash::check($password, $this->dummyHash());

            return false;
        }

        return $username !== '' && Hash::check($password, $hash);
    }

    /**
     * Create or update a user, storing only the password hash.
     *
     * @throws \InvalidArgumentException when the username or password is empty
     */
    public function setUser(string $username, string $password): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new \InvalidArgumentException('Username must not be empty.');
        }

        if ($password === '') {
            throw new \InvalidArgumentException('Password must not be empty.');
        }

        $users = $this->users();
        $users[$username] = Hash::make($password);
        ksort($users);

        $this->persist($users);
    }

    /**
     * Remove a user. Returns false when the username was not present.
     */
    public function removeUser(string $username): bool
    {
        $users = $this->users();

        if (! array_key_exists($username, $users)) {
            return false;
        }

        unset($users[$username]);

        $this->persist($users);

        return true;
    }

    /**
     * @param  array<string, string>  $users
     */
    protected function persist(array $users): void
    {
        AtomicFile::write(
            $this->path(),
            Yaml::dump(['users' => $users], 4, 2)
        );

        $this->ensureGitignored();
    }

    protected function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make('larapilot-dashboard-auth-dummy');
    }

    /**
     * Best-effort: make sure the credential file is listed in the project
     * `.gitignore` so password hashes are never committed. No-op when the
     * entry is already present or the file lives outside the project root.
     */
    protected function ensureGitignored(): void
    {
        $relative = str_replace('\\', '/', $this->config->relativePath($this->path()));

        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:/', $relative) === 1) {
            return;
        }

        $gitignore = rtrim($this->config->projectRoot(), '/\\').'/.gitignore';
        $existing = is_file($gitignore) ? (string) file_get_contents($gitignore) : '';

        foreach (preg_split('/\r\n|\r|\n/', $existing) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === $relative || $trimmed === '/'.$relative) {
                return;
            }
        }

        $prefix = ($existing !== '' && ! str_ends_with($existing, "\n")) ? "\n" : '';

        @file_put_contents(
            $gitignore,
            $existing.$prefix."\n# Larapilot dashboard Basic Auth credentials (hashed) — never commit\n/".$relative."\n"
        );
    }
}
