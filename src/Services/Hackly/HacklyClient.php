<?php

declare(strict_types=1);

namespace Larapilot\Services\Hackly;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The API of a Hackly instance (`/api/v1`), with the personal token of a
 * user of it. Hackly is self-hosted: the address is the team's own, never
 * the documentation site. The token lives in `.env` and nowhere else.
 */
class HacklyClient
{
    public const DOCS = 'https://hackly.web.ap.it';

    public function configured(): bool
    {
        return $this->url() !== '' && $this->token() !== '';
    }

    /**
     * The address of the instance, without `/api`.
     */
    public function url(): string
    {
        $url = rtrim(trim((string) config('larapilot.hackly.url', '')), '/');

        return (string) preg_replace('#/api(?:/v1)?$#', '', $url);
    }

    public function host(): string
    {
        return $this->url();
    }

    /**
     * Whether the address is the documentation site, which runs no Hackly.
     */
    public function pointsAtTheDocs(): bool
    {
        return in_array(strtolower(rtrim($this->url(), '/')), ['https://hackly.web.ap.it', 'http://hackly.web.ap.it'], true);
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

    public function text(string $path): string
    {
        return (string) $this->send('get', $path, [], [], 'text/markdown, text/plain')->body();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function post(string $path, array $body = []): array
    {
        $json = $this->send('post', $path, [], $body)->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function patch(string $path, array $body = []): array
    {
        $json = $this->send('patch', $path, [], $body)->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, mixed>  $body
     */
    protected function send(string $method, string $path, array $query = [], array $body = [], string $accept = 'application/json'): Response
    {
        if (! $this->configured()) {
            throw new HacklyException(
                'Hackly is not configured.',
                'Set LARAPILOT_HACKLY_URL (the address of your Hackly, such as https://hackly.example.com) and LARAPILOT_HACKLY_TOKEN (Profile → API tokens in Hackly) in .env.'
            );
        }

        $query = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '');
        $url = $this->url().'/api/v1'.$path.($query === [] ? '' : '?'.http_build_query($query));

        try {
            $request = Http::timeout(max(1, (int) config('larapilot.hackly.timeout', 15)))
                ->withToken($this->token())
                ->withHeaders(['Accept' => $accept, 'User-Agent' => 'Larapilot'])
                ->asJson();

            $response = match ($method) {
                'post' => $request->post($url, $body),
                'patch' => $request->patch($url, $body),
                default => $request->get($url),
            };
        } catch (ConnectionException) {
            throw new HacklyException(
                'Hackly could not be reached at '.$this->url().'.',
                $this->pointsAtTheDocs()
                    ? 'hackly.web.ap.it is the documentation of Hackly, not an instance: set LARAPILOT_HACKLY_URL to the address of your own Hackly.'
                    : 'Check the network and LARAPILOT_HACKLY_URL.'
            );
        }

        if ($response->successful()) {
            return $response;
        }

        $message = is_array($response->json()) ? (string) ($response->json()['message'] ?? '') : '';

        throw new HacklyException(
            match ($response->status()) {
                401 => 'Hackly refused the token.',
                403 => 'The Hackly token may not do this.',
                404 => 'Hackly has no such record'.($this->pointsAtTheDocs() ? ' — the address is the documentation site, not a Hackly' : '').'.',
                422 => 'Hackly refused the request'.($message !== '' ? ': '.$message : '.'),
                429 => 'Hackly asks to slow down.',
                default => 'Hackly answered '.$response->status().($message !== '' ? ': '.$message : '.'),
            },
            match ($response->status()) {
                401 => 'Check LARAPILOT_HACKLY_TOKEN: a personal token of your Hackly (Profile → API tokens), with the hk_ prefix.',
                403 => 'Give the token the write ability (php artisan hackly:token you@example.com --ability=write) to tell Hackly a decision or ask for a scan.',
                404 => $this->pointsAtTheDocs() ? 'Set LARAPILOT_HACKLY_URL to your own Hackly, such as https://hackly.example.com.' : null,
                429 => 'Try again in a minute.',
                default => null,
            },
            $response->status()
        );
    }

    protected function token(): string
    {
        return trim((string) config('larapilot.hackly.token', ''));
    }
}
