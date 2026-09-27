<?php

declare(strict_types=1);

namespace Larapilot\Services;

use FilesystemIterator;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\Markdown;
use Larapilot\Support\MimeTypes;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Browse and manage the material folders the skills read from `.larapilot/`:
 * brand, client materials, design systems, legacy snapshots, custom skills.
 *
 * Every path is relative to one of those roots. The parent directory is
 * resolved with realpath and checked against the root before anything is
 * read or written, and the leaf is handled by name without following it, so
 * a symlink can neither be walked out of the root nor take its target down
 * with it.
 */
class FileManagerService
{
    /**
     * Housekeeping files that never show in a listing or count as content.
     *
     * @var list<string>
     */
    protected const HIDDEN = ['.gitkeep', '.DS_Store', 'Thumbs.db'];

    /**
     * Directories dropped from an uploaded folder: a nested repository inside
     * `.larapilot/` would turn into an embedded git repo on the next commit.
     *
     * @var list<string>
     */
    protected const SKIPPED_DIRECTORIES = ['.git', '.svn', '.hg'];

    /**
     * Design systems shipped with the package. `larapilot:update` rewrites
     * them, so edits made here do not survive an update.
     *
     * @var list<string>
     */
    protected const PACKAGED_DESIGN_SYSTEMS = ['filament', 'starter-kit', 'bootstrap-5', 'tailwind', 'adminlte'];

    /**
     * @var list<string>
     */
    protected const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp'];

    /**
     * @var list<string>
     */
    protected const MARKDOWN_EXTENSIONS = ['md', 'markdown', 'mdx'];

    /**
     * @var list<string>
     */
    protected const TEXT_EXTENSIONS = [
        'txt', 'text', 'log', 'csv', 'tsv', 'json', 'yaml', 'yml', 'xml', 'html', 'htm', 'css', 'scss', 'less',
        'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'vue', 'svelte', 'php', 'blade', 'py', 'rb', 'go', 'java', 'sql',
        'sh', 'bash', 'zsh', 'env', 'ini', 'conf', 'toml', 'lock', 'gitignore', 'editorconfig', 'htaccess',
    ];

    protected const TREE_LIMIT = 400;

    protected const TREE_DEPTH = 12;

    protected const SCAN_LIMIT = 20000;

    protected const PREVIEW_BYTES = 262144;

    protected const MAX_SEGMENTS = 32;

    public function __construct(
        protected ConfigService $config,
    ) {}

    /**
     * @return array<string, array{key: string, label: string, description: string, used_by: string, path: string, absolute: string}>
     */
    public function roots(): array
    {
        $paths = $this->config->resolve()['paths'] ?? [];
        $paths = is_array($paths) ? $paths : [];

        $definitions = [
            'brand' => [
                'label' => 'Brand',
                'description' => 'Logo, palette, typography, and the brand guide.',
                'used_by' => 'Design and mockups',
                'path' => $paths['brand'] ?? '.larapilot/brand/',
            ],
            'client-materials' => [
                'label' => 'Client materials',
                'description' => 'Briefs, analyses, and documents supplied by the client.',
                'used_by' => 'Inception and specs',
                'path' => $paths['client_materials'] ?? '.larapilot/client-materials/',
            ],
            'design-systems' => [
                'label' => 'Design systems',
                'description' => 'Visual references and tokens the mockups are built on.',
                'used_by' => 'Design and mockups',
                'path' => $paths['design_systems'] ?? '.larapilot/design-systems/',
            ],
            'legacy' => [
                'label' => 'Legacy',
                'description' => 'Snapshots of the old system to port or migrate.',
                'used_by' => 'Adopt, inception, and parity checks',
                'path' => $paths['legacy'] ?? '.larapilot/legacy/',
            ],
            'skills' => [
                'label' => 'Skills',
                'description' => 'Your custom skills, one folder per slash command.',
                'used_by' => 'Boost slash commands',
                'path' => $paths['custom_skills'] ?? '.larapilot/skills/',
            ],
        ];

        $roots = [];

        foreach ($definitions as $key => $definition) {
            $absolute = rtrim($this->config->absolutePath((string) $definition['path']), '/\\');

            $roots[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'used_by' => $definition['used_by'],
                'path' => rtrim(str_replace('\\', '/', $this->config->relativePath($absolute)), '/').'/',
                'absolute' => $absolute,
            ];
        }

        return $roots;
    }

    /**
     * @return list<string>
     */
    public function rootKeys(): array
    {
        return array_keys($this->roots());
    }

    /**
     * @return array{key: string, label: string, description: string, used_by: string, path: string, absolute: string}|null
     */
    public function root(string $key): ?array
    {
        return $this->roots()[$key] ?? null;
    }

    /**
     * The five folders with what they hold, for the landing page.
     *
     * @return list<array<string, mixed>>
     */
    public function summary(): array
    {
        $summary = [];

        foreach ($this->roots() as $root) {
            $summary[] = array_merge($root, $this->measure($root['absolute']));
        }

        return $summary;
    }

    /**
     * What sits at a path: a directory listing or a file preview. Null when
     * the root or the path does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function browse(string $rootKey, string $path = ''): ?array
    {
        $root = $this->root($rootKey);

        if ($root === null) {
            return null;
        }

        try {
            $relative = $this->normalize($path);
        } catch (InvalidArgumentException) {
            return null;
        }

        $absolute = $this->locate($root, $relative);

        // A folder nobody has created yet reads as empty. Looking at it must
        // not write to the project, so it is only made by the first upload.
        if ($absolute === null && ($relative !== '' || file_exists($root['absolute']))) {
            return null;
        }

        $isDirectory = $absolute === null || is_dir($absolute);

        $data = [
            'root' => $root,
            'path' => $relative,
            'name' => $relative === '' ? $root['label'] : basename($relative),
            'parent' => $relative === '' ? null : $this->parentOf($relative),
            'breadcrumbs' => $this->breadcrumbs($relative),
            'display_path' => $root['path'].$relative.($isDirectory && $relative !== '' ? '/' : ''),
            'is_directory' => $isDirectory,
            'tree' => $this->tree($root, $isDirectory ? $relative : $this->parentOf($relative)),
            'limits' => $this->limits(),
        ];

        if ($absolute === null || $isDirectory) {
            $entries = $this->entries($root, $relative);

            return array_merge($data, [
                'entries' => $entries,
                'directory' => $relative,
                'totals' => [
                    'folders' => count(array_filter($entries, static fn (array $entry): bool => $entry['type'] === 'directory')),
                    'files' => count(array_filter($entries, static fn (array $entry): bool => $entry['type'] === 'file')),
                    'bytes' => array_sum(array_map(static fn (array $entry): int => $entry['type'] === 'file' ? (int) $entry['size'] : 0, $entries)),
                ],
            ]);
        }

        return array_merge($data, [
            'entries' => [],
            'directory' => $this->parentOf($relative),
            'file' => $this->describe($root, $relative, $absolute),
            'preview' => $this->preview($absolute),
        ]);
    }

    /**
     * Absolute path of a regular file inside a root, for the raw route.
     */
    public function file(string $rootKey, string $path): ?string
    {
        $root = $this->root($rootKey);

        if ($root === null) {
            return null;
        }

        try {
            $relative = $this->normalize($path);
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($relative === '') {
            return null;
        }

        $absolute = $this->locate($root, $relative);

        return $absolute !== null && is_file($absolute) ? $absolute : null;
    }

    /**
     * Dashboard URL of a folder or file (`browse`) or of its bytes (`raw`).
     * Each segment is encoded on its own, so a name with `#`, `?`, or `%`
     * in it stays one path on every supported Laravel version.
     */
    public function url(string $rootKey, string $path = '', string $route = 'browse'): string
    {
        $name = 'larapilot.dashboard.files.'.$route;

        if ($path === '' && $route === 'browse') {
            return route($name, ['root' => $rootKey]);
        }

        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return str_replace('__PATH__', $encoded, route($name, ['root' => $rootKey, 'path' => '__PATH__']));
    }

    /**
     * Content type and disposition the raw route may answer with. Only
     * formats a browser renders without running anything are served inline;
     * markup and scripts always go out as a download.
     *
     * @return array{type: string, inline: bool}
     */
    public function delivery(string $absolute): array
    {
        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        $inline = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
        ];

        if (isset($inline[$extension])) {
            return ['type' => $inline[$extension], 'inline' => true];
        }

        return ['type' => MimeTypes::forPath($absolute), 'inline' => false];
    }

    /**
     * @return array{path: string, name: string}
     */
    public function createFolder(string $rootKey, string $directory, string $name): array
    {
        $root = $this->requireRoot($rootKey);
        $relative = $this->normalize($directory);
        $name = $this->assertName($name);
        $parent = $this->requireDirectory($root, $relative);
        $target = $parent.DIRECTORY_SEPARATOR.$name;

        if (file_exists($target) || is_link($target)) {
            throw new InvalidArgumentException("“{$name}” already exists in this folder.");
        }

        if (! @mkdir($target, 0755) && ! is_dir($target)) {
            throw new InvalidArgumentException("Could not create “{$name}”. Check the folder permissions.");
        }

        // Git does not track an empty directory; the keep file makes the new
        // folder part of the next commit, like every folder install creates.
        AtomicFile::write($target.DIRECTORY_SEPARATOR.'.gitkeep', '');

        return ['path' => $this->join($relative, $name), 'name' => $name];
    }

    /**
     * @return array{path: string, name: string, from: string, is_directory: bool}
     */
    public function rename(string $rootKey, string $path, string $name): array
    {
        $root = $this->requireRoot($rootKey);
        $relative = $this->normalize($path);

        if ($relative === '') {
            throw new InvalidArgumentException('The folder itself cannot be renamed — the skills look for it by name.');
        }

        $name = $this->assertName($name);
        $source = $this->requireEntry($root, $relative);
        $current = basename($relative);

        if ($name === $current) {
            throw new InvalidArgumentException('That is already the name.');
        }

        $target = dirname($source).DIRECTORY_SEPARATOR.$name;
        $caseOnly = strcasecmp($name, $current) === 0;

        if (! $caseOnly && (file_exists($target) || is_link($target))) {
            throw new InvalidArgumentException("“{$name}” already exists in this folder.");
        }

        $isDirectory = is_dir($source) && ! is_link($source);

        if (! @rename($source, $target)) {
            throw new InvalidArgumentException("Could not rename “{$current}”. Check the folder permissions.");
        }

        return [
            'path' => $this->join($this->parentOf($relative), $name),
            'name' => $name,
            'from' => $current,
            'is_directory' => $isDirectory,
        ];
    }

    /**
     * @return array{name: string, parent: string, is_directory: bool, removed: int}
     */
    public function delete(string $rootKey, string $path): array
    {
        $root = $this->requireRoot($rootKey);
        $relative = $this->normalize($path);

        if ($relative === '') {
            throw new InvalidArgumentException('The folder itself cannot be deleted — the skills look for it by name.');
        }

        $target = $this->requireEntry($root, $relative);
        $isDirectory = is_dir($target) && ! is_link($target);
        $removed = $isDirectory ? $this->removeDirectory($target) : (int) @unlink($target);

        if (file_exists($target) || is_link($target)) {
            throw new InvalidArgumentException('Could not delete “'.basename($relative).'”. Check the folder permissions.');
        }

        return [
            'name' => basename($relative),
            'parent' => $this->parentOf($relative),
            'is_directory' => $isDirectory,
            'removed' => $removed,
        ];
    }

    /**
     * Store uploaded files under a directory, rebuilding the folder tree each
     * one came from. `$relativePaths` runs parallel to `$files`: the path of
     * the file inside the uploaded folder, as the browser reported it.
     *
     * @param  array<int, mixed>  $files
     * @param  array<int, mixed>  $relativePaths
     * @return array{stored: list<string>, skipped: list<array{path: string, reason: string}>}
     */
    public function upload(string $rootKey, string $directory, array $files, array $relativePaths = [], bool $replace = false): array
    {
        $root = $this->requireRoot($rootKey);
        $relative = $this->normalize($directory);
        $base = $this->requireDirectory($root, $relative);
        $limit = $this->limits()['file_bytes'];

        $stored = [];
        $skipped = [];

        foreach (array_values($files) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $given = $relativePaths[$index] ?? null;
            $label = is_string($given) && trim($given) !== '' ? $given : $file->getClientOriginalName();

            try {
                $segments = $this->uploadSegments($label);
            } catch (InvalidArgumentException $e) {
                $skipped[] = ['path' => $label, 'reason' => $e->getMessage()];

                continue;
            }

            if ($segments === null) {
                continue;
            }

            $display = implode('/', $segments);

            if (! $file->isValid()) {
                $skipped[] = ['path' => $display, 'reason' => $this->uploadError($file)];

                continue;
            }

            if ($limit > 0 && (int) $file->getSize() > $limit) {
                $skipped[] = ['path' => $display, 'reason' => 'Larger than the '.$this->formatBytes($limit).' upload limit.'];

                continue;
            }

            $name = (string) array_pop($segments);
            $folder = $base;
            $blocked = null;

            foreach ($segments as $segment) {
                $folder .= DIRECTORY_SEPARATOR.$segment;

                if (is_link($folder) || (file_exists($folder) && ! is_dir($folder))) {
                    $blocked = "“{$segment}” exists and is not a folder.";
                    break;
                }

                if (! is_dir($folder) && ! @mkdir($folder, 0755) && ! is_dir($folder)) {
                    $blocked = "Could not create the folder “{$segment}”.";
                    break;
                }
            }

            if ($blocked !== null) {
                $skipped[] = ['path' => $display, 'reason' => $blocked];

                continue;
            }

            $target = $folder.DIRECTORY_SEPARATOR.$name;

            if (is_link($target) || is_dir($target)) {
                $skipped[] = ['path' => $display, 'reason' => 'A folder or link with this name is already there.'];

                continue;
            }

            if (is_file($target) && ! $replace) {
                $skipped[] = ['path' => $display, 'reason' => 'Already exists. Turn on “Replace existing files” to overwrite it.'];

                continue;
            }

            try {
                $file->move($folder, $name);
            } catch (\Throwable) {
                $skipped[] = ['path' => $display, 'reason' => 'Could not be written. Check the folder permissions.'];

                continue;
            }

            $stored[] = $this->join($relative, $display);
        }

        return ['stored' => $stored, 'skipped' => $skipped];
    }

    /**
     * Upload ceilings the page needs to batch a folder: PHP refuses what
     * exceeds them before the request reaches the application.
     *
     * @return array{file_bytes: int, request_bytes: int, max_files: int, file_label: string}
     */
    public function limits(): array
    {
        $configured = max(0, (int) config('larapilot.file_manager.max_upload_kb', 51200)) * 1024;
        $perFile = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $perRequest = $this->iniBytes((string) ini_get('post_max_size'));

        $candidates = array_filter([$configured, $perFile, $perRequest], static fn (int $bytes): bool => $bytes > 0);
        $fileBytes = $candidates === [] ? 0 : min($candidates);

        return [
            'file_bytes' => $fileBytes,
            'request_bytes' => $perRequest,
            'max_files' => max(1, (int) ini_get('max_file_uploads') ?: 20),
            'file_label' => $fileBytes > 0 ? $this->formatBytes($fileBytes) : 'no limit',
        ];
    }

    public function formatBytes(int|float $bytes): string
    {
        $bytes = max(0, (float) $bytes);

        if ($bytes < 1024) {
            return ((int) $bytes).' B';
        }

        foreach (['KB', 'MB', 'GB'] as $unit) {
            $bytes /= 1024;

            if ($bytes < 1024 || $unit === 'GB') {
                return rtrim(rtrim(number_format($bytes, $bytes < 10 ? 1 : 0, '.', ','), '0'), '.').' '.$unit;
            }
        }

        return '0 B';
    }

    /**
     * Collapse a request path into clean segments. Refuses anything that
     * would step out of the root.
     */
    public function normalize(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('The path is not valid.');
        }

        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new InvalidArgumentException('The path may not leave the folder.');
            }

            $segments[] = $segment;
        }

        if (count($segments) > self::MAX_SEGMENTS) {
            throw new InvalidArgumentException('The path is nested too deeply.');
        }

        return implode('/', $segments);
    }

    /**
     * A single file or folder name that is safe on every platform the
     * repository may be cloned on.
     */
    public function assertName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            throw new InvalidArgumentException('Give it a name.');
        }

        if (strlen($name) > 200) {
            throw new InvalidArgumentException('That name is too long (200 characters at most).');
        }

        if (preg_match('/[\/\\\\:*?"<>|\x00-\x1F\x7F]/', $name) === 1) {
            throw new InvalidArgumentException('A name cannot contain / \\ : * ? " < > | or control characters.');
        }

        if (str_ends_with($name, '.') || str_ends_with($name, ' ')) {
            throw new InvalidArgumentException('A name cannot end with a dot or a space.');
        }

        if (in_array($name, self::HIDDEN, true) || in_array($name, self::SKIPPED_DIRECTORIES, true)) {
            throw new InvalidArgumentException("“{$name}” is reserved.");
        }

        return $name;
    }

    /**
     * Whether an entry at the top of a root is a custom skill folder — the
     * caller keeps the Boost registration in step when one changes.
     */
    public function isSkillFolder(string $rootKey, string $path): bool
    {
        return $rootKey === 'skills' && $path !== '' && ! str_contains($path, '/');
    }

    /**
     * @param  array{absolute: string}  $root
     */
    protected function ensureRoot(array $root): void
    {
        if (! is_dir($root['absolute'])) {
            @mkdir($root['absolute'], 0755, true);
        }
    }

    /**
     * @return array{key: string, label: string, description: string, used_by: string, path: string, absolute: string}
     */
    protected function requireRoot(string $key): array
    {
        $root = $this->root($key);

        if ($root === null) {
            throw new InvalidArgumentException('Unknown folder.');
        }

        $this->ensureRoot($root);

        return $root;
    }

    /**
     * Absolute path of an existing entry, or null. The parent is resolved and
     * confined to the root; the leaf keeps its own name so a link is seen as
     * a link.
     *
     * @param  array{absolute: string}  $root
     */
    protected function locate(array $root, string $relative): ?string
    {
        $base = realpath($root['absolute']);

        if ($base === false) {
            return null;
        }

        if ($relative === '') {
            return $base;
        }

        $parent = realpath($base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->parentOf($relative)));

        if ($parent === false || ! $this->inside($base, $parent)) {
            return null;
        }

        $entry = $parent.DIRECTORY_SEPARATOR.basename($relative);

        if (in_array(basename($relative), self::HIDDEN, true)) {
            return null;
        }

        if (is_link($entry)) {
            // A link is listed but never opened: its target may sit anywhere.
            return null;
        }

        return file_exists($entry) ? $entry : null;
    }

    /**
     * @param  array{absolute: string}  $root
     */
    protected function requireDirectory(array $root, string $relative): string
    {
        $absolute = $this->locate($root, $relative);

        if ($absolute === null || ! is_dir($absolute)) {
            throw new InvalidArgumentException('That folder no longer exists.');
        }

        return $absolute;
    }

    /**
     * Like locate(), but a symlink is a valid entry: it can be renamed or
     * removed as the link it is.
     *
     * @param  array{absolute: string}  $root
     */
    protected function requireEntry(array $root, string $relative): string
    {
        $base = realpath($root['absolute']);
        $parent = $base === false
            ? false
            : realpath($base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->parentOf($relative)));

        if ($base === false || $parent === false || ! $this->inside($base, $parent)) {
            throw new InvalidArgumentException('That item no longer exists.');
        }

        $entry = $parent.DIRECTORY_SEPARATOR.basename($relative);

        if (in_array(basename($relative), self::HIDDEN, true) || (! file_exists($entry) && ! is_link($entry))) {
            throw new InvalidArgumentException('That item no longer exists.');
        }

        return $entry;
    }

    protected function inside(string $base, string $path): bool
    {
        return $path === $base || str_starts_with($path, $base.DIRECTORY_SEPARATOR);
    }

    /**
     * @param  array{key: string, absolute: string}  $root
     * @return list<array<string, mixed>>
     */
    protected function entries(array $root, string $relative): array
    {
        $directory = $this->locate($root, $relative);

        if ($directory === null || ! is_dir($directory)) {
            return [];
        }

        $entries = [];

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..' || in_array($name, self::HIDDEN, true)) {
                continue;
            }

            $entries[] = $this->describe($root, $this->join($relative, $name), $directory.DIRECTORY_SEPARATOR.$name);
        }

        usort($entries, static function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }

            return strnatcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $entries;
    }

    /**
     * @param  array{key: string, absolute: string}  $root
     * @return array<string, mixed>
     */
    protected function describe(array $root, string $relative, string $absolute): array
    {
        $isLink = is_link($absolute);
        $isDirectory = ! $isLink && is_dir($absolute);
        $name = basename($relative);
        $extension = $isDirectory ? '' : strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $modified = @filemtime($absolute);

        return [
            'name' => $name,
            'path' => $relative,
            'type' => $isDirectory ? 'directory' : 'file',
            'link' => $isLink,
            'extension' => $extension,
            'kind' => $isLink ? 'link' : ($isDirectory ? 'directory' : $this->kind($name)),
            'size' => $isDirectory || $isLink ? 0 : (int) @filesize($absolute),
            'size_label' => $isDirectory || $isLink ? '' : $this->formatBytes((int) @filesize($absolute)),
            'items' => $isDirectory ? $this->countChildren($absolute) : 0,
            'modified' => $modified === false ? null : $modified,
            'packaged' => $root['key'] === 'design-systems'
                && ! str_contains($relative, '/')
                && $isDirectory
                && in_array($name, self::PACKAGED_DESIGN_SYSTEMS, true),
        ];
    }

    protected function countChildren(string $directory): int
    {
        $count = 0;

        foreach (scandir($directory) ?: [] as $name) {
            if ($name !== '.' && $name !== '..' && ! in_array($name, self::HIDDEN, true)) {
                $count++;
            }
        }

        return $count;
    }

    protected function kind(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => 'image',
            in_array($extension, self::MARKDOWN_EXTENSIONS, true) => 'markdown',
            $extension === 'pdf' => 'pdf',
            in_array($extension, ['zip', 'tar', 'gz', 'tgz', 'rar', '7z'], true) => 'archive',
            in_array($extension, self::TEXT_EXTENSIONS, true) => 'text',
            default => 'other',
        };
    }

    /**
     * @return array{kind: string, html: string|null, text: string|null, truncated: bool}
     */
    protected function preview(string $absolute): array
    {
        $kind = $this->kind(basename($absolute));
        $empty = ['kind' => $kind, 'html' => null, 'text' => null, 'truncated' => false];

        if (in_array($kind, ['image', 'pdf', 'archive'], true)) {
            return $empty;
        }

        $size = (int) @filesize($absolute);
        $handle = @fopen($absolute, 'rb');

        if ($handle === false) {
            return array_merge($empty, ['kind' => 'other']);
        }

        $content = $size === 0 ? '' : (string) fread($handle, self::PREVIEW_BYTES);
        fclose($handle);

        if (str_contains($content, "\0") || ($content !== '' && ! mb_check_encoding($content, 'UTF-8') && ! $this->cutMidCharacter($content))) {
            return array_merge($empty, ['kind' => 'other']);
        }

        $truncated = $size > self::PREVIEW_BYTES;

        if ($truncated) {
            // Drop a trailing multibyte character sliced in half by the cap.
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        if ($kind === 'markdown' && ! $truncated) {
            return ['kind' => 'markdown', 'html' => Markdown::toHtml($content), 'text' => $content, 'truncated' => false];
        }

        return ['kind' => 'text', 'html' => null, 'text' => $content, 'truncated' => $truncated];
    }

    /**
     * True when the only thing wrong with the bytes is a character cut at
     * the preview cap, not a binary payload.
     */
    protected function cutMidCharacter(string $content): bool
    {
        if (strlen($content) < self::PREVIEW_BYTES) {
            return false;
        }

        return mb_check_encoding(substr($content, 0, -4), 'UTF-8')
            || mb_check_encoding(substr($content, 0, -3), 'UTF-8')
            || mb_check_encoding(substr($content, 0, -2), 'UTF-8')
            || mb_check_encoding(substr($content, 0, -1), 'UTF-8');
    }

    /**
     * Folder tree of a root: directories only, with the branch leading to
     * the open folder marked so the page can unfold it.
     *
     * @param  array{absolute: string}  $root
     * @return array{nodes: list<array<string, mixed>>, truncated: bool}
     */
    protected function tree(array $root, string $active): array
    {
        $base = realpath($root['absolute']);

        if ($base === false) {
            return ['nodes' => [], 'truncated' => false];
        }

        $budget = self::TREE_LIMIT;
        $nodes = $this->branch($base, '', $active, 1, $budget);

        return ['nodes' => $nodes, 'truncated' => $budget <= 0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function branch(string $directory, string $relative, string $active, int $depth, int &$budget): array
    {
        $names = [];

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$name;

            if (is_dir($path) && ! is_link($path)) {
                $names[] = $name;
            }
        }

        natcasesort($names);
        $nodes = [];

        foreach ($names as $name) {
            if ($budget <= 0) {
                break;
            }

            $budget--;
            $path = $this->join($relative, $name);
            $onTrail = $active === $path || str_starts_with($active.'/', $path.'/');

            $nodes[] = [
                'name' => $name,
                'path' => $path,
                'active' => $active === $path,
                'open' => $onTrail,
                'children' => $depth < self::TREE_DEPTH
                    ? $this->branch($directory.DIRECTORY_SEPARATOR.$name, $path, $active, $depth + 1, $budget)
                    : [],
            ];
        }

        return $nodes;
    }

    /**
     * @return array{exists: bool, files: int, folders: int, bytes: int, size_label: string, truncated: bool}
     */
    protected function measure(string $directory): array
    {
        $result = ['exists' => is_dir($directory), 'files' => 0, 'folders' => 0, 'bytes' => 0, 'size_label' => '0 B', 'truncated' => false];

        if (! $result['exists']) {
            return $result;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $seen = 0;

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (++$seen > self::SCAN_LIMIT) {
                $result['truncated'] = true;
                break;
            }

            if (in_array($item->getFilename(), self::HIDDEN, true)) {
                continue;
            }

            if ($item->isLink()) {
                $result['files']++;

                continue;
            }

            if ($item->isDir()) {
                $result['folders']++;

                continue;
            }

            $result['files']++;
            $result['bytes'] += (int) $item->getSize();
        }

        $result['size_label'] = $this->formatBytes($result['bytes']);

        return $result;
    }

    /**
     * Remove a directory and everything under it. Links are unlinked, never
     * followed. Returns how many entries went.
     */
    protected function removeDirectory(string $directory): int
    {
        $removed = 0;

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$name;

            if (is_dir($path) && ! is_link($path)) {
                $removed += $this->removeDirectory($path);

                continue;
            }

            if (@unlink($path) && ! in_array($name, self::HIDDEN, true)) {
                $removed++;
            }
        }

        return @rmdir($directory) ? $removed + 1 : $removed;
    }

    /**
     * Path segments of an uploaded file, or null when it is housekeeping
     * that should be dropped without a word (`.DS_Store`, a nested `.git`).
     *
     * @return list<string>|null
     */
    protected function uploadSegments(string $label): ?array
    {
        $normalized = $this->normalize($label);

        if ($normalized === '') {
            throw new InvalidArgumentException('The file has no name.');
        }

        $segments = explode('/', $normalized);
        $name = (string) end($segments);

        if (in_array($name, self::HIDDEN, true)) {
            return null;
        }

        foreach ($segments as $index => $segment) {
            if (in_array($segment, self::SKIPPED_DIRECTORIES, true)) {
                return null;
            }

            $segments[$index] = $this->assertName($segment);
        }

        return $segments;
    }

    protected function uploadError(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Larger than the '.$this->limits()['file_label'].' upload limit.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE => 'No file arrived.',
            default => 'The server could not store the upload.',
        };
    }

    protected function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    protected function breadcrumbs(string $relative): array
    {
        $crumbs = [];
        $trail = '';

        foreach ($relative === '' ? [] : explode('/', $relative) as $segment) {
            $trail = $this->join($trail, $segment);
            $crumbs[] = ['name' => $segment, 'path' => $trail];
        }

        return $crumbs;
    }

    protected function parentOf(string $relative): string
    {
        $position = strrpos($relative, '/');

        return $position === false ? '' : substr($relative, 0, $position);
    }

    protected function join(string $directory, string $name): string
    {
        return $directory === '' ? $name : $directory.'/'.$name;
    }
}
