<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Facades\Http;

class NotifyService
{
    /**
     * @var list<string>
     */
    public const EVENTS = [
        'pr_opened',
        'pr_updated',
        'task_done',
        'spec_review',
        'spec_done',
        'spec_blocked',
        'review_changes',
        'schedule_drift',
        'ship_go',
        'ship_nogo',
        'security_fail',
        'doctor_fail',
        'custom',
    ];

    public function __construct(
        protected ConfigService $config,
    ) {}

    /**
     * Fan-out a short notification to every enabled channel.
     *
     * @param  array{event?: string, title?: string, body?: string|null, url?: string|null}  $payload
     * @return array{
     *     sent: bool,
     *     skipped: bool,
     *     event: string,
     *     title: string,
     *     channels: array<string, array{ok: bool, skipped?: bool, reason?: string, status?: int|null, error?: string}>
     * }
     */
    public function send(array $payload): array
    {
        $event = strtolower(trim((string) ($payload['event'] ?? 'custom')));

        if (! in_array($event, self::EVENTS, true)) {
            throw new \InvalidArgumentException(
                'Invalid event. Allowed: '.implode(', ', self::EVENTS)
            );
        }

        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '') {
            throw new \InvalidArgumentException('Notification title is required.');
        }

        $body = trim((string) ($payload['body'] ?? ''));
        $url = trim((string) ($payload['url'] ?? ''));
        $text = $this->formatMessage($event, $title, $body !== '' ? $body : null, $url !== '' ? $url : null);

        if (! $this->config->notificationsEnabled()) {
            return [
                'sent' => false,
                'skipped' => true,
                'event' => $event,
                'title' => $title,
                'channels' => [],
            ];
        }

        $channels = [];

        if ($this->config->settings()['notify_slack'] === 'YES') {
            $channels['slack'] = $this->sendSlack($text);
        }

        if ($this->config->settings()['notify_discord'] === 'YES') {
            $channels['discord'] = $this->sendDiscord($text);
        }

        if ($this->config->settings()['notify_telegram'] === 'YES') {
            $channels['telegram'] = $this->sendTelegram($text);
        }

        $sent = false;

        foreach ($channels as $result) {
            if (($result['ok'] ?? false) === true && ($result['skipped'] ?? false) !== true) {
                $sent = true;
                break;
            }
        }

        return [
            'sent' => $sent,
            'skipped' => $channels === [],
            'event' => $event,
            'title' => $title,
            'channels' => $channels,
        ];
    }

    /**
     * @return array{ok: bool, skipped?: bool, reason?: string, status?: int|null, error?: string}
     */
    protected function sendSlack(string $text): array
    {
        $webhook = trim((string) config('larapilot.integrations.slack_webhook_url', ''));

        if ($webhook === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'LARAPILOT_SLACK_WEBHOOK_URL is not set.',
            ];
        }

        return $this->postJson($webhook, ['text' => $text]);
    }

    /**
     * @return array{ok: bool, skipped?: bool, reason?: string, status?: int|null, error?: string}
     */
    protected function sendDiscord(string $text): array
    {
        $webhook = trim((string) config('larapilot.integrations.discord_webhook_url', ''));

        if ($webhook === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'LARAPILOT_DISCORD_WEBHOOK_URL is not set.',
            ];
        }

        return $this->postJson($webhook, ['content' => $text]);
    }

    /**
     * @return array{ok: bool, skipped?: bool, reason?: string, status?: int|null, error?: string}
     */
    protected function sendTelegram(string $text): array
    {
        $token = trim((string) config('larapilot.integrations.telegram_bot_token', ''));
        $chatId = trim((string) config('larapilot.integrations.telegram_chat_id', ''));

        if ($token === '' || $chatId === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'LARAPILOT_TELEGRAM_BOT_TOKEN and LARAPILOT_TELEGRAM_CHAT_ID are required.',
            ];
        }

        $endpoint = "https://api.telegram.org/bot{$token}/sendMessage";

        return $this->postJson($endpoint, [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, skipped?: bool, reason?: string, status?: int|null, error?: string}
     */
    protected function postJson(string $url, array $payload): array
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'status' => $response->status(),
                ];
            }

            return [
                'ok' => false,
                'status' => $response->status(),
                'error' => 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function formatMessage(string $event, string $title, ?string $body, ?string $url): string
    {
        $lines = [
            '[Larapilot] '.$event,
            $title,
        ];

        if ($body !== null && $body !== '') {
            $lines[] = $body;
        }

        if ($url !== null && $url !== '') {
            $lines[] = $url;
        }

        return implode("\n", $lines);
    }
}
