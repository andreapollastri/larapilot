<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Larapilot\Services\Tracker\RemoteComment;
use Larapilot\Services\Tracker\RemoteRef;
use Larapilot\Services\Tracker\TrackerDriver;
use Larapilot\Services\Tracker\TrackerException;

/**
 * Shared plumbing for the tracker drivers: config access, an HTTP client
 * with the provider's auth already attached, and uniform error translation.
 *
 * Writes are never retried — a retried create is a duplicated card.
 */
abstract class Driver implements TrackerDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config,
        protected int $timeout = 15,
    ) {}

    /**
     * Config keys this driver requires, mapped to the env var that supplies
     * them, so a missing credential names the variable the user must set.
     *
     * @return array<string, string>
     */
    abstract protected function requiredConfig(): array;

    /**
     * The authenticated client every request for this provider goes through.
     */
    abstract protected function client(): PendingRequest;

    public function missingConfig(): array
    {
        $missing = [];

        foreach ($this->requiredConfig() as $key => $envVar) {
            if ($this->setting($key) === null) {
                $missing[] = $envVar;
            }
        }

        return $missing;
    }

    /**
     * Providers without a comment surface Larapilot can read stay silent
     * rather than failing the pull.
     *
     * @return list<RemoteComment>
     */
    public function readComments(RemoteRef $ref): array
    {
        return [];
    }

    protected function setting(string $key): ?string
    {
        $value = $this->config[$key] ?? null;

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * A required setting, or a hard failure naming the env var — reached only
     * when a caller skipped the missingConfig() gate.
     */
    protected function required(string $key): string
    {
        $value = $this->setting($key);

        if ($value === null) {
            throw new TrackerException(
                $this->label().' is missing '.($this->requiredConfig()[$key] ?? $key).'.'
            );
        }

        return $value;
    }

    /**
     * The provider's Larapilot-status → remote-label map, as configured.
     *
     * @return array<string, string>
     */
    protected function statusMap(): array
    {
        $map = $this->config['status_map'] ?? [];
        $normalized = [];

        if (is_array($map)) {
            foreach ($map as $local => $remote) {
                if (is_string($local) && is_string($remote)) {
                    $normalized[strtoupper(trim($local))] = trim($remote);
                }
            }
        }

        return $normalized;
    }

    /**
     * Unauthenticated client with the shared timeout applied; each driver
     * layers its own auth on top.
     */
    protected function base(): PendingRequest
    {
        return Http::timeout($this->timeout)->acceptJson();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function get(string $url, array $query, string $action): array
    {
        return $this->handle($this->attempt(fn (): Response => $this->client()->get($url, $query), $action), $action);
    }

    /**
     * A GET whose 404 means "gone", not "broken".
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    protected function getOrMissing(string $url, array $query, string $action): ?array
    {
        $response = $this->attempt(fn (): Response => $this->client()->get($url, $query), $action);

        if ($this->isMissing($response)) {
            return null;
        }

        return $this->handle($response, $action);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(string $url, array $payload, string $action): array
    {
        return $this->handle($this->attempt(fn (): Response => $this->client()->post($url, $payload), $action), $action);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function put(string $url, array $payload, string $action): array
    {
        return $this->handle($this->attempt(fn (): Response => $this->client()->put($url, $payload), $action), $action);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function delete(string $url, array $payload, string $action): array
    {
        $response = $this->attempt(fn (): Response => $this->client()->delete($url, $payload), $action);

        // Deleting something already gone is the outcome we wanted anyway.
        if ($this->isMissing($response)) {
            return [];
        }

        return $this->handle($response, $action);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    protected function graphql(string $endpoint, string $query, array $variables, string $action): array
    {
        $response = $this->attempt(
            fn (): Response => $this->client()->post($endpoint, [
                'query' => $query,
                'variables' => (object) $variables,
            ]),
            $action
        );

        $decoded = $this->handle($response, $action);
        $errors = $decoded['errors'] ?? null;

        // GraphQL reports business failures with HTTP 200 plus an errors array.
        if (is_array($errors) && $errors !== []) {
            $messages = [];

            foreach ($errors as $error) {
                $messages[] = is_array($error)
                    ? (string) ($error['message'] ?? 'unknown error')
                    : (string) $error;
            }

            throw TrackerException::api($this->key(), $action, 200, implode('; ', $messages));
        }

        $data = $decoded['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function handle(Response $response, string $action): array
    {
        if ($response->failed()) {
            throw TrackerException::api($this->key(), $action, $response->status(), $response->body());
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    protected function isMissing(Response $response): bool
    {
        return $response->status() === 404 || $response->status() === 410;
    }

    /**
     * @param  callable(): Response  $call
     */
    protected function attempt(callable $call, string $action): Response
    {
        try {
            return $call();
        } catch (ConnectionException $exception) {
            throw TrackerException::unreachable($this->key(), $action, $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return trim($value) === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function rows(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * Match a remote label (workflow state, list, section, status) without
     * punishing the user for case or padding in their status map.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    protected function matchByName(array $candidates, ?string $wanted, string $field = 'name'): ?array
    {
        if ($wanted === null) {
            return null;
        }

        $needle = mb_strtolower(trim($wanted));

        foreach ($candidates as $candidate) {
            $name = $this->str($candidate, $field);

            if ($name !== null && mb_strtolower(trim($name)) === $needle) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, detail: string, target: string|null}
     */
    protected function pong(bool $ok, string $detail, ?string $target = null): array
    {
        return ['ok' => $ok, 'detail' => $detail, 'target' => $target];
    }

    /**
     * Run a ping body, converting any provider failure into a failed ping —
     * `tracker-status` reports connectivity, it does not blow up on it.
     *
     * @param  callable(): array{ok: bool, detail: string, target: string|null}  $probe
     * @return array{ok: bool, detail: string, target: string|null}
     */
    protected function probe(callable $probe): array
    {
        $missing = $this->missingConfig();

        if ($missing !== []) {
            return $this->pong(false, 'Missing configuration: '.implode(', ', $missing).'.');
        }

        try {
            return $probe();
        } catch (TrackerException $exception) {
            return $this->pong(false, $exception->getMessage());
        }
    }
}
