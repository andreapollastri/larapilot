<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\ContextManifest;
use Larapilot\Support\SharedRuntime;

/**
 * What a skill loads at activation, and what its session already holds.
 *
 * The runtime packs are compiled for the project: a block between
 * `<!-- when: … -->` and `<!-- end -->` is kept only while its condition holds
 * against the settings, so the agent reads the rules that apply and none of
 * the others. The compiled files live in `.larapilot/cache/runtime/`, a
 * folder that ignores itself in git and is rebuilt whenever it is stale.
 *
 * A session is one conversation. Each file handed out is recorded against
 * its token with the revision it had, so a later call in the same
 * conversation lists it as loaded instead of asking for a second read.
 */
class ContextService
{
    /**
     * Seconds a session is kept after its last call.
     */
    protected const SESSION_TTL = 172800;

    /**
     * Longest value kept from the PRD or the choices snapshot.
     */
    protected const BRIEF_VALUE_LENGTH = 160;

    /**
     * @var array<string, array<string, array{file: string, rev: string, tokens: int, bytes: int}>>
     */
    protected array $compiled = [];

    public function __construct(
        protected ConfigService $config,
        protected ChoicesService $choices,
        protected SpecService $specs,
        protected PrdService $prd,
        protected CustomSkillService $customSkills,
    ) {}

    public function cachePath(): string
    {
        return $this->config->absolutePath('.larapilot/cache');
    }

    public function runtimePath(): string
    {
        return $this->cachePath().'/runtime';
    }

    public function sessionsPath(): string
    {
        return $this->cachePath().'/sessions';
    }

    /**
     * The packs the package ships: pack name → source file. A file that only
     * lists its parts (`runtime-delivery.md` beside `runtime-delivery-1.md`)
     * is an index for people, not a pack.
     *
     * @return array<string, string>
     */
    public function sources(): array
    {
        $sources = [];

        foreach (SharedRuntime::packagedDocs() as $filename) {
            $pack = self::packOf($filename);

            if ($pack === null) {
                continue;
            }

            $path = SharedRuntime::packageDocPath($filename);

            if (is_file(substr($path, 0, -3).'-1.md')) {
                continue;
            }

            $sources[$pack] = $path;
        }

        ksort($sources);

        return $sources;
    }

    /**
     * `runtime-delivery-1.md` → `delivery-1`, `task-templates.md` →
     * `task-templates`. Null for a packaged doc that is not a pack.
     */
    public static function packOf(string $filename): ?string
    {
        if ($filename === 'task-templates.md') {
            return 'task-templates';
        }

        if (str_starts_with($filename, 'runtime-') && str_ends_with($filename, '.md')) {
            return substr($filename, strlen('runtime-'), -3);
        }

        return null;
    }

    /**
     * Everything a condition may be measured against, as strings.
     *
     * @return array<string, string>
     */
    public function facts(): array
    {
        $settings = $this->config->settings();
        $paths = $this->config->setupInfo()['paths'];
        $forge = false;

        foreach (['github', 'gitlab', 'bitbucket', 'azure'] as $key) {
            $forge = $forge || ($settings[$key] ?? 'NO') === 'YES';
        }

        return array_map('strval', $settings) + [
            'view' => 'project',
            'forge' => $forge ? 'YES' : 'NO',
            'frontend' => $this->config->hasFrontendRepo() ? 'external' : 'none',
            'dev_docs' => $this->config->devDocsStatus()['documented'] ? 'documented' : 'undocumented',
            'client_materials' => $this->populated((string) $paths['client_materials']) ? 'populated' : 'empty',
            'legacy' => $this->populated((string) $paths['legacy']) ? 'populated' : 'empty',
            'research' => $this->populated((string) $paths['research']) ? 'populated' : 'empty',
            'prd' => $this->prd->exists() ? 'present' : 'absent',
        ];
    }

    /**
     * The facts a marker inside a pack may name: the settings and what
     * follows from them. Everything else moves while a session runs.
     *
     * @param  array<string, string>  $facts
     * @return array<string, string>
     */
    public function markerFacts(array $facts): array
    {
        $dynamic = array_diff(array_keys(ContextManifest::FACTS), ContextManifest::MARKER_FACTS);

        return array_diff_key($facts, array_flip($dynamic));
    }

    /**
     * Whether a `key=A|B and other!=C or …` condition holds. A key the facts
     * do not carry reads as empty: `=` fails on it and `!=` passes.
     *
     * @param  array<string, string>  $facts
     */
    public static function evaluate(string $condition, array $facts): bool
    {
        foreach (preg_split('/\s+or\s+/i', trim($condition)) ?: [] as $alternative) {
            $holds = true;

            foreach (preg_split('/\s+and\s+/i', $alternative) ?: [] as $clause) {
                if (preg_match('/^([A-Za-z0-9_]+)\s*(!=|=)\s*(.+)$/', trim($clause), $match) !== 1) {
                    $holds = false;

                    break;
                }

                $allowed = array_map(
                    static fn (string $value): string => strtoupper(trim($value)),
                    explode('|', $match[3])
                );
                $among = in_array(strtoupper((string) ($facts[$match[1]] ?? '')), $allowed, true);

                if (($match[2] === '=') !== $among) {
                    $holds = false;

                    break;
                }
            }

            if ($holds) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep the blocks whose condition holds and drop the markers. With no
     * facts every block is kept: the pack with every value of every setting.
     *
     * @param  array<string, string>|null  $facts
     */
    public static function filter(string $markdown, ?array $facts): string
    {
        $kept = [];
        $open = [];

        foreach (preg_split('/\r\n|\r|\n/', $markdown) ?: [] as $line) {
            if (preg_match('/^\s*<!--\s*when:\s*(.+?)\s*-->\s*$/', $line, $match) === 1) {
                $open[] = $facts === null || self::evaluate($match[1], $facts);

                continue;
            }

            if (preg_match('/^\s*<!--\s*end\s*-->\s*$/', $line) === 1) {
                array_pop($open);

                continue;
            }

            if (! in_array(false, $open, true)) {
                $kept[] = $line;
            }
        }

        $text = (string) preg_replace('/\n{3,}/', "\n\n", implode("\n", $kept));
        // A block dropped between two rules leaves them back to back.
        $text = (string) preg_replace('/^---\n\n(?:---\n\n)+/m', "---\n\n", $text);

        return trim($text)."\n";
    }

    /**
     * The conditions of the markers a pack carries, in order.
     *
     * @return list<string>
     */
    public static function markers(string $markdown): array
    {
        preg_match_all('/^\s*<!--\s*when:\s*(.+?)\s*-->\s*$/m', $markdown, $matches);

        return $matches[1];
    }

    /**
     * Compile every pack for the facts given and leave the result on disk.
     * A pack with nothing left but its headings is left out.
     *
     * @param  array<string, string>|null  $facts
     * @return array<string, array{file: string, rev: string, tokens: int, bytes: int}>
     */
    public function compile(?array $facts = null): array
    {
        $facts = $this->markerFacts($facts ?? $this->facts());
        $key = sha1((string) json_encode($facts));

        if (isset($this->compiled[$key])) {
            return $this->compiled[$key];
        }

        $files = [];

        foreach ($this->sources() as $pack => $path) {
            $source = (string) file_get_contents($path);
            $files[$pack.'.md'] = [$pack, self::filter($source, $facts)];

            if (in_array($pack, ContextManifest::FULL, true)) {
                $files[$pack.'.full.md'] = [$pack.'.full', self::filter($source, null)];
            }
        }

        $packs = [];

        foreach ($files as $file => [$pack, $content]) {
            if (self::hollow($content)) {
                continue;
            }

            $packs[$pack] = [
                'file' => $file,
                'rev' => substr(sha1($content), 0, 8),
                'tokens' => self::tokens($content),
                'bytes' => strlen($content),
            ];

            $target = $this->runtimePath().'/'.$file;

            if (! is_file($target) || file_get_contents($target) !== $content) {
                AtomicFile::write($target, $content);
            }
        }

        $this->ignoreCache();
        $this->prune(array_column($packs, 'file'));

        return $this->compiled[$key] = $packs;
    }

    /**
     * The estimate Zoey posts: characters over four.
     */
    public static function tokens(string $content): int
    {
        return (int) ceil(mb_strlen($content) / 4);
    }

    /**
     * What `larapilot:context {skill}` answers.
     *
     * @param  list<string>  $with  Extra packs, or groups of them (`delivery`).
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException When the skill or a pack is unknown.
     */
    public function resolve(string $skill, ?string $session = null, bool $fresh = false, array $with = []): array
    {
        $key = ContextManifest::key($skill);
        $manifest = ContextManifest::skills();
        $custom = null;

        // A packaged skill the manifest does not know yet still gets the
        // core: a new skill works before its packs are declared.
        if (! isset($manifest[$key]) && ! is_file($this->packagedSkillPath($key))) {
            $custom = $this->customSkill($skill);

            if ($custom === null) {
                throw new \InvalidArgumentException(
                    "Unknown skill: {$skill}. Packaged skills: ".implode(', ', array_keys($manifest)).'.'
                );
            }
        }

        $definition = $manifest[$key] ?? ['category' => 'other'];
        $facts = $this->facts();
        $packs = $this->compile($facts);

        $required = $this->required($definition, $manifest, $facts, $packs, $with);
        $onDemand = $this->onDemand($definition, $facts, $packs, $required);

        [$token, $held] = $this->session($session, $fresh);

        $read = [];
        $loaded = [];
        $tokens = ['read' => 0, 'loaded' => 0];

        foreach ($required as $pack) {
            $meta = $packs[$pack];

            if (($held[$meta['file']] ?? null) === $meta['rev']) {
                $loaded[] = $meta['file'];
                $tokens['loaded'] += $meta['tokens'];

                continue;
            }

            $read[] = ['file' => $meta['file'], 'tokens' => $meta['tokens']];
            $tokens['read'] += $meta['tokens'];
            $held[$meta['file']] = $meta['rev'];
        }

        $this->remember($token, $held);

        $name = $custom['name'] ?? 'larapilot-'.$key;
        $skillTokens = $this->skillTokens($custom['path'] ?? $this->packagedSkillPath($key));
        $settings = $this->config->settings();
        $setup = $this->config->setupInfo();

        $data = [
            'skill' => $name,
            'project_root' => $setup['project_root'],
            'session' => $token,
            'settings' => $settings,
            'paths' => $custom !== null
                ? $setup['paths']
                : array_intersect_key($setup['paths'], array_flip($definition['paths'] ?? [])),
            'project' => $this->project($facts),
        ];

        foreach ($definition['slices'] ?? [] as $slice) {
            if ($slice === 'dev_docs') {
                $data['dev_docs'] = $setup['dev_docs'];
            }

            if ($slice === 'frontend') {
                $data['frontend'] = $setup['frontend'];
            }
        }

        $data['runtime'] = [
            'dir' => $this->runtimePath(),
            'read' => $read,
            'loaded' => $loaded,
            'on_demand' => $onDemand,
            'tokens' => [
                'skill' => $skillTokens,
                'read' => $tokens['read'],
                'loaded' => $tokens['loaded'],
                'total' => $skillTokens + $tokens['read'] + $tokens['loaded'],
            ],
        ];

        if ($settings['lucille'] === 'YES') {
            $data['usage_log'] = 'php artisan larapilot:usage-log --category='.$definition['category']
                .' --skill='.$name.' --tokens={N} --minutes={M} --estimated';
        }

        return $data;
    }

    /**
     * The packs a skill reads before it starts, in reading order.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, array<string, mixed>>  $manifest
     * @param  array<string, string>  $facts
     * @param  array<string, array{file: string, rev: string, tokens: int, bytes: int}>  $packs
     * @param  list<string>  $with
     * @return list<string>
     */
    protected function required(array $definition, array $manifest, array $facts, array $packs, array $with): array
    {
        $entries = array_merge(ContextManifest::CORE, $definition['read'] ?? []);

        foreach ($definition['include'] ?? [] as $included => $condition) {
            if (self::evaluate($condition, $facts)) {
                $entries = array_merge($entries, $manifest[$included]['read'] ?? []);
            }
        }

        if (($facts['effort'] ?? '') === 'MAX') {
            $entries = array_merge($entries, $definition['deep'] ?? []);
        }

        $required = [];

        foreach ($entries as $entry) {
            [$pack, $condition] = ContextManifest::entry($entry);

            if ($condition !== null && ! self::evaluate($condition, $facts)) {
                continue;
            }

            if (isset($packs[$pack])) {
                $required[$pack] = true;
            }
        }

        foreach ($this->expand($with, $packs) as $pack) {
            $required[$pack] = true;
        }

        return array_keys($required);
    }

    /**
     * The packs a skill reads only when the moment named arrives.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $facts
     * @param  array<string, array{file: string, rev: string, tokens: int, bytes: int}>  $packs
     * @param  list<string>  $required
     * @return list<array{file: string, tokens: int, when: string}>
     */
    protected function onDemand(array $definition, array $facts, array $packs, array $required): array
    {
        $onDemand = [];

        foreach ($definition['on_demand'] ?? [] as $entry => $when) {
            [$pack, $condition] = ContextManifest::entry($entry);

            if ($condition !== null && ! self::evaluate($condition, $facts)) {
                continue;
            }

            if (! isset($packs[$pack]) || in_array($pack, $required, true)) {
                continue;
            }

            $onDemand[$pack] = [
                'file' => $packs[$pack]['file'],
                'tokens' => $packs[$pack]['tokens'],
                'when' => $when,
            ];
        }

        return array_values($onDemand);
    }

    /**
     * Pack names from `--with`, a group standing for its parts.
     *
     * @param  list<string>  $names
     * @param  array<string, array{file: string, rev: string, tokens: int, bytes: int}>  $packs
     * @return list<string>
     */
    protected function expand(array $names, array $packs): array
    {
        $expanded = [];

        foreach ($names as $name) {
            $name = strtolower(trim($name));
            $name = str_starts_with($name, 'runtime-') ? substr($name, strlen('runtime-')) : $name;
            $name = str_ends_with($name, '.md') ? substr($name, 0, -3) : $name;

            if ($name === '') {
                continue;
            }

            $parts = array_values(array_filter(
                array_keys($packs),
                static fn (string $pack): bool => preg_match('/^'.preg_quote($name, '/').'-\d+$/', $pack) === 1
            ));

            if (isset($packs[$name])) {
                $expanded[] = $name;
            } elseif ($parts !== []) {
                $expanded = array_merge($expanded, $parts);
            } elseif (! isset($this->sources()[$name])) {
                throw new \InvalidArgumentException(
                    "Unknown pack: {$name}. Packs: ".implode(', ', array_keys($this->sources())).'.'
                );
            }
        }

        return $expanded;
    }

    /**
     * The session named, or a new one. A token that is not on disk, or
     * `--fresh`, starts from nothing: every file is read again.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected function session(?string $token, bool $fresh): array
    {
        $this->forgetOldSessions();

        if (! $fresh && is_string($token) && preg_match('/^[a-z0-9]{6}$/', $token) === 1) {
            $path = $this->sessionsPath().'/'.$token.'.json';

            if (is_file($path)) {
                $stored = json_decode((string) file_get_contents($path), true);
                $files = is_array($stored) && is_array($stored['files'] ?? null) ? $stored['files'] : [];

                return [$token, array_map('strval', $files)];
            }
        }

        do {
            $token = '';

            for ($i = 0; $i < 6; $i++) {
                $token .= '0123456789abcdefghijklmnopqrstuvwxyz'[random_int(0, 35)];
            }
        } while (is_file($this->sessionsPath().'/'.$token.'.json'));

        return [$token, []];
    }

    /**
     * @param  array<string, string>  $files
     */
    protected function remember(string $token, array $files): void
    {
        AtomicFile::write(
            $this->sessionsPath().'/'.$token.'.json',
            (string) json_encode(['updated' => time(), 'files' => $files], JSON_UNESCAPED_SLASHES)
        );
    }

    protected function forgetOldSessions(): void
    {
        foreach (glob($this->sessionsPath().'/*.json') ?: [] as $file) {
            if ((int) @filemtime($file) < time() - self::SESSION_TTL) {
                @unlink($file);
            }
        }
    }

    /**
     * The cache is derived from the package and the settings: it ignores
     * itself, so nothing in it is ever committed.
     */
    protected function ignoreCache(): void
    {
        $gitignore = $this->cachePath().'/.gitignore';

        if (! is_file($gitignore)) {
            AtomicFile::write($gitignore, "*\n");
        }
    }

    /**
     * Remove the compiled files no pack accounts for any more.
     *
     * @param  list<string>  $files
     */
    protected function prune(array $files): void
    {
        foreach (glob($this->runtimePath().'/*.md') ?: [] as $path) {
            if (! in_array(basename($path), $files, true)) {
                @unlink($path);
            }
        }
    }

    /**
     * A pack that kept its headings and nothing under them.
     */
    protected static function hollow(string $content): bool
    {
        foreach (preg_split('/\n/', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '#')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a folder holds anything besides its scaffolding.
     */
    protected function populated(string $directory): bool
    {
        $directory = rtrim($directory, '/\\');

        if (! is_dir($directory)) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && ! in_array($file->getFilename(), ['README.md', '.gitkeep', '.DS_Store'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the product is, in the few answers every skill asks the PRD for.
     *
     * @param  array<string, string>  $facts
     * @return array<string, mixed>
     */
    protected function project(array $facts): array
    {
        $project = ['prd' => $facts['prd'] === 'present'];

        if ($project['prd']) {
            $project['prd_tokens'] = self::tokens((string) $this->prd->read());
        }

        $metrics = $this->specs->metrics();
        $project['specs'] = $metrics['total'];

        if ($metrics['total'] > 0) {
            $project['by_status'] = $metrics['by_status'];
        }

        foreach ($this->choices->brief() as $label => $value) {
            $project[$label] = mb_strlen($value) > self::BRIEF_VALUE_LENGTH
                ? mb_substr($value, 0, self::BRIEF_VALUE_LENGTH).'…'
                : $value;
        }

        return $project;
    }

    /**
     * A skill the project wrote, by the name of its folder or of its front
     * matter.
     *
     * @return array{name: string, path: string}|null
     */
    protected function customSkill(string $skill): ?array
    {
        $wanted = ltrim(strtolower(trim($skill)), '/');

        foreach ($this->customSkills->list() as $custom) {
            if ($wanted === strtolower($custom['name']) || $wanted === strtolower(basename(dirname($custom['path'])))) {
                return ['name' => $custom['name'], 'path' => $custom['path']];
            }
        }

        return null;
    }

    protected function packagedSkillPath(string $key): string
    {
        return dirname(__DIR__, 2).'/resources/boost/skills/larapilot-'.$key.'/SKILL.md';
    }

    protected function skillTokens(string $path): int
    {
        return is_file($path) ? self::tokens((string) file_get_contents($path)) : 0;
    }
}
