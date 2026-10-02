<?php

declare(strict_types=1);

namespace Larapilot\Services\Laravel;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\Events\MessageSent;
use Larapilot\Services\ConfigService;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Keeps the mail the application sends, to be read on the Laravel page:
 * who it went to, the subject, the HTML and the text, and the names of the
 * attachments. It listens to what Laravel says it sent, so it works with
 * every mailer — `log` and `array` included — and changes nothing of the
 * mail or of where it goes.
 */
class MailRecorder
{
    /** The most that is kept of a body, in bytes. */
    protected const BODY = 2097152;

    /** How far up the call stack the mailable is looked for. */
    protected const STACK = 60;

    protected RecordStore $store;

    public function __construct(protected ConfigService $config)
    {
        $this->store = new RecordStore('mail');
    }

    public function store(): RecordStore
    {
        return $this->store;
    }

    public function recording(): bool
    {
        return $this->config->laravelViewerRecords('mail');
    }

    /**
     * Never throws: a mail that cannot be kept is still a mail that was
     * sent.
     */
    public function handle(MessageSent $event): void
    {
        try {
            if ($this->recording()) {
                $this->record($event);
            }
        } catch (Throwable) {
            //
        }
    }

    protected function record(MessageSent $event): void
    {
        $message = $event->sent->getOriginalMessage();

        if (! $message instanceof Email) {
            return;
        }

        $html = $this->body($message->getHtmlBody());
        $text = $this->body($message->getTextBody());
        $parts = [];

        if ($html !== null) {
            $parts['html'] = substr($html, 0, self::BODY);
        }

        if ($text !== null) {
            $parts['txt'] = substr($text, 0, self::BODY);
        }

        $data = $event->data;

        $this->store->put([
            'at' => date(DATE_ATOM),
            'subject' => (string) $message->getSubject(),
            'from' => $this->addresses($message->getFrom()),
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
            'reply_to' => $this->addresses($message->getReplyTo()),
            'mailer' => is_string($data['mailer'] ?? null) ? $data['mailer'] : null,
            'transport' => $this->transport($data['mailer'] ?? null),
            'source' => $this->source($data),
            'queued' => (bool) ($data['__laravel_notification_queued'] ?? false),
            'html' => $html !== null,
            'text' => $text !== null,
            'size' => strlen($html ?? '') + strlen($text ?? ''),
            'cut' => strlen($html ?? '') > self::BODY || strlen($text ?? '') > self::BODY,
            'attachments' => $this->attachments($message),
            'context' => RunContext::describe(),
        ], $parts);
    }

    /**
     * @param  resource|string|null  $body
     */
    protected function body(mixed $body): ?string
    {
        if (is_resource($body)) {
            $body = @stream_get_contents($body, self::BODY + 1, 0);
        }

        return is_string($body) && $body !== '' ? $body : null;
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<array{address: string, name: string}>
     */
    protected function addresses(array $addresses): array
    {
        return array_values(array_map(static fn (Address $address): array => [
            'address' => $address->getAddress(),
            'name' => $address->getName(),
        ], $addresses));
    }

    /**
     * The mailable or the notification the mail came from, when Laravel
     * says which.
     *
     * @param  array<string, mixed>  $data
     */
    protected function source(array $data): ?string
    {
        foreach (['__laravel_notification', '__laravel_mailable'] as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        // Laravel 10 does not name the mailable. The event is fired while
        // it sends, so it is the one up the call stack.
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, self::STACK) as $frame) {
            if (($frame['object'] ?? null) instanceof Mailable) {
                return $frame['object']::class;
            }
        }

        return null;
    }

    protected function transport(mixed $mailer): ?string
    {
        $mailer = is_string($mailer) && $mailer !== '' ? $mailer : config('mail.default');
        $transport = is_string($mailer) ? config("mail.mailers.{$mailer}.transport") : null;

        return is_string($transport) && $transport !== '' ? $transport : null;
    }

    /**
     * The names of the attachments, never what is in them.
     *
     * @return list<array{name: string, type: string, size: int}>
     */
    protected function attachments(Email $message): array
    {
        $attachments = [];

        foreach ($message->getAttachments() as $part) {
            $attachments[] = [
                'name' => (string) ($part->getFilename() ?? 'attachment'),
                'type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'size' => strlen($part->getBody()),
            ];
        }

        return $attachments;
    }
}
