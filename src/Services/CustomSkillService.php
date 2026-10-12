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

    /**
     * Left in every folder Larapilot mirrors a custom skill into: a folder
     * of that name without it was put there by someone else — Boost, the
     * agent, the developer — and is never overwritten.
     */
    public const MARKER = '.larapilot-custom-skill';

    /** @var list<string>|null */
    protected ?array $boostSkillNames = null;

    public function __construct(
        protected ConfigService $config,
    ) {}

    /**
     * Who owns a skill name a custom skill may not take: the `/larapilot-*`
     * skills of this package and the skills Boost ships. A custom skill of
     * that name would replace theirs in the agents — in `.ai/skills` it is
     * the override Boost publishes. Null when the name is free.
     */
    public function reservedBy(string $name): ?string
    {
        $name = strtolower(trim($name));

        if (str_starts_with($name, 'larapilot-')) {
            return 'Larapilot';
        }

        return in_array($name, $this->boostSkillNames(), true) ? 'Laravel Boost' : null;
    }

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
        $root = rtrim($this->directory(), '/\\');

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
     * @return array{name: string, path: string, relative_path: string, registered: list<string>, kept: list<string>}
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

        // `--force` is the developer saying this skill is theirs: a copy
        // of the same name in an agent folder is replaced.
        $report = $this->registerReport($name, $force);

        return [
            'name' => $name,
            'path' => $path,
            'relative_path' => $this->relativePath($path),
            'registered' => $report['registered'],
            'kept' => $report['kept'],
        ];
    }

    /**
     * Mirror every discovered custom skill into Boost / agent skill directories.
     *
     * @return list<array{name: string, registered: list<string>, kept: list<string>, reserved: string|null}>
     */
    public function registerAll(): array
    {
        $this->ensureDirectory();

        $registered = [];

        foreach (glob($this->directory().'/*/SKILL.md') ?: [] as $skillFile) {
            $name = basename(dirname($skillFile));
            $report = $this->registerReport($name);
            $registered[] = ['name' => $name] + $report;
        }

        return $registered;
    }

    /**
     * What `registerAll()` left alone, in words for a person: the folders
     * of another skill of the same name, and the reserved names.
     *
     * @param  list<array{name: string, registered: list<string>, kept: list<string>, reserved: string|null}>  $registered
     * @return list<string>
     */
    public static function conflicts(array $registered): array
    {
        $lines = [];

        foreach ($registered as $skill) {
            if (($skill['reserved'] ?? null) !== null) {
                $lines[] = '.larapilot/skills/'.$skill['name'].' is not published: “'.$skill['name'].'” is a skill of '.$skill['reserved'].'. Rename the folder.';
            }

            foreach ($skill['kept'] ?? [] as $folder) {
                $lines[] = $folder.' was left as it is: a skill of the same name that Larapilot did not write is there. Rename the custom skill, or delete that folder.';
            }
        }

        return $lines;
    }

    /**
     * Copy one custom skill into `.ai/skills/` (Boost) and any existing agent skill folders.
     *
     * @return list<string> Relative destination directories written.
     */
    public function register(string $name, bool $claim = false): array
    {
        return $this->registerReport($name, $claim)['registered'];
    }

    /**
     * Mirror one custom skill, and say what was left alone: a folder of the
     * same name that Larapilot did not write (unless `$claim`), or the
     * whole skill when its name is reserved.
     *
     * @return array{registered: list<string>, kept: list<string>, reserved: string|null}
     */
    public function registerReport(string $name, bool $claim = false): array
    {
        $report = ['registered' => [], 'kept' => [], 'reserved' => null];
        $name = trim($name);

        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..')) {
            return $report;
        }

        $source = rtrim($this->directory(), '/\\').DIRECTORY_SEPARATOR.$name;

        if (! is_dir($source) || ! is_file($source.DIRECTORY_SEPARATOR.'SKILL.md')) {
            return $report;
        }

        if (($report['reserved'] = $this->reservedBy($name)) !== null) {
            return $report;
        }

        foreach ($this->registrationTargets() as $targetRoot) {
            $destination = rtrim($targetRoot, '/\\').DIRECTORY_SEPARATOR.$name;

            if (! $claim && ! $this->owns($destination, $source)) {
                $report['kept'][] = $this->relativePath($destination);

                continue;
            }

            $this->mirrorDirectory($source, $destination);

            if (! is_file($destination.DIRECTORY_SEPARATOR.self::MARKER)) {
                AtomicFile::write($destination.DIRECTORY_SEPARATOR.self::MARKER, '');
            }

            $report['registered'][] = $this->relativePath($destination);
        }

        return $report;
    }

    /**
     * Whether a folder in an agent skill folder is Larapilot's to write: it
     * is not there yet, it carries the marker, or — a mirror made before
     * the marker existed — its `SKILL.md` is the source's, byte for byte.
     */
    protected function owns(string $destination, string $source): bool
    {
        if (! file_exists($destination)) {
            return true;
        }

        if (is_file($destination.DIRECTORY_SEPARATOR.self::MARKER)) {
            return true;
        }

        $mirror = $destination.DIRECTORY_SEPARATOR.'SKILL.md';

        return ! is_file($mirror) || hash_file('sha256', $mirror) === hash_file('sha256', $source.DIRECTORY_SEPARATOR.'SKILL.md');
    }

    /**
     * The names of the skills the installed Boost ships, read once.
     *
     * @return list<string>
     */
    protected function boostSkillNames(): array
    {
        if ($this->boostSkillNames !== null) {
            return $this->boostSkillNames;
        }

        $names = [];
        $provider = 'Laravel\\Boost\\BoostServiceProvider';

        if (! class_exists($provider)) {
            return $this->boostSkillNames = [];
        }

        // Where Boost is installed, whatever the vendor folder is called.
        $ai = dirname((string) (new \ReflectionClass($provider))->getFileName(), 2).'/.ai';

        foreach (array_merge(glob($ai.'/*/skill/*', GLOB_ONLYDIR) ?: [], glob($ai.'/*/*/skill/*', GLOB_ONLYDIR) ?: []) as $folder) {
            $names[strtolower(basename($folder))] = true;
        }

        return $this->boostSkillNames = array_keys($names);
    }

    /**
     * Remove the mirrored copies of one custom skill from `.ai/skills/` and
     * the agent skill folders. Call it while the source folder still exists:
     * a mirror goes only when its `SKILL.md` is the source's, byte for byte,
     * so a skill of the same name that came from somewhere else is kept.
     *
     * @return array{removed: list<string>, kept: list<string>}
     */
    public function unregister(string $name): array
    {
        $result = ['removed' => [], 'kept' => []];
        $name = trim($name);

        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..')) {
            return $result;
        }

        $source = rtrim($this->directory(), '/\\').DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'SKILL.md';

        if (! is_file($source)) {
            return $result;
        }

        $fingerprint = hash_file('sha256', $source);

        foreach ($this->registrationTargets() as $targetRoot) {
            $destination = rtrim($targetRoot, '/\\').DIRECTORY_SEPARATOR.$name;
            $mirror = $destination.DIRECTORY_SEPARATOR.'SKILL.md';

            if (is_link($destination) || ! is_file($mirror)) {
                continue;
            }

            if (! is_file($destination.DIRECTORY_SEPARATOR.self::MARKER) && hash_file('sha256', $mirror) !== $fingerprint) {
                $result['kept'][] = $this->relativePath($destination);

                continue;
            }

            $this->removeMirror($destination);
            $result['removed'][] = $this->relativePath($destination);
        }

        return $result;
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

        if (($owner = $this->reservedBy($name)) !== null) {
            throw new InvalidArgumentException("“{$name}” is a skill of {$owner}: a custom skill of that name would replace it in the agents. Choose another name.");
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
    public function parseFrontMatter(string $content): array
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

    public function parseSummary(string $content): ?string
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

    public function parseTitle(string $content): ?string
    {
        $body = $this->body($content);

        if (preg_match('/^#\s+(.+)$/m', $body, $matches) !== 1) {
            return null;
        }

        $title = trim($matches[1], " \t\"'`");

        return $title !== '' ? $title : null;
    }

    public function body(string $content): string
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

    protected function removeMirror(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path) && ! is_link($path)) {
                $this->removeMirror($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
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

            $content = (string) file_get_contents($from);

            // The Skills page mirrors on every visit: a copy already current
            // is not rewritten.
            if (is_file($to) && filesize($to) === strlen($content) && (string) file_get_contents($to) === $content) {
                continue;
            }

            AtomicFile::write($to, $content);
        }
    }
}
