<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

/**
 * One walk of the frontend repository that keeps the files the scan needs:
 * the project manifests and every file an editor reads as agent rules.
 */
final class RepoIndex
{
    /**
     * @var list<string>
     */
    public const NAMES = [
        'project.json', 'package.json', 'generators.json',
        'AGENTS.md', 'AGENT.md', 'CLAUDE.md', 'GEMINI.md',
        '.cursorrules', '.windsurfrules', '.clinerules', 'CONTRIBUTING.md',
    ];

    /**
     * Folders whose Markdown files are rules for one editor or another.
     *
     * @var list<string>
     */
    public const RULE_FOLDERS = [
        '.cursor/rules/', '.github/instructions/', '.windsurf/rules/', '.clinerules/',
        '.kiro/steering/', '.amazonq/rules/', '.roo/rules/', '.claude/rules/',
        '.aiassistant/rules/', '.junie/',
    ];

    /**
     * @param  list<string>  $files
     */
    public function __construct(
        public readonly string $root,
        public readonly array $files,
        public readonly bool $truncated,
    ) {}

    public static function build(string $root): self
    {
        $root = rtrim($root, '/\\');
        $walk = RepoFiles::walk($root, static fn (string $path): bool => self::interesting($path), 12);

        return new self($root, $walk['files'], $walk['truncated']);
    }

    public static function interesting(string $path): bool
    {
        if (in_array(basename($path), self::NAMES, true)) {
            return true;
        }

        if ($path === '.github/copilot-instructions.md' || str_ends_with($path, '/.github/copilot-instructions.md')) {
            return true;
        }

        $haystack = '/'.$path;

        foreach (self::RULE_FOLDERS as $folder) {
            if (str_contains($haystack, '/'.$folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function named(string $name): array
    {
        return array_values(array_filter(
            $this->files,
            static fn (string $file): bool => basename($file) === $name
        ));
    }

    public function absolute(string $relative): string
    {
        return $relative === '.' || $relative === '' ? $this->root : $this->root.'/'.$relative;
    }
}
