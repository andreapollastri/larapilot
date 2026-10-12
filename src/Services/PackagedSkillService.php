<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;

/**
 * The `/larapilot-*` skills the package ships, and the copies of them the
 * agents read.
 *
 * Boost 2 publishes them on `boost:update`. Boost 1 has no skills at all —
 * its 1.x line will not get them — so on Laravel 10 and PHP 8.1 the
 * package copies them itself, into the folders of the agents `boost.json`
 * names and the agent folders already in the project, as it does for the
 * custom skills. Either way `check()` tells a copy that is missing or no
 * longer the text of the package.
 */
class PackagedSkillService
{
    /**
     * Where each agent Boost knows reads its skills, by the name `boost.json`
     * gives it (Boost 1 and Boost 2 names). `config('boost.agents.{name}.skills_path')`
     * wins over these.
     *
     * @var array<string, string>
     */
    public const AGENT_SKILL_FOLDERS = [
        'claude_code' => '.claude/skills',
        'cursor' => '.cursor/skills',
        'copilot' => '.github/skills',
        'vscode' => '.github/skills',
        'codex' => '.agents/skills',
        'amp' => '.agents/skills',
        'opencode' => '.agents/skills',
        'zed' => '.agents/skills',
        'antigravity' => '.agents/skills',
        'gemini' => '.gemini/skills',
        'junie' => '.junie/skills',
        'phpstorm' => '.junie/skills',
        'kiro' => '.kiro/skills',
        'factory' => '.factory/skills',
        'grok_build' => '.grok/skills',
        'pi' => '.pi/skills',
    ];

    public const PREFIX = 'larapilot-';

    /** @var array<string, string>|null name => SKILL.md text, read once per process */
    protected ?array $packaged = null;

    public function __construct(
        protected ConfigService $config,
    ) {}

    public function directory(): string
    {
        return dirname(__DIR__, 2).'/resources/boost/skills';
    }

    /**
     * Whether the installed Boost publishes package skills (Boost 2).
     */
    public function boostPublishesSkills(): bool
    {
        return interface_exists('Laravel\\Boost\\Contracts\\SupportsSkills')
            || class_exists('Laravel\\Boost\\Install\\SkillWriter');
    }

    public function boostInstalled(): bool
    {
        return class_exists('Laravel\\Boost\\BoostServiceProvider');
    }

    /**
     * The names of the packaged skills.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->packaged());
    }

    /**
     * Copy every packaged skill into the agent folders, rewriting a copy
     * only when its text differs, and remove a `larapilot-*` folder the
     * package no longer ships. Meant for a Boost that does not publish
     * skills: with Boost 2 the copies are Boost's.
     *
     * @return array{folders: list<string>, written: list<string>, unchanged: int, removed: list<string>}
     */
    public function publish(): array
    {
        $root = $this->root();
        $packaged = $this->packaged();
        $result = ['folders' => [], 'written' => [], 'unchanged' => 0, 'removed' => []];

        foreach ($this->targets(true) as $folder) {
            $result['folders'][] = $folder;

            foreach ($packaged as $name => $text) {
                $file = $root.'/'.$folder.'/'.$name.'/SKILL.md';

                if (is_file($file) && (string) file_get_contents($file) === $text) {
                    $result['unchanged']++;

                    continue;
                }

                if (! is_dir(dirname($file)) && ! @mkdir(dirname($file), 0755, true) && ! is_dir(dirname($file))) {
                    continue;
                }

                AtomicFile::write($file, $text);
                $result['written'][] = $folder.'/'.$name;
            }

            foreach (glob($root.'/'.$folder.'/'.self::PREFIX.'*', GLOB_ONLYDIR) ?: [] as $stale) {
                if (! isset($packaged[basename($stale)]) && ! is_link($stale)) {
                    $this->remove($stale);
                    $result['removed'][] = $folder.'/'.basename($stale);
                }
            }
        }

        return $result;
    }

    /**
     * Whether the agents hold the packaged skills as the package wrote them.
     * A project that keeps its own version in `.ai/skills/{name}` is
     * compared with that one: it is what Boost publishes.
     *
     * @return array{publisher: string, folders: list<string>, missing: list<string>, differs: list<string>, ok: bool}
     */
    public function check(): array
    {
        $root = $this->root();
        $boost = $this->boostPublishesSkills();
        $missing = [];
        $differs = [];
        $folders = $this->targets(false);

        foreach ($folders as $folder) {
            foreach ($this->packaged() as $name => $text) {
                $file = $root.'/'.$folder.'/'.$name.'/SKILL.md';

                if (! is_file($file)) {
                    $missing[] = $folder.'/'.$name;

                    continue;
                }

                $expected = $text;

                if ($boost && is_file($own = $root.'/.ai/skills/'.$name.'/SKILL.md')) {
                    $expected = (string) file_get_contents($own);
                }

                if ((string) file_get_contents($file) !== $expected) {
                    $differs[] = $folder.'/'.$name;
                }
            }
        }

        return [
            'publisher' => $boost ? 'boost' : 'larapilot',
            'folders' => $folders,
            'missing' => $missing,
            'differs' => $differs,
            'ok' => $missing === [] && $differs === [],
        ];
    }

    /**
     * The agent folders the skills belong in, relative to the root: those
     * of the agents `boost.json` names, and — when publishing — the agent
     * folders already in the project. Without `boost.json`, a check reads
     * only the folders that already hold a `larapilot-*` skill.
     *
     * @return list<string>
     */
    public function targets(bool $publishing): array
    {
        $root = $this->root();
        $folders = [];
        $boost = $this->boostJson();

        foreach (is_array($boost['agents'] ?? null) ? $boost['agents'] : [] as $agent) {
            if (! is_string($agent)) {
                continue;
            }

            $configured = config('boost.agents.'.$agent.'.skills_path');
            $folder = is_string($configured) && $configured !== '' ? $configured : (self::AGENT_SKILL_FOLDERS[$agent] ?? null);

            if (is_string($folder) && ! str_contains($folder, '..') && ! str_starts_with($folder, '/')) {
                $folders[trim(str_replace('\\', '/', $folder), '/')] = true;
            }
        }

        foreach (array_unique(self::AGENT_SKILL_FOLDERS) as $folder) {
            if (! is_dir($root.'/'.$folder)) {
                continue;
            }

            if ($publishing || glob($root.'/'.$folder.'/'.self::PREFIX.'*', GLOB_ONLYDIR) !== []) {
                $folders[$folder] = true;
            }
        }

        return array_keys($folders);
    }

    /**
     * Boost reads third-party guidelines only from the packages `boost.json`
     * lists, once a list is there: `guidelines` on Boost 1, `packages` on
     * Boost 2. Add this package to that list when it is missing, so the
     * Larapilot guideline reaches `CLAUDE.md` / `AGENTS.md`. Nothing is
     * written when there is no `boost.json` or no list yet.
     *
     * @return string|null The key the package was added to.
     */
    public function listInBoostJson(): ?string
    {
        $path = $this->root().'/boost.json';
        $boost = $this->boostJson();
        $key = $this->boostPublishesSkills() ? 'packages' : 'guidelines';

        if (! is_array($boost[$key] ?? null) || ! array_is_list($boost[$key]) || in_array('andreapollastri/larapilot', $boost[$key], true)) {
            return null;
        }

        $boost[$key][] = 'andreapollastri/larapilot';
        AtomicFile::write($path, json_encode($boost, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    protected function boostJson(): array
    {
        $path = $this->root().'/boost.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    protected function packaged(): array
    {
        if ($this->packaged !== null) {
            return $this->packaged;
        }

        $this->packaged = [];

        foreach (glob($this->directory().'/'.self::PREFIX.'*/SKILL.md') ?: [] as $file) {
            $this->packaged[basename(dirname($file))] = (string) file_get_contents($file);
        }

        ksort($this->packaged);

        return $this->packaged;
    }

    protected function root(): string
    {
        return rtrim(str_replace('\\', '/', $this->config->projectRoot()), '/');
    }

    protected function remove(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            is_dir($path) && ! is_link($path) ? $this->remove($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
