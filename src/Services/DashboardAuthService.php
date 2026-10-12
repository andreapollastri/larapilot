<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Larapilot\Services\Auth\AccessPolicy;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\FileLock;
use Symfony\Component\Yaml\Yaml;

/**
 * The users of the `/larapilot` dashboard and the token of its JSON API,
 * in `.larapilot/auth.yaml` — hashed, out of Git, with no table and no
 * `User` model of the application involved.
 *
 *   users:
 *     andrea:
 *       password: "$2y$…"        # bcrypt / argon2id hash, never the password
 *       role: admin               # admin · dev · pm · ext
 *       totp:                     # absent until the user enrols a second factor
 *         secret: "eyJpdiI6…"     # encrypted with the application key
 *         confirmed_at: "2026-…"
 *         last_step: 59672011     # the last code that let the user in: a code is good once
 *       created_at: "2026-…"
 *       last_login_at: "2026-…"
 *   api_token:
 *     hash: "…"                   # sha256 of the token, which is shown once when made
 *     prefix: lp_a1b2c3d4
 *     created_at: "2026-…"
 *     created_by: andrea
 *
 * A file written before 5.1 holds `users: {name: hash}`. It is read as it
 * is — every such user is an admin — and the next write, or
 * `larapilot:update`, brings it to this layout.
 */
class DashboardAuthService
{
    public const PASSWORD_MIN = 12;

    public const PASSWORD_MAX = 1024;

    public const USERNAME_MAX = 64;

    public const TOKEN_PREFIX = 'lp_';

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

    // ---- the users ----

    /**
     * Every user, by name.
     *
     * @return array<string, array{password: string, role: string, totp: array{secret: string, confirmed_at: string|null, last_step: int|null}|null, created_at: string|null, last_login_at: string|null}>
     */
    public function users(): array
    {
        return $this->read()['users'];
    }

    /**
     * @return array{password: string, role: string, totp: array{secret: string, confirmed_at: string|null, last_step: int|null}|null, created_at: string|null, last_login_at: string|null}|null
     */
    public function user(string $username): ?array
    {
        return $this->users()[$username] ?? null;
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
     * @return list<string>
     */
    public function admins(): array
    {
        return array_keys(array_filter($this->users(), static fn (array $user): bool => $user['role'] === AccessPolicy::ROLE_ADMIN));
    }

    public function role(string $username): ?string
    {
        return $this->user($username)['role'] ?? null;
    }

    /**
     * Verify a username / password pair. Always runs exactly one hash check
     * so an unknown username costs roughly the same as a wrong password.
     */
    public function validate(?string $username, ?string $password): bool
    {
        $username = (string) $username;
        $password = (string) $password;

        $hash = $this->users()[$username]['password'] ?? null;

        if ($hash === null) {
            Hash::check($password, $this->dummyHash());

            return false;
        }

        return $username !== '' && $password !== '' && Hash::check($password, $hash);
    }

    /**
     * What a session keeps of a user to notice a password change: not the
     * hash itself, a digest of it.
     *
     * @param  array{password: string}  $user
     */
    public function fingerprint(array $user): string
    {
        return hash('sha256', $user['password']);
    }

    /**
     * Whether the file still has the layout of before 5.1 — a hash in
     * place of a user, or a user with no role.
     */
    public function needsMigration(): bool
    {
        return $this->read()['legacy'];
    }

    /**
     * Rewrite a file of before 5.1 in the current layout: every user that
     * had no role is an admin. The names given a role.
     *
     * @return list<string>
     */
    public function migrate(): array
    {
        return FileLock::withLock($this->path(), function (): array {
            $data = $this->read();

            if (! $data['legacy']) {
                return [];
            }

            $this->persist($data);

            return $data['migrated'];
        });
    }

    /**
     * Create a user, or set the password of one that exists — and its role
     * when one is given; a new user with no role is an admin.
     *
     * @throws InvalidArgumentException when the name, the password, or the role is not acceptable
     */
    public function setUser(string $username, string $password, ?string $role = null): void
    {
        $username = self::assertUsername($username);
        self::assertPassword($password, $username);
        $role = $role === null ? null : self::assertRole($role);

        $this->update(function (array &$data) use ($username, $password, $role): void {
            $existing = $data['users'][$username] ?? null;

            $data['users'][$username] = [
                'password' => Hash::make($password),
                'role' => $role ?? $existing['role'] ?? AccessPolicy::ROLE_ADMIN,
                'totp' => $existing['totp'] ?? null,
                'created_at' => $existing['created_at'] ?? Carbon::now()->toIso8601String(),
                'last_login_at' => $existing['last_login_at'] ?? null,
            ];
        });
    }

    /**
     * @throws InvalidArgumentException when the name is taken or not acceptable
     */
    public function createUser(string $username, string $password, string $role = AccessPolicy::ROLE_ADMIN): void
    {
        $username = self::assertUsername($username);

        if ($this->user($username) !== null) {
            throw new InvalidArgumentException("There is already a user named {$username}.");
        }

        $this->setUser($username, $password, $role);
    }

    /**
     * @throws InvalidArgumentException when the user does not exist or the password is not acceptable
     */
    public function setPassword(string $username, string $password): void
    {
        $this->requireUser($username);
        self::assertPassword($password, $username);

        $this->update(function (array &$data) use ($username, $password): void {
            $data['users'][$username]['password'] = Hash::make($password);
        });
    }

    /**
     * @throws InvalidArgumentException when the user does not exist or the role is unknown
     */
    public function setRole(string $username, string $role): void
    {
        $this->requireUser($username);
        $role = self::assertRole($role);

        $this->update(function (array &$data) use ($username, $role): void {
            $data['users'][$username]['role'] = $role;
        });
    }

    /**
     * @throws InvalidArgumentException when the user does not exist, or the new name is taken or not acceptable
     */
    public function rename(string $username, string $newName): void
    {
        $this->requireUser($username);
        $newName = self::assertUsername($newName);

        if ($newName === $username) {
            return;
        }

        if ($this->user($newName) !== null) {
            throw new InvalidArgumentException("There is already a user named {$newName}.");
        }

        $this->update(function (array &$data) use ($username, $newName): void {
            $data['users'][$newName] = $data['users'][$username];
            unset($data['users'][$username]);
        });
    }

    /**
     * Remove a user. False when the username was not present.
     */
    public function removeUser(string $username): bool
    {
        if ($this->user($username) === null) {
            return false;
        }

        $this->update(function (array &$data) use ($username): void {
            unset($data['users'][$username]);
        });

        return true;
    }

    public function touchLogin(string $username): void
    {
        if ($this->user($username) === null) {
            return;
        }

        $this->update(function (array &$data) use ($username): void {
            $data['users'][$username]['last_login_at'] = Carbon::now()->toIso8601String();
        });
    }

    // ---- the second factor ----

    public function hasTotp(string $username): bool
    {
        return ($this->user($username)['totp'] ?? null) !== null;
    }

    /**
     * The base32 secret of a user's authenticator, or null when there is
     * none — or when it was encrypted with another application key, in
     * which case the user has to enrol again.
     */
    public function totpSecret(string $username): ?string
    {
        $encrypted = $this->user($username)['totp']['secret'] ?? null;

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    public function totpLastStep(string $username): ?int
    {
        $step = $this->user($username)['totp']['last_step'] ?? null;

        return is_int($step) ? $step : null;
    }

    /**
     * @throws InvalidArgumentException when the user does not exist
     */
    public function enableTotp(string $username, string $secret, ?int $step = null): void
    {
        $this->requireUser($username);

        $this->update(function (array &$data) use ($username, $secret, $step): void {
            $data['users'][$username]['totp'] = [
                'secret' => Crypt::encryptString($secret),
                'confirmed_at' => Carbon::now()->toIso8601String(),
                'last_step' => $step,
            ];
        });
    }

    public function disableTotp(string $username): bool
    {
        if (! $this->hasTotp($username)) {
            return false;
        }

        $this->update(function (array &$data) use ($username): void {
            $data['users'][$username]['totp'] = null;
        });

        return true;
    }

    public function markTotpStep(string $username, int $step): void
    {
        if (! $this->hasTotp($username)) {
            return;
        }

        $this->update(function (array &$data) use ($username, $step): void {
            $data['users'][$username]['totp']['last_step'] = $step;
        });
    }

    // ---- the API token ----

    /**
     * What is known of the token, never the token: its first characters,
     * when and by whom it was made.
     *
     * @return array{prefix: string, created_at: string|null, created_by: string|null}|null
     */
    public function apiToken(): ?array
    {
        $token = $this->read()['api_token'];

        return $token === null ? null : ['prefix' => $token['prefix'], 'created_at' => $token['created_at'], 'created_by' => $token['created_by']];
    }

    /**
     * Make a token — in place of the one there was — and return it: the
     * only time it is seen in clear.
     */
    public function issueApiToken(?string $by = null): string
    {
        $token = self::TOKEN_PREFIX.Str::random(40);

        $this->update(function (array &$data) use ($token, $by): void {
            $data['api_token'] = [
                'hash' => hash('sha256', $token),
                'prefix' => substr($token, 0, strlen(self::TOKEN_PREFIX) + 8),
                'created_at' => Carbon::now()->toIso8601String(),
                'created_by' => $by,
            ];
        });

        return $token;
    }

    public function revokeApiToken(): bool
    {
        if ($this->read()['api_token'] === null) {
            return false;
        }

        $this->update(function (array &$data): void {
            $data['api_token'] = null;
        });

        return true;
    }

    public function apiTokenMatches(?string $provided): bool
    {
        $stored = $this->read()['api_token']['hash'] ?? null;

        if (! is_string($provided) || $provided === '' || ! is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, hash('sha256', $provided));
    }

    // ---- what is acceptable ----

    /**
     * @throws InvalidArgumentException
     */
    public static function assertUsername(string $username): string
    {
        $username = trim($username);

        if ($username === '') {
            throw new InvalidArgumentException('A username is required.');
        }

        if (mb_strlen($username) > self::USERNAME_MAX) {
            throw new InvalidArgumentException('A username has at most '.self::USERNAME_MAX.' characters.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@+-]*$/', $username) !== 1) {
            throw new InvalidArgumentException('A username is letters, digits, and . _ @ + -, and starts with a letter or a digit.');
        }

        return $username;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertPassword(string $password, string $username = ''): void
    {
        if ($password === '') {
            throw new InvalidArgumentException('A password is required.');
        }

        if (mb_strlen($password) < self::PASSWORD_MIN) {
            throw new InvalidArgumentException('A password has at least '.self::PASSWORD_MIN.' characters.');
        }

        if (strlen($password) > self::PASSWORD_MAX) {
            throw new InvalidArgumentException('A password has at most '.self::PASSWORD_MAX.' bytes.');
        }

        if ($username !== '' && mb_strtolower($password) === mb_strtolower(trim($username))) {
            throw new InvalidArgumentException('The password cannot be the username.');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertRole(string $role): string
    {
        $role = strtolower(trim($role));

        if (! AccessPolicy::isRole($role)) {
            throw new InvalidArgumentException('Unknown role '.($role === '' ? '(none)' : $role).'. Use one of: '.implode(', ', AccessPolicy::ROLES).'.');
        }

        return $role;
    }

    // ---- the file ----

    /**
     * @throws InvalidArgumentException when there is no such user
     */
    protected function requireUser(string $username): void
    {
        if ($this->user($username) === null) {
            throw new InvalidArgumentException("No dashboard user named {$username}.");
        }
    }

    /**
     * Read, change, write — under the lock of the file.
     *
     * @param  callable(array<string, mixed>&): void  $mutate
     */
    protected function update(callable $mutate): void
    {
        FileLock::withLock($this->path(), function () use ($mutate): void {
            $data = $this->read();
            $mutate($data);
            $this->persist($data);
        });
    }

    /**
     * The file as it is, every user in the current layout whatever layout
     * the file has, and whether it needs rewriting.
     *
     * @return array{users: array<string, array{password: string, role: string, totp: array{secret: string, confirmed_at: string|null, last_step: int|null}|null, created_at: string|null, last_login_at: string|null}>, api_token: array{hash: string, prefix: string, created_at: string|null, created_by: string|null}|null, legacy: bool, migrated: list<string>}
     */
    protected function read(): array
    {
        $path = $this->path();
        $empty = ['users' => [], 'api_token' => null, 'legacy' => false, 'migrated' => []];

        if (! is_file($path)) {
            return $empty;
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (\Throwable) {
            return $empty;
        }

        $raw = is_array($parsed) && is_array($parsed['users'] ?? null) ? $parsed['users'] : [];
        $users = [];
        $legacy = false;
        $migrated = [];

        foreach ($raw as $name => $entry) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $user = $this->normalizeUser($entry);

            if ($user === null) {
                continue;
            }

            if ($user['legacy']) {
                $legacy = true;
                $migrated[] = $name;
            }

            unset($user['legacy']);
            $users[$name] = $user;
        }

        ksort($users);

        $token = is_array($parsed) && is_array($parsed['api_token'] ?? null) ? $parsed['api_token'] : null;

        if ($token !== null && (! is_string($token['hash'] ?? null) || $token['hash'] === '')) {
            $token = null;
        }

        return [
            'users' => $users,
            'api_token' => $token === null ? null : [
                'hash' => (string) $token['hash'],
                'prefix' => is_string($token['prefix'] ?? null) ? $token['prefix'] : self::TOKEN_PREFIX.'…',
                'created_at' => is_string($token['created_at'] ?? null) ? $token['created_at'] : null,
                'created_by' => is_string($token['created_by'] ?? null) ? $token['created_by'] : null,
            ],
            'legacy' => $legacy,
            'migrated' => $migrated,
        ];
    }

    /**
     * One user as the file holds it — a hash alone before 5.1, a map
     * since — in the current layout. Null for an entry with no password.
     *
     * @return array{password: string, role: string, totp: array{secret: string, confirmed_at: string|null, last_step: int|null}|null, created_at: string|null, last_login_at: string|null, legacy: bool}|null
     */
    protected function normalizeUser(mixed $entry): ?array
    {
        if (is_string($entry)) {
            return $entry === '' ? null : [
                'password' => $entry,
                'role' => AccessPolicy::ROLE_ADMIN,
                'totp' => null,
                'created_at' => null,
                'last_login_at' => null,
                'legacy' => true,
            ];
        }

        if (! is_array($entry) || ! is_string($entry['password'] ?? null) || $entry['password'] === '') {
            return null;
        }

        $role = is_string($entry['role'] ?? null) ? strtolower(trim($entry['role'])) : '';
        $legacy = ! AccessPolicy::isRole($role);

        $totp = null;

        if (is_array($entry['totp'] ?? null) && is_string($entry['totp']['secret'] ?? null) && $entry['totp']['secret'] !== '') {
            $totp = [
                'secret' => $entry['totp']['secret'],
                'confirmed_at' => is_string($entry['totp']['confirmed_at'] ?? null) ? $entry['totp']['confirmed_at'] : null,
                'last_step' => is_int($entry['totp']['last_step'] ?? null) ? $entry['totp']['last_step'] : null,
            ];
        }

        return [
            'password' => $entry['password'],
            'role' => $legacy ? AccessPolicy::ROLE_ADMIN : $role,
            'totp' => $totp,
            'created_at' => is_string($entry['created_at'] ?? null) ? $entry['created_at'] : null,
            'last_login_at' => is_string($entry['last_login_at'] ?? null) ? $entry['last_login_at'] : null,
            'legacy' => $legacy,
        ];
    }

    /**
     * @param  array{users: array<string, array<string, mixed>>, api_token: array<string, mixed>|null}  $data
     */
    protected function persist(array $data): void
    {
        $users = [];
        ksort($data['users']);

        foreach ($data['users'] as $name => $user) {
            $entry = ['password' => $user['password'], 'role' => $user['role']];

            if (is_array($user['totp'] ?? null)) {
                $entry['totp'] = array_filter([
                    'secret' => $user['totp']['secret'],
                    'confirmed_at' => $user['totp']['confirmed_at'] ?? null,
                    'last_step' => $user['totp']['last_step'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            }

            foreach (['created_at', 'last_login_at'] as $key) {
                if (is_string($user[$key] ?? null)) {
                    $entry[$key] = $user[$key];
                }
            }

            $users[$name] = $entry;
        }

        $document = ['users' => $users];

        if (is_array($data['api_token'] ?? null)) {
            $document['api_token'] = array_filter($data['api_token'], static fn (mixed $value): bool => $value !== null);
        }

        AtomicFile::write($this->path(), Yaml::dump($document, 4, 2));

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
            $existing.$prefix."\n# Larapilot dashboard users and API token (hashed) — never commit\n/".$relative."\n"
        );
    }
}
