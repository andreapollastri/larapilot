<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\Markdown;
use Symfony\Component\Yaml\Yaml;

/**
 * Every skill the agents of the project can run, to be read on the
 * dashboard, whoever brought it: the project, Larapilot, another package,
 * Laravel Boost, or a hand that dropped it into the folder of an agent.
 *
 * A skill is found by looking its name up in what is on disk. The name in
 * the address is never turned into a path, and a template is never run:
 * what Boost published from it is read instead.
 */
class SkillLibraryService
{
    /** Written for this project, in `.larapilot/skills/`. */
    public const CUSTOM = 'custom';

    /** Ships with Larapilot. */
    public const PACKAGED = 'packaged';

    /** Written for Boost by the team, in `.ai/`. */
    public const PROJECT = 'project';

    /** Brought by another package of the project. */
    public const PACKAGE = 'package';

    /** Built into Laravel Boost. */
    public const BOOST = 'boost';

    /** In the folder of an agent, and nowhere else. */
    public const AGENT = 'agent';

    /**
     * When one name is carried by several skills, the first of these wins.
     *
     * @var list<string>
     */
    public const ORIGINS = [self::CUSTOM, self::PACKAGED, self::PROJECT, self::PACKAGE, self::BOOST, self::AGENT];

    /**
     * Where an agent reads its skills from, and which agent that is.
     *
     * @var array<string, string>
     */
    public const AGENT_FOLDERS = [
        '.claude/skills' => 'Claude Code',
        '.cursor/skills' => 'Cursor',
        '.github/skills' => 'GitHub Copilot',
        '.agents/skills' => 'Codex, Amp, OpenCode, Zed',
        '.junie/skills' => 'Junie',
        '.kiro/skills' => 'Kiro',
        '.factory/skills' => 'Factory Droid',
        '.grok/skills' => 'Grok Build',
        '.pi/skills' => 'Pi',
        '.codex/skills' => 'Codex',
        '.gemini/skills' => 'Gemini CLI',
        '.opencode/skills' => 'OpenCode',
        '.windsurf/skills' => 'Windsurf',
    ];

    protected const BOOST_PACKAGE = 'laravel/boost';

    protected const OWN_PACKAGE = 'andreapollastri/larapilot';

    /** A file above this is not compared nor read: it is not a skill. */
    protected const MAX_BYTES = 1_048_576;

    public function __construct(
        protected ConfigService $config,
        protected CustomSkillService $custom,
    ) {}

    public function packagedDirectory(): string
    {
        return dirname(__DIR__, 2).'/resources/boost/skills';
    }

    /**
     * Where Composer installs the packages: `vendor`, unless the project
     * says otherwise in `composer.json`.
     */
    public function vendorDirectory(): string
    {
        $manifest = $this->manifest('composer.json');
        $folder = is_string($manifest['config']['vendor-dir'] ?? null) && trim($manifest['config']['vendor-dir']) !== ''
            ? trim($manifest['config']['vendor-dir'])
            : 'vendor';

        return rtrim($this->config->absolutePath($folder), '/\\');
    }

    public function nodeDirectory(): string
    {
        return rtrim($this->config->absolutePath('node_modules'), '/\\');
    }

    /**
     * @return array{
     *     custom: list<array<string, mixed>>,
     *     packaged: list<array<string, mixed>>,
     *     project: list<array<string, mixed>>,
     *     packages: list<array<string, mixed>>,
     *     boost: list<array<string, mixed>>,
     *     agent: list<array<string, mixed>>,
     *     agents: list<array{folder: string, agent: string, skills: int}>,
     *     boost_state: array{installed: bool, configured: bool, tracked: list<string>, agents: list<string>}
     * }
     */
    public function all(): array
    {
        $root = rtrim($this->config->projectRoot(), '/\\');
        $state = $this->boostState();

        $custom = $this->scan($this->folders(rtrim($this->custom->directory(), '/\\')), self::CUSTOM, '.larapilot/skills', $root);
        $packaged = $this->scan($this->folders($this->packagedDirectory()), self::PACKAGED, self::OWN_PACKAGE, $this->packagedDirectory());
        $packages = $this->fromPackages();
        $boost = $this->fromBoost();

        $known = $this->names($custom, $packaged, $packages, $boost);

        // A folder in `.ai/skills` that carries the name of a skill already
        // known is its published copy, or the project's own version of it:
        // it is told on that skill, not listed as another one.
        $project = array_values(array_filter(
            $this->fromProject($root),
            static fn (array $skill): bool => ! isset($known[strtolower($skill['name'])]) && ! isset($known[strtolower($skill['folder'])])
        ));

        $known += $this->names($project);
        $folders = $this->agentFolders($root);
        $agent = $this->fromAgents($folders, $known, $root);

        $library = [
            'custom' => $custom,
            'packaged' => $packaged,
            'project' => $project,
            'packages' => $packages,
            'boost' => $boost,
            'agent' => $agent,
        ];

        foreach ($library as $group => $skills) {
            foreach ($skills as $index => $skill) {
                $library[$group][$index] = $this->placed($skill, $folders, $root, $state['tracked']);
            }
        }

        // One name can be carried by a package and by Boost, or by two
        // versions Boost keeps: one of them was published.
        $named = $this->versions(array_merge($library['packages'], $library['boost']));
        $library['packages'] = array_values(array_filter($named, static fn (array $skill): bool => $skill['origin'] === self::PACKAGE));
        $library['boost'] = array_values(array_filter($named, static fn (array $skill): bool => $skill['origin'] === self::BOOST));

        // What Boost ships for a package the project does not use is not
        // published: it is kept apart, so it does not read as a skill of
        // the project.
        usort($library['boost'], static fn (array $a, array $b): int => [(int) ! $a['published'], $a['name'], (string) $a['version']]
            <=> [(int) ! $b['published'], $b['name'], (string) $b['version']]);

        $agents = [];

        foreach ($folders as $folder => $label) {
            $agents[] = [
                'folder' => $folder,
                'agent' => $label,
                'skills' => count($this->folders($root.'/'.$folder)),
            ];
        }

        return $library + ['agents' => $agents, 'boost_state' => $state];
    }

    /**
     * One skill with its text ready to read, or null when no skill carries
     * that name. With several skills of one name the first origin wins,
     * unless `$from` names the one that is meant.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $name, ?string $from = null): ?array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,80}$/', $name) !== 1) {
            return null;
        }

        if ($from !== null && preg_match('/^[a-z0-9][a-z0-9.-]{0,160}$/', $from) !== 1) {
            return null;
        }

        $library = $this->all();
        $same = [];

        foreach (['custom', 'packaged', 'project', 'packages', 'boost', 'agent'] as $group) {
            foreach ($library[$group] as $skill) {
                if (strcasecmp($skill['name'], $name) === 0 || strcasecmp($skill['folder'], $name) === 0) {
                    $same[] = $skill;
                }
            }
        }

        if ($same === []) {
            return null;
        }

        $skill = null;

        foreach ($same as $candidate) {
            if ($from === null ? $candidate['published'] || $candidate['origin'] !== self::BOOST : $candidate['id'] === $from) {
                $skill = $candidate;

                break;
            }
        }

        // Nothing published carries the name: the first one is read.
        $skill ??= $from === null ? $same[0] : null;

        if ($skill === null) {
            return null;
        }

        $content = $this->read($skill['path']);
        $shown = $content;
        $shownFrom = null;

        // A template says little as it is written. What Boost made of it,
        // in the folder of an agent, is what the agent reads.
        if ($skill['template']) {
            foreach ($skill['installed'] as $copy) {
                $published = $this->read($copy['path']);

                if ($published !== '') {
                    $shown = $published;
                    $shownFrom = $copy['relative_path'];

                    break;
                }
            }
        }

        $body = ltrim($this->custom->body($shown));
        $others = [];

        foreach ($same as $candidate) {
            if ($candidate['id'] !== $skill['id']) {
                $others[] = [
                    'id' => $candidate['id'],
                    'origin' => $candidate['origin'],
                    'source' => $candidate['source'],
                    'version' => $candidate['version'],
                    'published' => $candidate['published'],
                    'relative_path' => $candidate['relative_path'],
                ];
            }
        }

        return array_merge($skill, [
            'content' => $content,
            'html' => Markdown::toHtml($body),
            'headings' => Markdown::headings($body),
            'front_matter' => $this->frontMatter($content),
            'lines' => substr_count($content, "\n") + 1,
            'files' => $this->companions(dirname($skill['path'])),
            'shown_from' => $shownFrom,
            'others' => $others,
            'agents' => $library['agents'],
        ]);
    }

    /**
     * What the project asked of Boost, read from `boost.json`.
     *
     * @return array{installed: bool, configured: bool, tracked: list<string>, agents: list<string>}
     */
    public function boostState(): array
    {
        $settings = $this->manifest('boost.json');

        return [
            'installed' => is_dir($this->vendorDirectory().'/'.self::BOOST_PACKAGE),
            'configured' => $settings !== [],
            'tracked' => $this->strings($settings['skills'] ?? []),
            'agents' => $this->strings($settings['agents'] ?? []),
        ];
    }

    /**
     * The folders of agents that are in the project, with the agent that
     * reads each one. A path Boost was told to use instead is read too.
     *
     * @return array<string, string>
     */
    public function agentFolders(?string $root = null): array
    {
        $root ??= rtrim($this->config->projectRoot(), '/\\');
        $known = self::AGENT_FOLDERS;

        foreach ((array) config('boost.agents', []) as $agent => $settings) {
            $path = is_array($settings) && is_string($settings['skills_path'] ?? null)
                ? trim(str_replace('\\', '/', $settings['skills_path']), '/')
                : '';

            if ($path !== '' && ! isset($known[$path]) && ! str_contains($path, '..') && ! str_starts_with($path, '/')) {
                $known[$path] = ucwords(str_replace('_', ' ', (string) $agent));
            }
        }

        return array_filter($known, static fn (string $folder): bool => is_dir($root.'/'.$folder), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fromPackages(): array
    {
        $skills = [];
        $own = realpath($this->packagedDirectory());

        foreach ($this->packageFolders() as $package => $found) {
            if ($package === self::BOOST_PACKAGE || $package === self::OWN_PACKAGE || realpath($found['skills']) === $own) {
                continue;
            }

            foreach ($this->scan($this->folders($found['skills']), self::PACKAGE, $package, $found['path']) as $skill) {
                $skills[] = $skill + ['direct' => $found['direct'], 'manager' => $found['manager']];
            }
        }

        usort($skills, static fn (array $a, array $b): int => [$a['source'], $a['name']] <=> [$b['source'], $b['name']]);

        return $skills;
    }

    /**
     * Every installed package that brings skills for Boost.
     *
     * @return array<string, array{path: string, skills: string, direct: bool, manager: string}>
     */
    public function packageFolders(): array
    {
        $found = [];
        $sets = [
            'composer' => [$this->vendorDirectory(), ['/*/*'], $this->required('composer.json', ['require', 'require-dev'])],
            'npm' => [$this->nodeDirectory(), ['/*', '/@*/*'], $this->required('package.json', ['dependencies', 'devDependencies'])],
        ];

        foreach ($sets as $manager => [$root, $patterns, $required]) {
            if (! is_dir($root)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                foreach (glob($root.$pattern.'/resources/boost/skills', GLOB_ONLYDIR) ?: [] as $skills) {
                    $path = dirname($skills, 3);
                    $package = str_replace('\\', '/', substr($path, strlen($root) + 1));

                    $found[$package] ??= [
                        'path' => $path,
                        'skills' => $skills,
                        // Boost publishes nothing of a package the project
                        // does not ask for itself.
                        'direct' => in_array(strtolower($package), $required, true),
                        'manager' => $manager,
                    ];
                }
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fromBoost(): array
    {
        $package = $this->vendorDirectory().'/'.self::BOOST_PACKAGE;
        $base = $package.'/.ai';

        if (! is_dir($base)) {
            return [];
        }

        $skills = [];

        foreach ($this->aiFolders($base) as $found) {
            foreach ($this->scan($this->folders($found['path']), self::BOOST, self::BOOST_PACKAGE, $package) as $skill) {
                $skill['about'] = $found['about'];
                $skill['version'] = $found['version'];
                $skill['id'] = $this->id(self::BOOST, $found['about'].($found['version'] === null ? '' : '-'.$found['version']));
                $skills[] = $skill;
            }
        }

        return $skills;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fromProject(string $root): array
    {
        $base = $root.'/.ai';

        if (! is_dir($base)) {
            return [];
        }

        $skills = $this->scan($this->folders($base.'/skills'), self::PROJECT, '.ai/skills', $root);

        foreach ($this->aiFolders($base) as $found) {
            foreach ($this->scan($this->folders($found['path']), self::PROJECT, '.ai/'.$found['about'], $root) as $skill) {
                $skill['about'] = $found['about'];
                $skill['version'] = $found['version'];
                $skill['id'] = $this->id(self::PROJECT, $found['about'].($found['version'] === null ? '' : '-'.$found['version']));
                $skills[] = $skill;
            }
        }

        usort($skills, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $skills;
    }

    /**
     * The skills that are in the folder of an agent and that nothing
     * accounts for: added by hand, or by a tool Larapilot does not know.
     *
     * @param  array<string, string>  $folders
     * @param  array<string, true>  $known
     * @return list<array<string, mixed>>
     */
    protected function fromAgents(array $folders, array $known, string $root): array
    {
        $skills = [];

        foreach ($folders as $folder => $agent) {
            foreach ($this->scan($this->folders($root.'/'.$folder), self::AGENT, $folder, $root) as $skill) {
                $key = strtolower($skill['name']);

                if (isset($known[$key]) || isset($known[strtolower($skill['folder'])]) || isset($skills[$key])) {
                    continue;
                }

                $skills[$key] = $skill;
            }
        }

        ksort($skills);

        return array_values($skills);
    }

    /**
     * The `skill` folders Boost looks into under an `.ai` folder: one for
     * each package, and one for each version of a package.
     *
     * @return list<array{path: string, about: string, version: string|null}>
     */
    protected function aiFolders(string $base): array
    {
        $found = [];

        foreach (glob($base.'/*/skill', GLOB_ONLYDIR) ?: [] as $folder) {
            $found[] = ['path' => $folder, 'about' => basename(dirname($folder)), 'version' => null];
        }

        foreach (glob($base.'/*/*/skill', GLOB_ONLYDIR) ?: [] as $folder) {
            $found[] = ['path' => $folder, 'about' => basename(dirname($folder, 2)), 'version' => basename(dirname($folder))];
        }

        return $found;
    }

    /**
     * The folders of skills inside one folder.
     *
     * @return list<string>
     */
    protected function folders(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        return array_values(array_filter(
            glob(rtrim($root, '/\\').'/*', GLOB_ONLYDIR) ?: [],
            fn (string $folder): bool => $this->skillFile($folder) !== null
        ));
    }

    protected function skillFile(string $folder): ?string
    {
        // Boost reads the template first, when a skill has both.
        foreach (['SKILL.blade.php', 'SKILL.md'] as $name) {
            if (is_file($folder.'/'.$name)) {
                return $folder.'/'.$name;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $folders
     * @param  string  $within  what a skill has to stay inside of, once its links are followed
     * @return list<array<string, mixed>>
     */
    protected function scan(array $folders, string $origin, string $source, string $within): array
    {
        $skills = [];
        $inside = realpath($within);

        foreach ($folders as $directory) {
            $file = $this->skillFile($directory);
            $real = $file === null ? false : realpath($file);

            // A link that leads out of the project, or of the package, is
            // not followed.
            if ($file === null || $real === false || $inside === false || ! str_starts_with($real, rtrim($inside, '/\\').DIRECTORY_SEPARATOR)) {
                continue;
            }

            $content = $this->read($file);
            $meta = $this->frontMatter($content);
            $folder = basename($directory);
            $name = is_string($meta['name'] ?? null) && trim($meta['name']) !== '' ? trim($meta['name']) : $folder;

            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,80}$/', $name) !== 1) {
                $name = $folder;
            }

            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,80}$/', $name) !== 1) {
                continue;
            }

            $description = is_string($meta['description'] ?? null) && trim($meta['description']) !== ''
                ? trim((string) preg_replace('/\s+/', ' ', $meta['description']))
                : null;
            $title = $this->custom->parseTitle($content);
            $modified = @filemtime($file);

            $skills[] = [
                'id' => $this->id($origin, $source),
                'name' => $name,
                'folder' => $folder,
                'origin' => $origin,
                'source' => $source,
                'about' => null,
                'version' => null,
                'path' => $file,
                'relative_path' => $this->shownPath($file, $origin, $source, $within),
                'template' => str_ends_with($file, '.blade.php'),
                'title' => $title !== null && strcasecmp($title, $name) !== 0 && ! str_contains($title, '{{') ? $title : null,
                'description' => $description ?? $this->custom->parseSummary($content),
                'trigger' => '/'.$name,
                // Only a skill of the project has to be registered: Boost
                // finds the others by itself.
                'registered' => $origin === self::CUSTOM ? $this->custom->isRegistered($folder) : null,
                'bytes' => strlen($content),
                'modified' => $modified === false ? null : $modified,
                'author' => is_string($meta['metadata']['author'] ?? null) ? $meta['metadata']['author'] : null,
                'license' => is_string($meta['license'] ?? null) ? $meta['license'] : null,
            ];
        }

        usort($skills, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $skills;
    }

    /**
     * Where the agents find the skill, and whether what they find is what
     * the source says.
     *
     * @param  array<string, mixed>  $skill
     * @param  array<string, string>  $folders
     * @param  list<string>  $tracked
     * @return array<string, mixed>
     */
    protected function placed(array $skill, array $folders, string $root, array $tracked): array
    {
        $source = $this->read($skill['path']);
        $installed = [];

        foreach ($folders as $folder => $agent) {
            foreach (array_unique([$skill['name'], $skill['folder']]) as $name) {
                $copy = $this->skillFile($root.'/'.$folder.'/'.$name);

                if ($copy === null) {
                    continue;
                }

                $installed[] = [
                    'folder' => $folder,
                    'agent' => $agent,
                    'path' => $copy,
                    'relative_path' => $folder.'/'.$name.'/'.basename($copy),
                    'state' => match (true) {
                        realpath($copy) === realpath($skill['path']) => 'same',
                        $skill['template'] => 'made',
                        default => $this->read($copy) === $source ? 'same' : 'differs',
                    },
                ];

                break;
            }
        }

        // The project can keep its own version of a skill in `.ai/skills`:
        // Boost publishes that one in place of the package's.
        $override = null;

        if (! in_array($skill['origin'], [self::CUSTOM, self::PROJECT, self::AGENT], true)) {
            $own = $this->skillFile($root.'/.ai/skills/'.$skill['name']);

            if ($own !== null && ! $skill['template'] && $this->read($own) !== $source) {
                $override = '.ai/skills/'.$skill['name'].'/'.basename($own);
            }
        }

        $skill['installed'] = $installed;
        $skill['override'] = $override;
        $skill['tracked'] = in_array($skill['name'], $tracked, true);
        $skill['published'] = $installed !== [] || $skill['origin'] === self::AGENT;

        return $skill;
    }

    /**
     * Boost keeps one skill for each version of a package, under one name,
     * and a package can bring its own under that name too. One of them is
     * published. Which one is read from the text the agents have: the one
     * that shares the most lines with it, and the first one when two share
     * as many.
     *
     * @param  list<array<string, mixed>>  $skills
     * @return list<array<string, mixed>>
     */
    protected function versions(array $skills): array
    {
        $byName = [];

        foreach ($skills as $index => $skill) {
            if ($skill['installed'] !== []) {
                $byName[strtolower($skill['name'])][] = $index;
            }
        }

        foreach ($byName as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $best = null;
            $score = -1.0;

            foreach ($indexes as $index) {
                $shared = $this->shared($this->read($skills[$index]['path']), $this->read($skills[$index]['installed'][0]['path']));

                if ($shared > $score) {
                    $score = $shared;
                    $best = $index;
                }
            }

            foreach ($indexes as $index) {
                if ($index !== $best) {
                    $skills[$index]['installed'] = [];
                    $skills[$index]['published'] = false;
                }
            }
        }

        return $skills;
    }

    /**
     * How much of a text is found, line by line, in another: 0 to 1.
     */
    protected function shared(string $source, string $copy): float
    {
        $lines = static fn (string $text): array => array_values(array_filter(
            array_map('trim', preg_split('/\R/', $text) ?: []),
            static fn (string $line): bool => $line !== ''
        ));

        $wanted = $lines($source);

        if ($wanted === []) {
            return 0.0;
        }

        $there = array_flip($lines($copy));
        $found = 0;

        foreach ($wanted as $line) {
            if (isset($there[$line])) {
                $found++;
            }
        }

        return $found / count($wanted);
    }

    /**
     * What sits beside the skill in its folder: templates, checklists,
     * scripts the skill refers to.
     *
     * @return list<array{path: string, bytes: int}>
     */
    protected function companions(string $folder): array
    {
        $files = [];
        $base = rtrim($folder, '/\\');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if (count($files) >= 50) {
                break;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($base) + 1));

            if ($item->isLink() || ! $item->isFile() || in_array($relative, ['SKILL.md', 'SKILL.blade.php'], true) || in_array($item->getFilename(), ['.gitkeep', '.DS_Store'], true)) {
                continue;
            }

            $files[] = ['path' => $relative, 'bytes' => (int) $item->getSize()];
        }

        usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $files;
    }

    /**
     * The front matter as YAML, which Boost skills write in full: quoted
     * text, nested keys. What is not YAML is read line by line.
     *
     * @return array<string, mixed>
     */
    protected function frontMatter(string $content): array
    {
        if (preg_match('/^\s*---[^\S\r\n]*\R(.*?)\R---[^\S\r\n]*(?:\R|$)/s', $content, $matches) === 1) {
            try {
                $parsed = Yaml::parse($matches[1]);

                if (is_array($parsed)) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // read line by line below
            }
        }

        return $this->custom->parseFrontMatter($content);
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

    /**
     * @param  list<array<string, mixed>>  ...$groups
     * @return array<string, true>
     */
    protected function names(array ...$groups): array
    {
        $names = [];

        foreach ($groups as $skills) {
            foreach ($skills as $skill) {
                $names[strtolower($skill['name'])] = true;
                $names[strtolower($skill['folder'])] = true;
            }
        }

        return $names;
    }

    /**
     * What names one skill among the ones that carry its name: made of
     * where it comes from, and looked up, never opened.
     */
    protected function id(string $origin, string $source): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($source)), '-');

        return substr($origin.($slug === '' ? '' : '.'.$slug), 0, 160);
    }

    protected function shownPath(string $file, string $origin, string $source, string $within): string
    {
        $root = rtrim($this->config->projectRoot(), '/\\').'/';
        $file = str_replace('\\', '/', $file);

        if ($origin === self::PACKAGED) {
            return 'larapilot/resources/boost/skills/'.basename(dirname($file)).'/'.basename($file);
        }

        if (str_starts_with($file, $root)) {
            return substr($file, strlen($root));
        }

        // A package linked from elsewhere on the machine: said from its own
        // folder on, not with the path of this machine.
        return $source.'/'.ltrim(substr($file, strlen(rtrim(str_replace('\\', '/', $within), '/'))), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function manifest(string $file): array
    {
        $path = rtrim($this->config->projectRoot(), '/\\').'/'.$file;

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode($this->read($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The packages the project asks for itself, in lower case.
     *
     * @param  list<string>  $sections
     * @return list<string>
     */
    protected function required(string $file, array $sections): array
    {
        $manifest = $this->manifest($file);
        $names = [];

        foreach ($sections as $section) {
            foreach (array_keys(is_array($manifest[$section] ?? null) ? $manifest[$section] : []) as $name) {
                $names[] = strtolower((string) $name);
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    protected function strings(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''))
            : [];
    }
}
