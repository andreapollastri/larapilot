<?php

declare(strict_types=1);

namespace Larapilot\Services\Boogle;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The admin API of a Boogle the team hosts: read calls with the token of
 * an admin user, and one write, the status of an exception.
 *
 * The token lives in `.env` and nowhere else. It reads every project of
 * that Boogle, so nothing Boogle answers about another project is kept.
 */
class BoogleClient
{
    public function configured(): bool
    {
        return $this->host() !== '' && $this->token() !== '';
    }

    /**
     * Where Boogle is reached: the address given for it, or the one the
     * client package sends the exceptions to, without its path.
     */
    public function host(): string
    {
        foreach (['larapilot.boogle.url', 'larapilot.boogle.server'] as $key) {
            $url = trim((string) config($key, ''));

            if ($url === '') {
                continue;
            }

            $parts = parse_url($url);

            if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || ($parts['host'] ?? '') === '') {
                continue;
            }

            $path = rtrim((string) ($parts['path'] ?? ''), '/');
            // Boogle can live under a folder; what the client package adds
            // to reach the ingestion is not part of the address.
            $path = (string) preg_replace('#/api(/log|/admin.*)?$#', '', $path);

            return strtolower((string) $parts['scheme']).'://'.$parts['host']
                .(isset($parts['port']) ? ':'.$parts['port'] : '')
                .$path;
        }

        return '';
    }

    public function hasToken(): bool
    {
        return $this->token() !== '';
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<array-key, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $body = $this->send('get', $path, $query)->json();

        return is_array($body) ? $body : [];
    }

    /**
     * Every row of a list Boogle gives page by page, up to a number of
     * pages.
     *
     * @param  array<string, scalar|null>  $query
     * @return array{rows: list<array<string, mixed>>, truncated: bool}
     */
    public function pages(string $path, array $query = [], int $max = 5): array
    {
        $rows = [];
        $truncated = false;

        for ($page = 1; $page <= $max; $page++) {
            $body = $this->get($path, $query + ['page' => $page]);

            foreach (is_array($body['data'] ?? null) ? $body['data'] : [] as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            $last = (int) ($body['last_page'] ?? 0);
            $more = $last > 0 ? $page < $last : ($body['next_page_url'] ?? null) !== null;

            if (! $more) {
                break;
            }

            $truncated = $page === $max;
        }

        return ['rows' => $rows, 'truncated' => $truncated];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function patch(string $path, array $body): array
    {
        $answer = $this->send('patch', $path, [], $body)->json();

        return is_array($answer) ? $answer : [];
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, mixed>  $body
     */
    protected function send(string $method, string $path, array $query = [], array $body = []): Response
    {
        if ($this->host() === '') {
            throw new BoogleException(
                'The address of Boogle is not set.',
                'Set LARAPILOT_BOOGLE_URL in .env, such as https://boogle.example.com.'
            );
        }

        if ($this->token() === '') {
            throw new BoogleException(
                'The Boogle token is not set.',
                'Set LARAPILOT_BOOGLE_TOKEN in .env. Create it in Boogle, as an admin user, in the profile under API tokens.'
            );
        }

        $query = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '');
        $url = $this->host().'/api/admin'.$path.($query === [] ? '' : '?'.http_build_query($query));

        try {
            $request = $this->http()->withToken($this->token());
            $response = $method === 'patch' ? $request->patch($url, $body) : $request->get($url);
        } catch (ConnectionException $e) {
            throw new BoogleException(
                'Boogle could not be reached at '.$this->host().'.',
                'Check the network and LARAPILOT_BOOGLE_URL.'
            );
        }

        if ($response->successful()) {
            return $response;
        }

        throw $this->failure($response);
    }

    protected function failure(Response $response): BoogleException
    {
        $detail = $response->json('message') ?? $response->json('error');
        $detail = is_string($detail) && $detail !== '' ? ' — '.$detail : '';

        return match ($response->status()) {
            401 => new BoogleException('Boogle refused the token'.$detail.'.', 'Create a new token in Boogle, in the profile of an admin user, and update LARAPILOT_BOOGLE_TOKEN.', 401),
            403 => new BoogleException('The user of this token is not an admin of Boogle'.$detail.'.', 'The admin API answers to admin users only: use the token of one.', 403),
            404 => new BoogleException('Boogle does not know this'.$detail.'.', 'Check LARAPILOT_BOOGLE_PROJECT, and that LARAPILOT_BOOGLE_URL is the address of Boogle and not of a page of it.', 404),
            422 => new BoogleException('Boogle did not accept what was sent'.$detail.'.', null, 422),
            429 => new BoogleException('Boogle asked to slow down'.$detail.'.', 'Wait a minute and run the command again.', 429),
            default => new BoogleException('Boogle answered '.$response->status().$detail.'.', null, $response->status()),
        };
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(max(1, (int) config('larapilot.boogle.timeout', 15)))->acceptJson();
    }

    protected function token(): string
    {
        return trim((string) config('larapilot.boogle.token', ''));
    }
}
