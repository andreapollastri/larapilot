<?php

declare(strict_types=1);

namespace Larapilot\Services;

/**
 * Discover user-authored Boost skills under `.larapilot/skills/`.
 */
class CustomSkillService
{
    public function __construct(
        protected ConfigService $config,
    ) {}

    public function directory(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['custom_skills'] ?? '.larapilot/skills/');
    }

    /**
     * @return list<array{name: string, path: string, description: string|null, trigger: string|null}>
     */
    public function list(): array
    {
        $root = $this->directory();

        if (! is_dir($root)) {
            return [];
        }

        $skills = [];

        foreach (glob($root.'/*/SKILL.md') ?: [] as $skillFile) {
            $meta = $this->parseFrontMatter((string) file_get_contents($skillFile));
            $folder = basename(dirname($skillFile));

            $skills[] = [
                'name' => is_string($meta['name'] ?? null) && $meta['name'] !== ''
                    ? (string) $meta['name']
                    : $folder,
                'path' => $skillFile,
                'description' => is_string($meta['description'] ?? null) ? $meta['description'] : null,
                'trigger' => is_string($meta['name'] ?? null) ? '/'.(string) $meta['name'] : '/'.$folder,
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
}
