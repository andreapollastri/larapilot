<?php

declare(strict_types=1);

namespace Larapilot\Services;

use InvalidArgumentException;
use Larapilot\Support\AtomicFile;

/**
 * Discover, persist, and Boost-register user-authored skills under `.larapilot/skills/`.
 */
class CustomSkillService
{
    /**
     * Agent skill folders Boost may already have published into.
     * `.ai/skills` is always created; the rest are mirrored only when they exist.
     *
     * @var list<string>
     */
    protected const AGENT_SKILL_ROOTS = [
        '.ai/skills',
        '.cursor/skills',
        '.claude/skills',
        '.github/skills',
        '.agents/skills',
        '.codex/skills',
        '.gemini/skills',
        '.junie/skills',
        '.opencode/skills',
        '.windsurf/skills',
    ];

    public function __construct(
        protected ConfigService $config,
    ) {}

    public function directory(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['custom_skills'] ?? '.larapilot/skills/');
    }

    public function boostDirectory(): string
    {
        return rtrim($this->config->projectRoot(), '/\\').DIRECTORY_SEPARATOR.'.ai'.DIRECTORY_SEPARATOR.'skills';
    }

    /**
     * @return list<array{
     *     name: string,
     *     path: string,
     *     relative_path: string,
     *     description: string|null,
     *     summary: string|null,
     *     title: string|null,
     *     trigger: string|null,
     *     registered: bool
     * }>
     */
    public function list(): array
    {
        $root = $this->directory();

        if (! is_dir($root)) {
            return [];
        }

        $skills = [];

        foreach (glob($root.'/*/SKILL.md') ?: [] as $skillFile) {
            $content = (string) file_get_contents($skillFile);
            $meta = $this->parseFrontMatter($content);
            $folder = basename(dirname($skillFile));
            $name = is_string($meta['name'] ?? null) && $meta['name'] !== ''
                ? (string) $meta['name']
                : $folder;
            $description = is_string($meta['description'] ?? null) && $meta['description'] !== ''
                ? (string) $meta['description']
                : null;
            $summary = $this->parseSummary($content);
            $title = $this->parseTitle($content);

            $skills[] = [
                'name' => $name,
                'path' => $skillFile,
                'relative_path' => $this->relativePath($skillFile),
                'description' => $description,
                'summary' => $summary !== $description ? $summary : null,
                'title' => $title !== null && strcasecmp($title, $name) !== 0 ? $title : null,
                'trigger' => '/'.$name,
                'registered' => $this->isRegistered($folder),
            ];
        }

        usort($skills, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $skills;
    }

    public function ensureDirectory(): void
    {
        $dir = $this->directory();

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $gitkeep = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'.gitkeep';

        if (! is_file($gitkeep)) {
            AtomicFile::write($gitkeep, '');
        }
    }

    /**
     * Persist a custom skill under `.larapilot/skills/{name}/SKILL.md` and register it with Boost.
     *
     * @return array{name: string, path: string, relative_path: string, registered: list<string>}
     */
    public function write(string $name, string $content, bool $force = false): array
    {
        $name = $this->normalizeName($name, $content);
        $content = $this->ensureFrontMatter($content, $name);

        $this->assertValidSkill($name, $content);

        $this->ensureDirectory();

        $folder = rtrim($this->directory(), '/\\').DIRECTORY_SEPARATOR.$name;
        $path = $folder.DIRECTORY_SEPARATOR.'SKILL.md';

        if (is_file($path) && ! $force) {
            throw new InvalidArgumentException("Custom skill [{$name}] already exists. Pass --force to overwrite.");
        }

        AtomicFile::write($path, $content);

        $registered = $this->register($name);

        return [
            'name' => $name,
            'path' => $path,
            'relative_path' => $this->relativePath($path),
            'registered' => $registered,
        ];
    }

    /**
     * Mirror every discovered custom skill into Boost / agent skill directories.
     *
     * @return list<array{name: string, registered: list<string>}>
     */
    public function registerAll(): array
    {
        $this->ensureDirectory();

        $registered = [];

        foreach (glob($this->directory().'/*/SKILL.md') ?: [] as $skillFile) {
            $name = basename(dirname($skillFile));
            $registered[] = [
                'name' => $name,
                'registered' => $this->register($name),
            ];
        }

        return $registered;
    }

    /**
     * Copy one custom skill into `.ai/skills/` (Boost) and any existing agent skill folders.
     *
     * @return list<string> Relative destination directories written.
     */
    public function register(string $name): array
    {
        $name = trim($name);

        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..')) {
            return [];
        }

        $source = rtrim($this->directory(), '/\\').DIRECTORY_SEPARATOR.$name;

        if (! is_dir($source) || ! is_file($source.DIRECTORY_SEPARATOR.'SKILL.md')) {
            return [];
        }

        $written = [];

        foreach ($this->registrationTargets() as $targetRoot) {
            $destination = rtrim($targetRoot, '/\\').DIRECTORY_SEPARATOR.$name;
            $this->mirrorDirectory($source, $destination);
            $written[] = $this->relativePath($destination);
        }

        return $written;
    }

    public function isRegistered(string $name): bool
    {
        $boost = $this->boostDirectory().DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'SKILL.md';

        return is_file($boost);
    }

    /**
     * @return list<string>
     */
    protected function registrationTargets(): array
    {
        $root = rtrim($this->config->projectRoot(), '/\\');
        $targets = [];

        foreach (self::AGENT_SKILL_ROOTS as $relative) {
            $absolute = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $always = $relative === '.ai/skills';

            if ($always || is_dir($absolute)) {
                $targets[] = $absolute;
            }
        }

        return $targets;
    }

    protected function normalizeName(string $name, string $content): string
    {
        $name = trim($name);

        if ($name !== '') {
            return $name;
        }

        $meta = $this->parseFrontMatter($content);
        $fromMeta = is_string($meta['name'] ?? null) ? trim((string) $meta['name']) : '';

        return $fromMeta;
    }

    protected function assertValidSkill(string $name, string $content): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Skill name is required (--name or YAML front matter name).');
        }

        if (preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $name) !== 1) {
            throw new InvalidArgumentException('Skill name must be kebab-case (a-z, 0-9, hyphens), 1–63 characters.');
        }

        $meta = $this->parseFrontMatter($content);
        $description = is_string($meta['description'] ?? null) ? trim((string) $meta['description']) : '';

        if ($description === '') {
            throw new InvalidArgumentException('SKILL.md must include YAML front matter with a non-empty description.');
        }
    }

    protected function ensureFrontMatter(string $content, string $name): string
    {
        $trimmed = ltrim($content);

        if (! str_starts_with($trimmed, '---')) {
            return "---\nname: {$name}\ndescription: Custom Larapilot skill.\n---\n\n".$content;
        }

        if (preg_match('/^---\s*\r?\n(.*?)\r?\n---/s', $trimmed, $matches) !== 1) {
            return $content;
        }

        $front = $matches[1];

        if (preg_match('/^name:\s*.+$/m', $front) === 1) {
            $front = preg_replace('/^name:\s*.+$/m', 'name: '.$name, $front, 1) ?? $front;
        } else {
            $front = 'name: '.$name."\n".$front;
        }

        return preg_replace('/^---\s*\r?\n(.*?)\r?\n---/s', "---\n{$front}\n---", $trimmed, 1) ?? $content;
    }

    /**
     * @return array<string, string>
     */
    protected function parseFrontMatter(string $content): array
    {
        if (! str_starts_with(trim($content), '---')) {
            return [];
        }

        if (preg_match('/^---\s*\r?\n(.*?)\r?\n---/s', $content, $matches) !== 1) {
            return [];
        }

        $meta = [];

        foreach (preg_split('/\r?\n/', $matches[1]) ?: [] as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):\s*(.+)$/', trim($line), $parts) !== 1) {
                continue;
            }

            $meta[$parts[1]] = trim($parts[2], " \t\"'");
        }

        return $meta;
    }

    protected function parseSummary(string $content): ?string
    {
        $body = $this->body($content);

        $paragraph = [];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($paragraph !== []) {
                    break;
                }

                continue;
            }

            if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, '---') || str_starts_with($trimmed, '|')) {
                continue;
            }

            $paragraph[] = $trimmed;
        }

        if ($paragraph === []) {
            return null;
        }

        $summary = trim(implode(' ', $paragraph));

        return $summary !== '' ? $summary : null;
    }

    protected function parseTitle(string $content): ?string
    {
        $body = $this->body($content);

        if (preg_match('/^#\s+(.+)$/m', $body, $matches) !== 1) {
            return null;
        }

        $title = trim($matches[1], " \t\"'`");

        return $title !== '' ? $title : null;
    }

    protected function body(string $content): string
    {
        if (preg_match('/^---\s*\r?\n.*?\r?\n---\s*\r?\n(.*)$/s', ltrim($content), $matches) === 1) {
            return $matches[1];
        }

        return $content;
    }

    protected function relativePath(string $absolute): string
    {
        $root = rtrim($this->config->projectRoot(), '/\\').DIRECTORY_SEPARATOR;

        if (str_starts_with($absolute, $root)) {
            return str_replace('\\', '/', substr($absolute, strlen($root)));
        }

        return str_replace('\\', '/', $absolute);
    }

    protected function mirrorDirectory(string $source, string $destination): void
    {
        if (! is_dir($destination) && ! @mkdir($destination, 0755, true) && ! is_dir($destination)) {
            throw new \RuntimeException("Unable to create directory {$destination}.");
        }

        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }

            $from = $source.DIRECTORY_SEPARATOR.$entry;
            $to = $destination.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($from)) {
                $this->mirrorDirectory($from, $to);

                continue;
            }

            AtomicFile::write($to, (string) file_get_contents($from));
        }
    }
}
