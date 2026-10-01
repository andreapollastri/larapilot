<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

/**
 * The instructions a frontend repository gives to coding agents, in every
 * format an editor reads them: `AGENTS.md` at any depth, `CLAUDE.md` with
 * its `@imports`, `GEMINI.md`, Cursor rules (`.cursorrules`,
 * `.cursor/rules/*.mdc` with `alwaysApply` / `globs` / `description`),
 * GitHub Copilot (`copilot-instructions.md`, `*.instructions.md` with
 * `applyTo`), Windsurf, Cline, Junie, Kiro, Amazon Q, Roo, and JetBrains AI.
 *
 * Larapilot drives the frontend from the Laravel workspace, where the
 * editor never loads these files by itself, so they are listed here with
 * the folder each one governs and how it applies — and read on purpose.
 */
final class AgentRules
{
    /**
     * Reading order of the formats inside one folder.
     *
     * @var list<string>
     */
    public const ORDER = ['agents', 'claude', 'gemini', 'cursor', 'copilot', 'windsurf', 'cline', 'junie', 'kiro', 'amazonq', 'roo', 'aiassistant', 'import'];

    protected const IMPORT_DEPTH = 4;

    protected const REFERENCE_LIMIT = 40;

    /**
     * @var list<array<string, mixed>>|null
     */
    protected ?array $rules = null;

    /**
     * @var list<array{path: string, from: string}>
     */
    protected array $references = [];

    public function __construct(
        protected RepoIndex $index,
    ) {}

    /**
     * Every rule file, deduplicated, imports resolved, shallow folders first.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $rules = [];

        foreach ($this->index->files as $file) {
            $rule = $this->classify($file);

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        $rules = $this->sort($rules);
        $rules = $this->withImports($rules);
        $rules = $this->deduplicate($rules);
        $this->references = $this->collectReferences($rules);

        return $this->rules = $rules;
    }

    /**
     * The rules of the folders between a workspace root and a repository
     * cloned inside it — only those on the way, never the sibling apps'.
     * Paths come back absolute: they are outside the repository.
     *
     * @param  list<string>|null  $files  Paths relative to the repository; null for the whole repository.
     * @return array<string, mixed>|null
     */
    public static function inherited(string $workspace, string $repository, ?array $files = null): ?array
    {
        $workspace = rtrim($workspace, '/');
        $relative = substr(rtrim($repository, '/'), strlen($workspace) + 1);

        if ($relative === '') {
            return null;
        }

        $segments = explode('/', $relative);
        $collected = [];

        for ($depth = 0; $depth < count($segments); $depth++) {
            $directory = $depth === 0 ? '' : implode('/', array_slice($segments, 0, $depth));
            $absolute = $directory === '' ? $workspace : $workspace.'/'.$directory;

            foreach (@scandir($absolute) ?: [] as $entry) {
                $path = ($directory === '' ? '' : $directory.'/').$entry;

                if (is_file($absolute.'/'.$entry) && RepoIndex::interesting($path) && ! in_array($entry, ['package.json', 'project.json', 'generators.json'], true)) {
                    $collected[] = $path;
                }
            }

            foreach (['.cursor/rules', '.github', '.windsurf/rules', '.clinerules', '.kiro/steering', '.amazonq/rules', '.roo/rules', '.claude', '.aiassistant/rules', '.junie'] as $folder) {
                if (! is_dir($absolute.'/'.$folder)) {
                    continue;
                }

                foreach (RepoFiles::walk($absolute.'/'.$folder, static fn (string $file): bool => true, 4, 2000)['files'] as $file) {
                    $path = ($directory === '' ? '' : $directory.'/').$folder.'/'.$file;

                    if (RepoIndex::interesting($path)) {
                        $collected[] = $path;
                    }
                }
            }
        }

        if ($collected === []) {
            return null;
        }

        $rules = new self(new RepoIndex($workspace, array_values(array_unique($collected)), false));
        $answer = $files === null
            ? $rules->forProjects([$relative])
            : $rules->forFiles(array_map(static fn (string $file): string => $relative.'/'.ltrim($file, '/'), $files));

        foreach (['must_read', 'conditional', 'on_request'] as $list) {
            $answer[$list] = array_map(static function (array $entry) use ($workspace): array {
                $entry['path'] = $workspace.'/'.$entry['path'];

                return $entry;
            }, $answer[$list]);
        }

        $answer['manual'] = array_map(static fn (string $path): string => $workspace.'/'.$path, $answer['manual']);
        $answer['workspace'] = $workspace;
        unset($answer['references'], $answer['order']);

        return $answer['must_read'] === [] && $answer['conditional'] === [] && $answer['on_request'] === [] ? null : $answer;
    }

    /**
     * Human guides the rules link to — read when the task touches what they
     * cover. `CONTRIBUTING.md` is one of them.
     *
     * @return list<array{path: string, from: string}>
     */
    public function references(): array
    {
        $this->all();

        return $this->references;
    }

    /**
     * The rules for work inside some projects: what governs their folders
     * (`must_read`), what governs only some files in them (`conditional`),
     * what an agent reads when the description fits (`on_request`), and the
     * manual rules nobody reads unless asked.
     *
     * @param  list<string>  $roots
     * @return array<string, mixed>
     */
    public function forProjects(array $roots): array
    {
        // With no project named yet, only the rules of the whole repository
        // apply; the folders of every other team would be noise.
        $wholeRepository = $roots === [];
        $roots = $wholeRepository ? ['.'] : $roots;
        $mustRead = [];
        $conditional = [];
        $onRequest = [];
        $manual = [];

        foreach ($this->all() as $rule) {
            $base = (string) $rule['base'];
            $governs = false;
            $inside = false;

            if ($wholeRepository && $base !== '.') {
                continue;
            }

            foreach ($roots as $root) {
                if (RepoFiles::isUnder($root, $base)) {
                    $governs = true;
                } elseif (RepoFiles::isUnder($base, $root)) {
                    $inside = true;
                }
            }

            if (! $governs && ! $inside) {
                continue;
            }

            $entry = $this->present($rule);

            if ($rule['apply'] === 'manual') {
                $manual[] = $entry;
            } elseif ($rule['apply'] === 'on_request') {
                $onRequest[] = $entry;
            } elseif ($rule['apply'] === 'glob' || ! $governs) {
                $conditional[] = $entry + ['when' => $this->when($rule, $governs)];
            } else {
                $mustRead[] = $entry;
            }
        }

        return $this->answer($mustRead, $conditional, $onRequest, $manual);
    }

    /**
     * The rules for a set of files about to be written.
     *
     * @param  list<string>  $files  Paths relative to the frontend root.
     * @return array<string, mixed>
     */
    public function forFiles(array $files): array
    {
        $mustRead = [];
        $onRequest = [];
        $manual = [];

        foreach ($this->all() as $rule) {
            $base = (string) $rule['base'];
            $covered = array_values(array_filter($files, static fn (string $file): bool => RepoFiles::isUnder($file, $base)));

            if ($covered === []) {
                continue;
            }

            $entry = $this->present($rule);

            if ($rule['apply'] === 'manual') {
                $manual[] = $entry;

                continue;
            }

            if ($rule['apply'] === 'on_request') {
                $onRequest[] = $entry;

                continue;
            }

            if ($rule['apply'] === 'glob') {
                $matched = array_values(array_filter($covered, function (string $file) use ($rule, $base): bool {
                    $relative = $base === '.' ? $file : substr($file, strlen($base) + 1);

                    foreach ($rule['globs'] as $glob) {
                        if (RepoFiles::matches((string) $glob, $relative)) {
                            return true;
                        }
                    }

                    return false;
                }));

                if ($matched === []) {
                    continue;
                }

                $entry['matched'] = array_slice($matched, 0, 10);
            }

            $mustRead[] = $entry;
        }

        return $this->answer($mustRead, [], $onRequest, $manual);
    }

    /**
     * @param  list<array<string, mixed>>  $mustRead
     * @param  list<array<string, mixed>>  $conditional
     * @param  list<array<string, mixed>>  $onRequest
     * @param  list<array<string, mixed>>  $manual
     * @return array<string, mixed>
     */
    protected function answer(array $mustRead, array $conditional, array $onRequest, array $manual): array
    {
        $fingerprint = [];

        foreach (array_merge($mustRead, $conditional) as $entry) {
            $fingerprint[] = $entry['path'].':'.$entry['sha'];
        }

        return [
            'must_read' => $mustRead,
            'conditional' => $conditional,
            'on_request' => $onRequest,
            'manual' => array_map(static fn (array $entry): string => (string) $entry['path'], $manual),
            'references' => $this->references(),
            'bytes' => array_sum(array_map(static fn (array $entry): int => (int) $entry['bytes'], $mustRead)),
            'fingerprint' => $fingerprint === [] ? null : substr(sha1(implode('|', $fingerprint)), 0, 12),
            'order' => 'Read must_read top to bottom: root first, deeper folders after. On a conflict the deeper folder wins; two rules of the same folder that disagree go to the user.',
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function when(array $rule, bool $governs): string
    {
        $where = $governs ? '' : 'files under '.$rule['base'].'/';

        if ($rule['apply'] !== 'glob') {
            return 'Before writing '.$where;
        }

        $globs = implode(', ', array_map('strval', $rule['globs']));

        return 'Before writing '.($where !== '' ? $where.' that match ' : 'files that match ').$globs;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    protected function present(array $rule): array
    {
        return array_filter([
            'path' => $rule['path'],
            'kind' => $rule['kind'],
            'base' => $rule['base'],
            'apply' => $rule['apply'],
            'globs' => $rule['globs'] === [] ? null : $rule['globs'],
            'description' => $rule['description'],
            'via' => $rule['via'] ?? null,
            'same_as' => ($rule['aliases'] ?? []) === [] ? null : $rule['aliases'],
            'bytes' => $rule['bytes'],
            'sha' => $rule['sha'],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function classify(string $path): ?array
    {
        $name = basename($path);
        $directory = RepoFiles::dirname($path);
        $kind = null;
        $base = $directory;
        $frontmatter = false;

        if ($name === 'AGENTS.md' || $name === 'AGENT.md') {
            $kind = 'agents';
        } elseif ($name === 'CLAUDE.md' && ! str_contains('/'.$path, '/.claude/rules/')) {
            $kind = 'claude';
            $base = str_ends_with($directory, '.claude') ? RepoFiles::dirname($directory) : $directory;
        } elseif ($name === 'GEMINI.md') {
            $kind = 'gemini';
        } elseif ($name === '.cursorrules') {
            $kind = 'cursor';
        } elseif ($name === '.windsurfrules') {
            $kind = 'windsurf';
        } elseif ($name === '.clinerules') {
            $kind = 'cline';
        } elseif ($name === 'copilot-instructions.md' && str_ends_with($directory, '.github')) {
            $kind = 'copilot';
            $base = RepoFiles::dirname($directory);
        } else {
            foreach ([
                '.cursor/rules/' => ['cursor', '/\.(mdc|md)$/'],
                '.github/instructions/' => ['copilot', '/\.instructions\.md$/'],
                '.windsurf/rules/' => ['windsurf', '/\.md$/'],
                '.clinerules/' => ['cline', '/\.(md|txt)$/'],
                '.kiro/steering/' => ['kiro', '/\.md$/'],
                '.amazonq/rules/' => ['amazonq', '/\.md$/'],
                '.roo/rules/' => ['roo', '/\.(md|txt)$/'],
                '.claude/rules/' => ['claude', '/\.md$/'],
                '.aiassistant/rules/' => ['aiassistant', '/\.md$/'],
                '.junie/' => ['junie', '/(^|\/)guidelines\.md$/'],
            ] as $folder => [$candidate, $pattern]) {
                $position = strpos('/'.$path, '/'.$folder);

                if ($position === false || preg_match($pattern, $path) !== 1) {
                    continue;
                }

                $kind = $candidate;
                $base = $position === 0 ? '.' : rtrim(substr($path, 0, $position - 1), '/');
                $frontmatter = true;

                break;
            }
        }

        if ($kind === null) {
            return null;
        }

        $content = RepoFiles::read($this->index->absolute($path), 512000);

        if ($content === null) {
            return null;
        }

        [$meta] = $frontmatter ? self::frontmatter($content) : [[], $content];
        [$apply, $globs] = $this->applyMode($kind, $meta);

        return [
            'path' => $path,
            'kind' => $kind,
            'base' => $base === '' ? '.' : $base,
            'apply' => $apply,
            'globs' => $globs,
            'description' => is_string($meta['description'] ?? null) && trim($meta['description']) !== '' ? trim($meta['description']) : null,
            'bytes' => strlen($content),
            'sha' => RepoFiles::shortHash($content),
            'content' => $content,
            'real' => realpath($this->index->absolute($path)) ?: $path,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{0: string, 1: list<string>}
     */
    protected function applyMode(string $kind, array $meta): array
    {
        $globs = [];

        foreach (['globs', 'applyTo', 'paths', 'fileMatchPattern'] as $key) {
            $value = $meta[$key] ?? null;

            foreach (is_array($value) ? $value : (is_string($value) ? self::splitList($value) : []) as $glob) {
                if (is_string($glob) && trim($glob) !== '') {
                    $globs[] = trim($glob);
                }
            }
        }

        $globs = array_values(array_unique($globs));
        $hasDescription = is_string($meta['description'] ?? null) && trim($meta['description']) !== '';

        if (($meta['alwaysApply'] ?? null) === true) {
            return ['always', []];
        }

        $trigger = is_string($meta['trigger'] ?? null) ? strtolower($meta['trigger']) : null;
        $inclusion = is_string($meta['inclusion'] ?? null) ? strtolower($meta['inclusion']) : null;

        if ($trigger === 'always_on' || $inclusion === 'always') {
            return ['always', []];
        }

        if ($trigger === 'manual' || $inclusion === 'manual') {
            return ['manual', []];
        }

        if ($trigger === 'model_decision') {
            return ['on_request', []];
        }

        if ($globs !== [] && ! in_array('**', $globs, true) && ! in_array('**/*', $globs, true)) {
            return ['glob', $globs];
        }

        if ($globs !== []) {
            return ['always', []];
        }

        if ($kind === 'cursor' && $meta !== []) {
            return [$hasDescription ? 'on_request' : 'manual', []];
        }

        return ['always', []];
    }

    /**
     * `@path/to/file.md` inside a rule pulls that file in (Claude Code reads
     * them this way, up to five hops). The imported file governs what its
     * importer governs.
     *
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    protected function withImports(array $rules): array
    {
        $out = [];
        $seen = [];

        foreach ($rules as $rule) {
            $seen[$rule['path']] = true;
        }

        foreach ($rules as $rule) {
            $out[] = $rule;
            $queue = [[$rule, 0]];

            while ($queue !== []) {
                [$current, $depth] = array_shift($queue);

                if ($depth >= self::IMPORT_DEPTH) {
                    continue;
                }

                foreach ($this->imports((string) $current['content'], (string) $current['path']) as $imported) {
                    if (isset($seen[$imported])) {
                        continue;
                    }

                    $content = RepoFiles::read($this->index->absolute($imported), 512000);

                    if ($content === null) {
                        continue;
                    }

                    $seen[$imported] = true;
                    $child = [
                        'path' => $imported,
                        'kind' => 'import',
                        'base' => $rule['base'],
                        'apply' => $rule['apply'],
                        'globs' => $rule['globs'],
                        'description' => $rule['description'],
                        'via' => $current['path'],
                        'bytes' => strlen($content),
                        'sha' => RepoFiles::shortHash($content),
                        'content' => $content,
                        'real' => realpath($this->index->absolute($imported)) ?: $imported,
                    ];

                    $out[] = $child;
                    $queue[] = [$child, $depth + 1];
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    protected function imports(string $content, string $from): array
    {
        $prose = self::withoutCode($content);

        if (preg_match_all('/(?:^|[\s(])@((?:\.{1,2}\/)?[A-Za-z0-9_\-.\/]+\.(?:md|mdc|txt|markdown))(?![\w\/])/m', $prose, $matches) === 0) {
            return [];
        }

        $paths = [];

        foreach ($matches[1] as $target) {
            $resolved = $this->resolve($target, $from);

            if ($resolved !== null) {
                $paths[] = $resolved;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array{path: string, from: string}>
     */
    protected function collectReferences(array $rules): array
    {
        $known = [];

        foreach ($rules as $rule) {
            $known[$rule['path']] = true;

            foreach ($rule['aliases'] ?? [] as $alias) {
                $known[$alias] = true;
            }
        }

        $references = [];

        foreach ($this->index->named('CONTRIBUTING.md') as $file) {
            if (substr_count($file, '/') <= 1) {
                $references[$file] = ['path' => $file, 'from' => 'repository'];
            }
        }

        foreach ($rules as $rule) {
            $prose = self::withoutCode((string) ($rule['content'] ?? ''));

            if (preg_match_all('/\[[^\]]*\]\(\s*<?([^)#\s>]+\.(?:md|mdx|mdc|txt))>?(?:#[^)]*)?\s*\)/i', $prose, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $target) {
                if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $target) === 1) {
                    continue;
                }

                $resolved = $this->resolve($target, (string) $rule['path']);

                if ($resolved !== null && ! isset($known[$resolved]) && ! isset($references[$resolved])) {
                    $references[$resolved] = ['path' => $resolved, 'from' => (string) $rule['path']];
                }
            }
        }

        return array_slice(array_values($references), 0, self::REFERENCE_LIMIT);
    }

    /**
     * A path written in a rule, resolved against the rule's folder and kept
     * only when it is a file inside the repository.
     */
    protected function resolve(string $target, string $from): ?string
    {
        $target = str_replace('\\', '/', trim($target));

        if ($target === '' || str_starts_with($target, '~')) {
            return null;
        }

        $base = str_starts_with($target, '/') ? '' : RepoFiles::dirname($from);
        $joined = ($base === '.' || $base === '' ? '' : $base.'/').ltrim($target, '/');
        $segments = [];

        foreach (explode('/', $joined) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $relative = implode('/', $segments);

        if ($relative === '' || ! is_file($this->index->absolute($relative))) {
            // Some teams write imports from the repository root.
            $rootRelative = (string) preg_replace('#^(\./)+#', '', ltrim($target, '/'));

            return $rootRelative !== '' && $rootRelative !== $relative && is_file($this->index->absolute($rootRelative))
                ? $rootRelative
                : null;
        }

        return $relative;
    }

    /**
     * Two files with the same content — `CLAUDE.md` as a symlink to
     * `AGENTS.md`, or a copy — are read once.
     *
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    protected function deduplicate(array $rules): array
    {
        $kept = [];
        $byKey = [];

        foreach ($rules as $rule) {
            $key = $rule['base'].'|'.$rule['apply'].'|'.implode(',', $rule['globs']).'|'.$rule['sha'];
            $real = (string) $rule['real'];

            $match = $byKey[$key] ?? $byKey['real:'.$real] ?? null;

            if ($match !== null) {
                $kept[$match]['aliases'][] = $rule['path'];

                continue;
            }

            $kept[] = $rule + ['aliases' => []];
            $index = array_key_last($kept);
            $byKey[$key] = $index;
            $byKey['real:'.$real] = $index;
        }

        return array_map(static function (array $rule): array {
            unset($rule['real']);

            return $rule;
        }, array_values($kept));
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    protected function sort(array $rules): array
    {
        usort($rules, static function (array $a, array $b): int {
            $depth = ($a['base'] === '.' ? 0 : substr_count((string) $a['base'], '/') + 1)
                <=> ($b['base'] === '.' ? 0 : substr_count((string) $b['base'], '/') + 1);

            if ($depth !== 0) {
                return $depth;
            }

            $base = strcmp((string) $a['base'], (string) $b['base']);

            if ($base !== 0) {
                return $base;
            }

            $kind = array_search($a['kind'], self::ORDER, true) <=> array_search($b['kind'], self::ORDER, true);

            return $kind !== 0 ? $kind : strcmp((string) $a['path'], (string) $b['path']);
        });

        return $rules;
    }

    /**
     * A tolerant frontmatter reader: Cursor writes `globs: *.tsx` unquoted,
     * which strict YAML refuses.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public static function frontmatter(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (preg_match('/^---\r?\n(.*?)\r?\n---[ \t]*(?:\r?\n|$)/s', $content, $matches) !== 1) {
            return [[], $content];
        }

        $meta = [];
        $list = null;

        foreach (preg_split('/\r?\n/', $matches[1]) ?: [] as $line) {
            if ($list !== null && preg_match('/^\s*-\s*(.*)$/', $line, $item) === 1) {
                $meta[$list][] = self::scalar($item[1]);

                continue;
            }

            if (preg_match('/^([A-Za-z_][\w-]*)\s*:\s*(.*)$/', $line, $pair) !== 1) {
                continue;
            }

            $key = $pair[1];
            $value = trim($pair[2]);

            if ($value === '') {
                $meta[$key] = [];
                $list = $key;

                continue;
            }

            $list = null;

            if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                $meta[$key] = array_values(array_filter(
                    array_map(static fn (string $part): mixed => self::scalar($part), self::splitList(substr($value, 1, -1))),
                    static fn (mixed $part): bool => $part !== ''
                ));

                continue;
            }

            $meta[$key] = self::scalar($value);
        }

        return [$meta, substr($content, strlen($matches[0]))];
    }

    /**
     * Commas separate globs, except inside `{a,b}`.
     *
     * @return list<string>
     */
    public static function splitList(string $value): array
    {
        $parts = [];
        $current = '';
        $depth = 0;

        foreach (str_split($value) as $char) {
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}' && $depth > 0) {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return array_values(array_filter(
            array_map(static fn (string $part): string => (string) self::scalar($part), $parts),
            static fn (string $part): bool => $part !== ''
        ));
    }

    protected static function scalar(string $value): mixed
    {
        $value = trim($value);

        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[strlen($value) - 1] === $value[0]) {
            return substr($value, 1, -1);
        }

        $lower = strtolower($value);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        return $value;
    }

    protected static function withoutCode(string $content): string
    {
        $content = (string) preg_replace('/^(```|~~~).*?^\1/ms', '', $content);

        return (string) preg_replace('/`[^`\n]*`/', '', $content);
    }
}
