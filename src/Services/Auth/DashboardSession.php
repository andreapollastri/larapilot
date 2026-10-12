<?php

declare(strict_types=1);

namespace Larapilot\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Support\Totp;

/**
 * Who is at the dashboard in this session, and the steps that let them in:
 * the password, then — for a user with an authenticator, and for every
 * user in production — the code it shows. The state lives in the session
 * of the application; the user is read from `.larapilot/auth.yaml` on
 * every request, so a removed user, a changed password, or a changed role
 * takes effect at once, on every open session.
 */
final class DashboardSession
{
    public const KEY = 'larapilot_auth';

    public const PENDING = 'larapilot_auth_pending';

    public const SETUP = 'larapilot_totp_setup';

    /** Seconds a user has to finish the second step. */
    public const PENDING_TTL = 600;

    public const SIGNED_IN = 'signed_in';

    public const NEEDS_TOTP = 'totp';

    public const NEEDS_SETUP = 'setup';

    public const FAILED = 'failed';

    /** Wrong passwords a minute for one name, from anywhere. */
    public const USER_ATTEMPTS = 5;

    /** Wrong codes a minute for one name. */
    public const TOTP_ATTEMPTS = 5;

    public function __construct(
        protected DashboardAuthService $users,
        protected AccessPolicy $policy,
        protected AuthAuditService $audit,
    ) {}

    public function policy(): AccessPolicy
    {
        return $this->policy;
    }

    /**
     * Who acts: the signed-in user, or — on an open dashboard — an
     * implicit admin with no name. Null when the dashboard asks for a
     * sign-in and nobody has.
     *
     * @return array{name: string|null, role: string, totp: bool, implicit: bool}|null
     */
    public function actor(): ?array
    {
        if ($this->policy->isOpen()) {
            return ['name' => null, 'role' => AccessPolicy::ROLE_ADMIN, 'totp' => false, 'implicit' => true];
        }

        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return ['name' => $user['name'], 'role' => $user['role'], 'totp' => $user['totp'] !== null, 'implicit' => false];
    }

    public function role(): ?string
    {
        return $this->actor()['role'] ?? null;
    }

    public function name(): ?string
    {
        return $this->actor()['name'] ?? null;
    }

    public function isAdmin(): bool
    {
        return $this->role() === AccessPolicy::ROLE_ADMIN;
    }

    /**
     * Whether whoever acts may see an area, in the current mode.
     */
    public function allows(string $area): bool
    {
        $role = $this->role();

        return $role !== null && $this->policy->allows($role, $area);
    }

    /**
     * Passwords and tokens in a dump, the host of the database, the values
     * of `.env`: admins alone.
     */
    public function canSeeSecrets(): bool
    {
        return $this->isAdmin();
    }

    /**
     * The signed-in user with their record, when the session is good: the
     * user still exists, the password has not changed since, and the
     * second factor — where the user has one — was passed.
     *
     * @return array{name: string, password: string, role: string, totp: array{secret: string, confirmed_at: string|null, last_step: int|null}|null, created_at: string|null, last_login_at: string|null}|null
     */
    public function user(): ?array
    {
        $auth = session()->get(self::KEY);

        if (! is_array($auth) || ! is_string($auth['name'] ?? null)) {
            return null;
        }

        $record = $this->users->user($auth['name']);

        if ($record === null || ! hash_equals($this->users->fingerprint($record), (string) ($auth['fingerprint'] ?? ''))) {
            session()->forget(self::KEY);

            return null;
        }

        if ($record['totp'] !== null && ($auth['totp'] ?? false) !== true) {
            // An authenticator this session never answered: back to the door.
            session()->forget(self::KEY);

            return null;
        }

        return ['name' => $auth['name']] + $record;
    }

    /**
     * The user who gave the right password and is on the second step.
     *
     * @return array{name: string, password: string, role: string, totp: array{secret: string, confirmed_at: string|null, last_step: int|null}|null, created_at: string|null, last_login_at: string|null}|null
     */
    public function pendingUser(): ?array
    {
        $pending = session()->get(self::PENDING);

        if (! is_array($pending) || ! is_string($pending['name'] ?? null)) {
            return null;
        }

        if (time() - (int) ($pending['at'] ?? 0) > self::PENDING_TTL) {
            session()->forget(self::PENDING);

            return null;
        }

        $record = $this->users->user($pending['name']);

        if ($record === null || ! hash_equals($this->users->fingerprint($record), (string) ($pending['fingerprint'] ?? ''))) {
            session()->forget(self::PENDING);

            return null;
        }

        return ['name' => $pending['name']] + $record;
    }

    /**
     * Whether a signed-in or pending user still has to enrol an
     * authenticator: production asks one of everybody.
     */
    public function needsTotpSetup(): bool
    {
        if (! $this->policy->requiresTotp()) {
            return false;
        }

        $who = $this->user() ?? $this->pendingUser();

        return $who !== null && $who['totp'] === null;
    }

    /**
     * Seconds before another password is taken from this address, or for
     * this name; 0 when one is taken now.
     */
    public function lockedFor(string $username, Request $request): int
    {
        $max = max(0, (int) config('larapilot.dashboard_route.auth.max_attempts', 20));
        $seconds = 0;

        if ($max > 0 && RateLimiter::tooManyAttempts($this->ipKey($request), $max)) {
            $seconds = RateLimiter::availableIn($this->ipKey($request));
        }

        if (RateLimiter::tooManyAttempts($this->userKey($username), self::USER_ATTEMPTS)) {
            $seconds = max($seconds, RateLimiter::availableIn($this->userKey($username)));
        }

        return $seconds;
    }

    /**
     * The first step. What comes next: the dashboard, the code of the
     * authenticator, its enrolment — or nothing, for a wrong password.
     */
    public function attempt(string $username, string $password, Request $request): string
    {
        $username = trim($username);

        if (! $this->users->validate($username, $password)) {
            RateLimiter::hit($this->ipKey($request), 60);
            RateLimiter::hit($this->userKey($username), 60);
            $this->audit->record('login.failed', ['user' => mb_substr($username, 0, 64)]);

            return self::FAILED;
        }

        RateLimiter::clear($this->ipKey($request));
        RateLimiter::clear($this->userKey($username));

        $record = $this->users->user($username);

        if ($record === null) {
            return self::FAILED;
        }

        // A new session for a new sign-in: whatever was in the old one goes.
        session()->forget([self::KEY, self::PENDING, self::SETUP]);
        session()->regenerate();

        if ($record['totp'] !== null) {
            $this->setPending($username, $record);

            return self::NEEDS_TOTP;
        }

        if ($this->policy->requiresTotp()) {
            $this->setPending($username, $record);

            return self::NEEDS_SETUP;
        }

        $this->establish($username, $record, false);

        return self::SIGNED_IN;
    }

    /**
     * Seconds before another code is taken for this name; 0 when one is.
     */
    public function totpLockedFor(string $username): int
    {
        $key = $this->totpKey($username);

        return RateLimiter::tooManyAttempts($key, self::TOTP_ATTEMPTS) ? RateLimiter::availableIn($key) : 0;
    }

    /**
     * The second step: the code of the authenticator of the pending user.
     */
    public function verifyTotp(string $code): bool
    {
        $pending = $this->pendingUser();

        if ($pending === null) {
            return false;
        }

        $secret = $this->users->totpSecret($pending['name']);
        $step = $secret === null ? null : Totp::verify($secret, $code, $this->users->totpLastStep($pending['name']));

        if ($step === null) {
            RateLimiter::hit($this->totpKey($pending['name']), 60);
            $this->audit->record('totp.failed', ['user' => $pending['name']]);

            return false;
        }

        RateLimiter::clear($this->totpKey($pending['name']));
        $this->users->markTotpStep($pending['name'], $step);
        session()->forget(self::PENDING);

        $record = $this->users->user($pending['name']);

        if ($record === null) {
            return false;
        }

        $this->establish($pending['name'], $record, true);

        return true;
    }

    /**
     * Whom an enrolment is for: the signed-in user, from their account —
     * or the pending one, whom production lets no further without it.
     *
     * @return array{name: string, password: string, role: string, totp: array{secret: string, confirmed_at: string|null, last_step: int|null}|null, created_at: string|null, last_login_at: string|null}|null
     */
    public function enrolling(): ?array
    {
        return $this->user() ?? $this->pendingUser();
    }

    /**
     * The secret of an enrolment under way — the one begun, or a new one.
     */
    public function beginTotpSetup(): string
    {
        $current = $this->setupSecret();

        if ($current !== null) {
            return $current;
        }

        $secret = Totp::generateSecret();
        session()->put(self::SETUP, ['secret' => $secret, 'name' => $this->enrolling()['name'] ?? null, 'at' => time()]);

        return $secret;
    }

    public function setupSecret(): ?string
    {
        $setup = session()->get(self::SETUP);
        $who = $this->enrolling();

        if (! is_array($setup) || ! is_string($setup['secret'] ?? null) || $who === null) {
            return null;
        }

        if (($setup['name'] ?? null) !== $who['name'] || time() - (int) ($setup['at'] ?? 0) > self::PENDING_TTL) {
            session()->forget(self::SETUP);

            return null;
        }

        return $setup['secret'];
    }

    /**
     * The enrolment closes on a code from the new authenticator: the
     * secret is kept, encrypted, and this session counts as having
     * passed it.
     */
    public function confirmTotpSetup(string $code): bool
    {
        $who = $this->enrolling();
        $secret = $this->setupSecret();

        if ($who === null || $secret === null) {
            return false;
        }

        $step = Totp::verify($secret, $code);

        if ($step === null) {
            RateLimiter::hit($this->totpKey($who['name']), 60);

            return false;
        }

        RateLimiter::clear($this->totpKey($who['name']));
        $this->users->enableTotp($who['name'], $secret, $step);
        session()->forget([self::SETUP, self::PENDING]);
        $this->audit->record('totp.enabled', ['user' => $who['name'], 'actor' => $who['name']]);

        $record = $this->users->user($who['name']);

        if ($record === null) {
            return false;
        }

        if ($this->user() !== null) {
            $this->remember($who['name'], $record, true);
        } else {
            $this->establish($who['name'], $record, true);
        }

        return true;
    }

    /**
     * The signed-in user turns their authenticator off: with the password
     * and a current code — never in production, where everyone has one.
     */
    public function disableTotp(string $password, string $code): bool
    {
        $user = $this->user();

        if ($user === null || $user['totp'] === null || $this->policy->requiresTotp()) {
            return false;
        }

        if (! $this->users->validate($user['name'], $password)) {
            return false;
        }

        $secret = $this->users->totpSecret($user['name']);

        if ($secret !== null && Totp::verify($secret, $code, $this->users->totpLastStep($user['name'])) === null) {
            RateLimiter::hit($this->totpKey($user['name']), 60);

            return false;
        }

        $this->users->disableTotp($user['name']);
        $this->audit->record('totp.disabled', ['user' => $user['name'], 'actor' => $user['name']]);

        return true;
    }

    /**
     * The signed-in user changes their password. This session follows the
     * new hash; every other session of theirs is out at its next request.
     *
     * @throws InvalidArgumentException when the current password is wrong or the new one is not acceptable
     */
    public function changePassword(string $current, string $new): void
    {
        $user = $this->user();

        if ($user === null) {
            throw new InvalidArgumentException('Nobody is signed in.');
        }

        if (! $this->users->validate($user['name'], $current)) {
            throw new InvalidArgumentException('The current password is not right.');
        }

        $this->users->setPassword($user['name'], $new);

        $record = $this->users->user($user['name']);

        if ($record !== null) {
            $this->remember($user['name'], $record, $record['totp'] !== null);
        }

        $this->audit->record('password.changed', ['user' => $user['name'], 'actor' => $user['name']]);
    }

    public function signOut(): void
    {
        $name = $this->user()['name'] ?? $this->pendingUser()['name'] ?? null;

        session()->forget([self::KEY, self::PENDING, self::SETUP]);
        session()->invalidate();
        session()->regenerateToken();

        if ($name !== null) {
            $this->audit->record('logout', ['user' => $name, 'actor' => $name]);
        }
    }

    /**
     * @param  array{password: string}  $record
     */
    protected function setPending(string $name, array $record): void
    {
        session()->put(self::PENDING, ['name' => $name, 'fingerprint' => $this->users->fingerprint($record), 'at' => time()]);
    }

    /**
     * A sign-in that passed every step it had to.
     *
     * @param  array{password: string}  $record
     */
    protected function establish(string $name, array $record, bool $totp): void
    {
        $this->remember($name, $record, $totp);
        $this->users->touchLogin($name);
        $this->audit->record('login.ok', ['user' => $name, 'actor' => $name, 'totp' => $totp]);
    }

    /**
     * @param  array{password: string}  $record
     */
    protected function remember(string $name, array $record, bool $totp): void
    {
        session()->put(self::KEY, ['name' => $name, 'fingerprint' => $this->users->fingerprint($record), 'totp' => $totp, 'at' => time()]);
    }

    protected function ipKey(Request $request): string
    {
        return 'larapilot-login:ip:'.sha1((string) $request->ip());
    }

    protected function userKey(string $username): string
    {
        return 'larapilot-login:user:'.sha1(mb_strtolower($username));
    }

    protected function totpKey(string $username): string
    {
        return 'larapilot-totp:'.sha1($username);
    }
}
