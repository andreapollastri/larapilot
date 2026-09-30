<?php

declare(strict_types=1);

namespace Larapilot\Services\Aikido;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The public REST API of Aikido: an access token from the client
 * credentials, then calls with it — reads, and the few writes a decision
 * of the user asks for.
 *
 * The credentials live in `.env` and nowhere else. The token is kept in the
 * cache for as long as Aikido says it lasts, less a minute, and never
 * written to a file of the project.
 */
class AikidoClient
{
    /**
     * @var array<string, string>
     */
    public const REGIONS = [
        'eu' => 'https://app.aikido.dev',
        'us' => 'https://app.us.aikido.dev',
        'au' => 'https://app.au.aikido.dev',
        'me' => 'https://app.me.aikido.dev',
    ];

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function region(): string
    {
        $region = strtolower(trim((string) config('larapilot.aikido.region', 'eu')));

        return isset(self::REGIONS[$region]) ? $region : 'eu';
    }

    /**
     * Where the workspace is reached: the address of its region, unless one
     * is given for a proxy or a private instance.
     */
    public function host(): string
    {
        $override = rtrim(trim((string) config('larapilot.aikido.base_url', '')), '/');

        return $override !== '' ? $override : self::REGIONS[$this->region()];
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<array-key, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->send('get', $path, $query);
        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, scalar|null>  $body
     */
    public function post(string $path, array $query = [], array $body = []): Response
    {
        return $this->send('post', $path, $query, $body);
    }

    /**
     * @param  array<string, scalar|null>  $body
     */
    public function put(string $path, array $body = []): Response
    {
        return $this->send('put', $path, [], $body);
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenKey());
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, scalar|null>  $body
     */
    protected function send(string $method, string $path, array $query, array $body = [], bool $retry = true): Response
    {
        $filled = static fn (mixed $value): bool => $value !== null && $value !== '';
        $query = array_filter($query, $filled);
        $body = array_filter($body, $filled);
        $url = $this->host().'/api/public/v1'.$path.($query === [] ? '' : '?'.http_build_query($query));

        try {
            $request = $this->http()->withToken($this->token());
            $response = match ($method) {
                'post' => $request->post($url, $body),
                'put' => $request->put($url, $body),
                default => $request->get($url),
            };
        } catch (ConnectionException $e) {
            throw new AikidoException(
                'Aikido could not be reached at '.$this->host().'.',
                'Check the network and LARAPILOT_AIKIDO_REGION ('.implode(', ', array_keys(self::REGIONS)).').'
            );
        }

        // A token refused before its time was revoked: ask once for a new one.
        if ($response->status() === 401 && $retry) {
            $this->forgetToken();

            return $this->send($method, $path, $query, $body, false);
        }

        if ($response->successful()) {
            return $response;
        }

        throw $this->failure($response);
    }

    protected function token(): string
    {
        if (! $this->configured()) {
            throw new AikidoException(
                'The Aikido credentials are not set.',
                'Set LARAPILOT_AIKIDO_CLIENT_ID and LARAPILOT_AIKIDO_CLIENT_SECRET in .env. Create them in Aikido under Settings → Integrations → Public REST API.'
            );
        }

        $cached = Cache::get($this->tokenKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->http()
                ->withBasicAuth($this->clientId(), $this->clientSecret())
                ->asJson()
                ->post($this->host().'/api/oauth/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $e) {
            throw new AikidoException(
                'Aikido could not be reached at '.$this->host().'.',
                'Check the network and LARAPILOT_AIKIDO_REGION ('.implode(', ', array_keys(self::REGIONS)).').'
            );
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new AikidoException(
                'Aikido refused the credentials'.($response->json('error') ? ' ('.$response->json('error').')' : '').'.',
                'Check LARAPILOT_AIKIDO_CLIENT_ID, LARAPILOT_AIKIDO_CLIENT_SECRET, and that LARAPILOT_AIKIDO_REGION is the region of the workspace.',
                $response->status()
            );
        }

        $lifetime = max(60, (int) ($response->json('expires_in') ?? 3600) - 60);
        Cache::put($this->tokenKey(), $token, $lifetime);

        return $token;
    }

    protected function failure(Response $response): AikidoException
    {
        $detail = $response->json('reason_phrase') ?? $response->json('error') ?? $response->json('message');
        $detail = is_string($detail) && trim($detail, " .\n") !== '' ? ' — '.trim($detail, " .\n") : '';

        return match ($response->status()) {
            401 => new AikidoException('Aikido refused the access token'.$detail.'.', 'Create new credentials in Aikido and update .env.', 401),
            403 => new AikidoException('These Aikido credentials may not do this'.$detail.'.', 'Give the credentials the issues:read and repositories:read scopes, issues:write to send a decision, and repositories:write to ask for a scan.', 403),
            404 => new AikidoException('Aikido does not know this'.$detail.'.', 'Check LARAPILOT_AIKIDO_REPOSITORY, or that the repository is connected in Aikido.', 404),
            429 => new AikidoException('Aikido asked to slow down'.$detail.'.', 'Wait a minute and run the command again.', 429),
            default => new AikidoException('Aikido answered '.$response->status().$detail.'.', null, $response->status()),
        };
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(max(1, (int) config('larapilot.aikido.timeout', 15)))->acceptJson();
    }

    protected function clientId(): string
    {
        return trim((string) config('larapilot.aikido.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('larapilot.aikido.client_secret', ''));
    }

    protected function tokenKey(): string
    {
        return 'larapilot.aikido.token.'.sha1($this->host().'|'.$this->clientId());
    }
}
