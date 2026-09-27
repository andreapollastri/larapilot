<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\Markdown;

/**
 * What the agents of the project are told before any request: the files
 * they read by themselves (`CLAUDE.md`, `AGENTS.md`, …) and what the team
 * wrote for Boost under `.ai/`.
 *
 * Boost writes the guidelines of every package into those files, between
 * two markers, one section for each. A file is read section by section, so
 * it can be said who put each one there.
 *
 * A file is found by looking its id up in what is on disk. The id in the
 * address is never turned into a path.
 */
class AgentGuidelineService
{
    /** Between the markers of Boost, from Boost itself. */
    public const BOOST = 'boost';

    /** Between the markers of Boost, from Larapilot. */
    public const LARAPILOT = 'larapilot';

    /** Between the markers of Boost, from another package. */
    public const PACKAGE = 'package';

    /** Between the markers of Boost, from `.ai/guidelines` of the project. */
    public const PROJECT = 'project';

    /** Outside the markers: written by hand, or by another tool. */
    public const HAND = 'hand';

    /**
     * The files an agent reads by itself, and which agent that is.
     *
     * @var array<string, string>
     */
    public const FILES = [
        'CLAUDE.md' => 'Claude Code',
        'AGENTS.md' => 'Cursor, Codex, GitHub Copilot, Junie, and the agents that read AGENTS.md',
        'GEMINI.md' => 'Gemini CLI',
        '.github/copilot-instructions.md' => 'GitHub Copilot',
        '.junie/guidelines.md' => 'Junie',
        '.windsurfrules' => 'Windsurf',
    ];

    /**
     * The folders that hold one file for each rule.
     *
     * @var array<string, array{reader: string, kind: string}>
     */
    public const FOLDERS = [
        '.cursor/rules' => ['reader' => 'Cursor', 'kind' => 'agent'],
        '.ai/guidelines' => ['reader' => 'Laravel Boost, which writes it into the files of the agents', 'kind' => 'source'],
        '.ai/rules' => ['reader' => 'Laravel Boost, for the files the rule names', 'kind' => 'source'],
    ];

    protected const OWN_PACKAGE = 'andreapollastri/larapilot';

    protected const MAX_BYTES = 1_048_576;

    protected const MAX_FILES = 80;

    public function __construct(
        protected ConfigService $config,
        protected SkillLibraryService $skills,
    ) {}

    /**
     * Every file, with who reads it and who wrote what is in it. The text
     * itself is left out.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $files = [];

        foreach ($this->files() as $file) {
            $content = $this->read($file['path']);
            $sections = $this->sections($content);

            $origins = [];

            foreach ($sections as $section) {
                $origins[$section['origin']] = ($origins[$section['origin']] ?? 0) + 1;
            }

            $modified = @filemtime($file['path']);

            $files[] = $file + [
                'bytes' => strlen($content),
                'lines' => $content === '' ? 0 : substr_count($content, "\n") + 1,
                'modified' => $modified === false ? null : $modified,
                'managed' => $this->managed($content),
                'origins' => $origins,
                'sections' => array_map(static fn (array $section): array => [
                    'key' => $section['key'],
                    'origin' => $section['origin'],
                    'source' => $section['source'],
                    'bytes' => $section['bytes'],
                ], $sections),
            ];
        }

        return $files;
    }

    /**
     * One file with its text ready to read, or null when no file carries
     * that id.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,120}$/', $id) !== 1) {
            return null;
        }

        foreach ($this->files() as $file) {
            if ($file['id'] !== $id) {
                continue;
            }

            $content = $this->read($file['path']);
            $sections = [];

            foreach ($this->sections($content) as $index => $section) {
                $text = Markdown::demoteTopLevel($section['markdown']);

                $sections[] = $section + [
                    'id' => 'part-'.($index + 1),
                    'html' => Markdown::toHtml($text),
                ];
            }

            $modified = @filemtime($file['path']);
            // A file Boost did not write is read as one document.
            $body = $sections === [] ? ltrim($this->body($content)) : '';

            return $file + [
                'content' => $content,
                'html' => $body === '' ? '' : Markdown::toHtml($body),
                'headings' => $body === '' ? [] : Markdown::headings($body),
                'front_matter' => $sections === [] ? $this->frontMatter($content) : [],
                'bytes' => strlen($content),
                'lines' => $content === '' ? 0 : substr_count($content, "\n") + 1,
                'modified' => $modified === false ? null : $modified,
                'managed' => $this->managed($content),
                'sections' => $sections,
            ];
        }

        return null;
    }

    /**
     * @return list<array{id: string, file: string, path: string, reader: string, kind: string, template: bool}>
     */
    protected function files(): array
    {
        $root = rtrim($this->config->projectRoot(), '/\\');
        $inside = realpath($root);
        $found = [];

        if ($inside === false) {
            return [];
        }

        $candidates = [];

        foreach (self::FILES as $file => $reader) {
            $candidates[] = [$file, $reader, 'agent'];
        }

        // A path Boost was told to write the guidelines to, in place of
        // the usual one.
        foreach ((array) config('boost.agents', []) as $agent => $settings) {
            $path = is_array($settings) && is_string($settings['guidelines_path'] ?? null)
                ? trim(str_replace('\\', '/', $settings['guidelines_path']), '/')
                : '';

            if ($path !== '' && ! isset(self::FILES[$path]) && ! str_contains($path, '..')) {
                $candidates[] = [$path, ucwords(str_replace('_', ' ', (string) $agent)), 'agent'];
            }
        }

        foreach (self::FOLDERS as $folder => $about) {
            foreach ($this->within($root.'/'.$folder) as $relative) {
                $candidates[] = [$folder.'/'.$relative, $about['reader'], $about['kind']];
            }
        }

        foreach ($candidates as [$file, $reader, $kind]) {
            if (count($found) >= self::MAX_FILES) {
                break;
            }

            $path = $root.'/'.$file;
            $real = is_file($path) ? realpath($path) : false;
            $id = $this->id($file);

            // A link that leads out of the project is not followed.
            if ($real === false || $id === '' || isset($found[$id]) || ! str_starts_with($real, rtrim($inside, '/\\').DIRECTORY_SEPARATOR)) {
                continue;
            }

            $found[$id] = [
                'id' => $id,
                'file' => $file,
                'path' => $path,
                'reader' => $reader,
                'kind' => $kind,
                'template' => str_ends_with($file, '.blade.php'),
            ];
        }

        return array_values($found);
    }

    /**
     * The Markdown files of a folder, whatever their depth, as paths from
     * that folder on. The skills Boost keeps beside its guidelines are not
     * guidelines.
     *
     * @return list<string>
     */
    protected function within(string $folder): array
    {
        if (! is_dir($folder)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $item): bool => ! $item->isLink()
                    && ! ($item->isDir() && in_array($item->getFilename(), ['skill', 'skills'], true))
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if (count($files) >= self::MAX_FILES) {
                break;
            }

            if ($item->isFile() && preg_match('/\.(md|mdc|blade\.php)$/i', $item->getFilename()) === 1) {
                $files[] = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($folder, '/\\')) + 1));
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The text of a file, cut where Boost cut it.
     *
     * @return list<array{key: string, origin: string, source: string|null, markdown: string, bytes: int}>
     */
    protected function sections(string $content): array
    {
        if (preg_match('/<laravel-boost-guidelines>(.*?)<\/laravel-boost-guidelines>/s', $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $sections = [];
        $before = $this->byHand(substr($content, 0, $match[0][1]));
        $after = $this->byHand(substr($content, $match[0][1] + strlen($match[0][0])));

        if ($before !== '') {
            $sections[] = $this->section('Before the guidelines of Boost', self::HAND, null, $before);
        }

        $parts = preg_split('/^=== (.+?) rules ===[^\S\r\n]*$/m', $match[1][0], -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        // What comes before the first title belongs to nobody in particular.
        if (trim($parts[0] ?? '') !== '') {
            $sections[] = $this->section('Opening', self::BOOST, 'laravel/boost', trim($parts[0]));
        }

        for ($index = 1; $index + 1 < count($parts); $index += 2) {
            $key = trim($parts[$index]);
            $text = trim($parts[$index + 1]);

            if ($key === '' || $text === '') {
                continue;
            }

            [$origin, $source] = $this->author($key);
            $sections[] = $this->section($key, $origin, $source, $text);
        }

        if ($after !== '') {
            $sections[] = $this->section('After the guidelines of Boost', self::HAND, null, $after);
        }

        return $sections;
    }

    /**
     * @return array{key: string, origin: string, source: string|null, markdown: string, bytes: int}
     */
    protected function section(string $key, string $origin, ?string $source, string $markdown): array
    {
        return [
            'key' => $key,
            'origin' => $origin,
            'source' => $source,
            'markdown' => $markdown,
            'bytes' => strlen($markdown),
        ];
    }

    /**
     * Who wrote a section, read from the key Boost gave it: `.ai/…` is the
     * project, `vendor/package/…` a package that brings guidelines, and
     * anything else is Boost.
     *
     * @return array{0: string, 1: string|null}
     */
    protected function author(string $key): array
    {
        if (str_starts_with($key, '.ai/')) {
            return [self::PROJECT, '.ai/guidelines'];
        }

        $segments = explode('/', $key);

        foreach ([2, 1] as $length) {
            if (count($segments) <= $length) {
                continue;
            }

            $package = implode('/', array_slice($segments, 0, $length));

            if (preg_match('/^(@?[A-Za-z0-9][A-Za-z0-9._-]*)(\/[A-Za-z0-9][A-Za-z0-9._-]*)?$/', $package) !== 1) {
                continue;
            }

            if (strtolower($package) === self::OWN_PACKAGE) {
                return [self::LARAPILOT, self::OWN_PACKAGE];
            }

            foreach ([$this->skills->vendorDirectory(), $this->skills->nodeDirectory()] as $root) {
                if ($package !== 'laravel/boost' && is_dir($root.'/'.$package.'/resources/boost/guidelines')) {
                    return [self::PACKAGE, $package];
                }
            }
        }

        return [self::BOOST, 'laravel/boost'];
    }

    /**
     * What is outside the markers, without what Boost put there to keep
     * the two apart.
     */
    protected function byHand(string $text): string
    {
        $text = (string) preg_replace('/\A\s*---\s*\R\s*alwaysApply:\s*true\s*\R---\s*/', '', $text);
        $text = (string) preg_replace('/(?:\A|\R)\s*===\s*\z/', '', rtrim($text));

        return trim($text);
    }

    /**
     * The text without the front matter a rule file opens with.
     */
    protected function body(string $content): string
    {
        return preg_match('/\A\s*---[^\S\r\n]*\R.*?\R---[^\S\r\n]*(?:\R|\z)(.*)\z/s', $content, $match) === 1
            ? $match[1]
            : $content;
    }

    /**
     * What a rule file says about itself: a line for each key, as text.
     *
     * @return array<string, string>
     */
    protected function frontMatter(string $content): array
    {
        if (preg_match('/\A\s*---[^\S\r\n]*\R(.*?)\R---[^\S\r\n]*(?:\R|\z)/s', $content, $match) !== 1) {
            return [];
        }

        $facts = [];

        foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', trim($line), $parts) === 1 && trim($parts[2]) !== '') {
                $facts[$parts[1]] = trim($parts[2], " \t\"'");
            }
        }

        return $facts;
    }

    protected function managed(string $content): bool
    {
        return str_contains($content, '<laravel-boost-guidelines>') && str_contains($content, '</laravel-boost-guidelines>');
    }

    protected function id(string $file): string
    {
        return substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($file)), '-'), 0, 120);
    }

    protected function read(string $file): string
    {
        $size = @filesize($file);

        if ($size === false || $size > self::MAX_BYTES) {
            return '';
        }

        $content = @file_get_contents($file);

        return $content === false ? '' : $content;
    }
}
