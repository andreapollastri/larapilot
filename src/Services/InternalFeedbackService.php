<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\Markdown;
use Larapilot\Support\SpecCode;

class InternalFeedbackService
{
    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
    ) {}

    public function enabled(): bool
    {
        return $this->config->commentsEnabled();
    }

    public function directory(): string
    {
        $config = $this->config->resolve();

        return rtrim($this->config->absolutePath(
            $config['paths']['internal_feedback'] ?? '.larapilot/internal-feedback/'
        ), DIRECTORY_SEPARATOR);
    }

    public function filePath(string $code): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$code.'.md';
    }

    /**
     * @param  array<string, mixed>|null  $spec
     */
    public function canComment(?array $spec): bool
    {
        if (! $this->enabled() || $spec === null) {
            return false;
        }

        return strtoupper((string) ($spec['status'] ?? '')) !== $this->config->status('done');
    }

    public function read(string $code): ?string
    {
        if (! SpecCode::isValid($code)) {
            return null;
        }

        $path = $this->filePath($code);

        if (! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
    }

    /**
     * @param  array<string, mixed>|null  $spec
     * @return array{
     *     enabled: bool,
     *     writable: bool,
     *     path: string,
     *     entry_count: int,
     *     blocking_count: int,
     *     content: string|null,
     *     html: string|null,
     *     entries: list<array{at: string, author: string, status: string, body: string, blocks_merge: bool}>
     * }
     */
    public function forSpec(string $code, ?array $spec = null): array
    {
        $spec ??= $this->specs->find($code);
        $content = $this->read($code);
        $entries = $content !== null ? $this->parseEntries($content) : [];
        $blocking = array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['blocks_merge']
        ));

        return [
            'enabled' => $this->enabled(),
            'writable' => $this->canComment($spec),
            'path' => $this->config->relativePath($this->filePath($code)),
            'entry_count' => count($entries),
            'blocking_count' => count($blocking),
            'content' => $content,
            'html' => $content !== null && trim($content) !== '' ? Markdown::toHtml($content) : null,
            'entries' => $entries,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $spec
     * @return array{
     *     enabled: bool,
     *     available: bool,
     *     entry_count: int,
     *     blocking_count: int,
     *     writable: bool,
     *     path: string
     * }
     */
    public function summary(string $code, ?array $spec = null): array
    {
        $detail = $this->forSpec($code, $spec);

        return [
            'enabled' => $detail['enabled'],
            'available' => $detail['entry_count'] > 0,
            'entry_count' => $detail['entry_count'],
            'blocking_count' => $detail['blocking_count'],
            'writable' => $detail['writable'],
            'path' => $detail['path'],
        ];
    }

    public function append(
        string $code,
        string $author,
        string $message,
        ?string $statusAt = null,
        bool $blocksMerge = false,
    ): void {
        if (! SpecCode::isValid($code)) {
            throw new \InvalidArgumentException("Invalid spec code: {$code}");
        }

        if (! $this->enabled()) {
            throw new \RuntimeException('Internal feedback comments are disabled.');
        }

        $spec = $this->specs->find($code);

        if (! $this->canComment($spec)) {
            throw new \RuntimeException('Comments are closed for this spec.');
        }

        $author = trim($author);
        $message = trim($message);

        if ($author === '' || $message === '') {
            throw new \InvalidArgumentException('Author and message are required.');
        }

        $statusAt ??= strtoupper((string) ($spec['status'] ?? 'TODO'));
        $timestamp = now()->format('Y-m-d H:i');
        $tag = $blocksMerge ? ' · `[blocks-merge]`' : '';
        $block = <<<MD


---

**{$timestamp}** · {$author} · status: {$statusAt}{$tag}  
{$message}

---
MD;

        $path = $this->filePath($code);
        $directory = $this->directory();

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! is_file($path)) {
            AtomicFile::write($path, "# {$code} — Internal feedback{$block}");
        } else {
            AtomicFile::write($path, rtrim((string) file_get_contents($path)).$block);
        }
    }

    public function blockingMarkdown(string $code): string
    {
        $content = $this->read($code);

        if ($content === null || trim($content) === '') {
            return '';
        }

        $blocks = array_values(array_filter(
            $this->parseEntries($content),
            fn (array $entry): bool => $entry['blocks_merge']
        ));

        if ($blocks === []) {
            return '';
        }

        $parts = [];

        foreach ($blocks as $entry) {
            $parts[] = '- **'.$entry['at'].'** · '.$entry['author'].' ('.$entry['status'].'): '.$entry['body'];
        }

        return "### From internal feedback\n\n".implode("\n\n", $parts);
    }

    public function delete(string $code): void
    {
        if (! SpecCode::isValid($code)) {
            return;
        }

        $path = $this->filePath($code);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @return list<array{at: string, author: string, status: string, body: string, blocks_merge: bool}>
     */
    public function parseEntries(string $content): array
    {
        $entries = [];

        if (preg_match_all(
            '/\*\*(?<at>[^*]+)\*\*\s*·\s*(?<author>[^·]+)\s*·\s*status:\s*(?<status>[^\n]+)\n(?<body>.*?)(?=\n---|\z)/s',
            $content,
            $matches,
            PREG_SET_ORDER
        ) !== false) {
            foreach ($matches as $match) {
                $statusLine = trim($match['status']);
                $blocksMerge = str_contains($statusLine, '[blocks-merge]')
                    || str_contains($statusLine, '[rework]');
                $status = trim((string) preg_replace('/\s*·\s*`?\[(?:blocks-merge|rework)\]`?/i', '', $statusLine));

                $entries[] = [
                    'at' => trim($match['at']),
                    'author' => trim($match['author']),
                    'status' => $status,
                    'body' => trim($match['body']),
                    'blocks_merge' => $blocksMerge,
                ];
            }
        }

        return $entries;
    }
}
