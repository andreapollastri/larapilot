<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\SpecCode;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class MockupService
{
    public function __construct(
        protected ConfigService $config,
        protected SpecService $specs,
    ) {}

    /**
     * Every mockup folder under the configured mockups root, joined with
     * backlog titles when the folder name matches a spec code.
     *
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $items = [];
        $screenCount = 0;
        $root = $this->mockupsRoot();

        if ($root !== null) {
            $specsByCode = [];

            foreach ($this->specs->allSpecs() as $spec) {
                if (! is_array($spec)) {
                    continue;
                }

                $code = (string) ($spec['code'] ?? '');

                if ($code !== '') {
                    $specsByCode[$code] = $spec;
                }
            }

            foreach (scandir($root) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || ! SpecCode::isValid($entry)) {
                    continue;
                }

                if (! is_dir($root.DIRECTORY_SEPARATOR.$entry)) {
                    continue;
                }

                $packed = $this->pack($entry);

                if ($packed['screens'] === []) {
                    continue;
                }

                $screenCount += count($packed['screens']);
                $spec = $specsByCode[$entry] ?? null;

                $items[] = array_merge($packed, [
                    'code' => $entry,
                    'title' => is_array($spec) ? (string) ($spec['title'] ?? $entry) : $entry,
                    'priority' => is_array($spec) ? (string) ($spec['priority'] ?? '') : '',
                    'status' => is_array($spec) ? (string) ($spec['status'] ?? '') : '',
                    'points' => is_array($spec) ? (int) ($spec['points'] ?? 0) : 0,
                    'has_spec' => is_array($spec),
                    'path' => $this->relativeMockupPath($entry),
                    'browsable' => $this->config->mockupsBrowsable(),
                ]);
            }
        }

        return [
            'available' => $items !== [],
            'spec_count' => count($items),
            'screen_count' => $screenCount,
            'path' => $this->relativeMockupsRoot(),
            'browsable' => $this->config->mockupsBrowsable(),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(string $code): array
    {
        SpecCode::ensure($code);

        $packed = $this->pack($code);

        return [
            'available' => $packed['screens'] !== [],
            'path' => $this->relativeMockupPath($code),
            'screen_count' => count($packed['screens']),
            'entry' => $packed['entry'],
            'entry_url' => $packed['entry_url'],
            'browsable' => $this->config->mockupsBrowsable(),
            'screens' => $packed['screens'],
            'styles' => $packed['styles'],
            'chosen_style' => $packed['chosen_style'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forSpec(string $code): ?array
    {
        SpecCode::ensure($code);

        $packed = $this->pack($code);

        if ($packed['screens'] === []) {
            return null;
        }

        return [
            'path' => $this->relativeMockupPath($code),
            'entry' => $packed['entry'],
            'entry_url' => $packed['entry_url'],
            'browsable' => $this->config->mockupsBrowsable(),
            'screens' => $packed['screens'],
            'styles' => $packed['styles'],
            'chosen_style' => $packed['chosen_style'],
        ];
    }

    /**
     * Record which style version implementation should follow.
     *
     * @return array{chosen: string, styles: list<array{id: string, label: string}>}
     */
    public function chooseStyle(string $code, string $styleId): array
    {
        SpecCode::ensure($code);

        if (preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', $styleId) !== 1 || $styleId === 'current') {
            throw new \InvalidArgumentException('Style id must be a lowercase slug such as filament or nordic-minimal.');
        }

        $directory = $this->absoluteMockupDirectory($code);

        if ($directory === null) {
            throw new \InvalidArgumentException('No mockup folder for '.$code.'.');
        }

        $versions = $this->styleVersions($code, []);
        $match = null;

        foreach ($versions as $version) {
            if ($version['id'] === $styleId) {
                $match = $version;
                break;
            }
        }

        if ($match === null) {
            throw new \InvalidArgumentException('Style "'.$styleId.'" is not a version under mockups/'.$code.'/styles/.');
        }

        $rows = [];

        foreach ($versions as $version) {
            if ($version['id'] === 'current') {
                continue;
            }

            $rows[] = [
                'id' => $version['id'],
                'label' => $version['label'],
            ];
        }

        $payload = [
            'chosen' => $styleId,
            'styles' => $rows,
        ];

        AtomicFile::write(
            $directory.DIRECTORY_SEPARATOR.'styles.yaml',
            Yaml::dump($payload, 4, 2)
        );

        return $payload;
    }

    /**
     * Active screens plus every style version. Root HTML stays the single
     * version when styles/ is absent, so older mockups keep their paths.
     *
     * @return array{
     *     entry: ?string,
     *     entry_url: ?string,
     *     screens: list<array{file: string, label: string, url: ?string}>,
     *     styles: list<array<string, mixed>>,
     *     chosen_style: ?string
     * }
     */
    protected function pack(string $code): array
    {
        $rootScreens = $this->discoverScreens($code);
        $versions = $this->styleVersions($code, $rootScreens);
        $active = $rootScreens;
        $chosen = null;

        foreach ($versions as $version) {
            if ($version['chosen']) {
                $chosen = $version['id'];
                $active = $version['files'];
                break;
            }
        }

        if ($chosen === null && $versions !== []) {
            $active = $versions[0]['files'];
        }

        $entry = $this->entryScreen($active);

        return [
            'entry' => $entry,
            'entry_url' => $this->screenUrl($code, $entry),
            'screens' => $this->mapScreens($code, $active),
            'styles' => array_map(function (array $version) use ($code): array {
                $styleEntry = $this->entryScreen($version['files']);

                return [
                    'id' => $version['id'],
                    'label' => $version['label'],
                    'chosen' => $version['chosen'],
                    'entry' => $styleEntry,
                    'entry_url' => $this->screenUrl($code, $styleEntry),
                    'screens' => $this->mapScreens($code, $version['files']),
                ];
            }, $versions),
            'chosen_style' => $chosen,
        ];
    }

    /**
     * @param  list<string>  $rootScreens
     * @return list<array{id: string, label: string, chosen: bool, files: list<string>}>
     */
    protected function styleVersions(string $code, array $rootScreens): array
    {
        $directory = $this->absoluteMockupDirectory($code);

        if ($directory === null) {
            return [];
        }

        $stylesDir = $directory.DIRECTORY_SEPARATOR.'styles';

        if (! is_dir($stylesDir)) {
            return [];
        }

        $manifest = $this->readStyleManifest($directory);
        $labels = [];

        foreach ($manifest['styles'] as $row) {
            $labels[$row['id']] = $row['label'];
        }

        $versions = [];

        if ($rootScreens !== []) {
            $versions[] = [
                'id' => 'current',
                'label' => 'Current',
                'chosen' => false,
                'files' => $rootScreens,
            ];
        }

        foreach (scandir($stylesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', $entry) !== 1) {
                continue;
            }

            $path = $stylesDir.DIRECTORY_SEPARATOR.$entry;

            if (! is_dir($path)) {
                continue;
            }

            $files = array_map(
                static fn (string $file): string => 'styles/'.$entry.'/'.$file,
                $this->screensIn($path)
            );

            if ($files === []) {
                continue;
            }

            $versions[] = [
                'id' => $entry,
                'label' => $labels[$entry] ?? $this->screenLabel($entry),
                'chosen' => $manifest['chosen'] === $entry,
                'files' => $files,
            ];
        }

        $current = array_values(array_filter($versions, static fn (array $version): bool => $version['id'] === 'current'));
        $rest = array_values(array_filter($versions, static fn (array $version): bool => $version['id'] !== 'current'));
        usort($rest, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return array_merge($current, $rest);
    }

    /**
     * @return array{chosen: ?string, styles: list<array{id: string, label: string}>}
     */
    protected function readStyleManifest(string $directory): array
    {
        $path = $directory.DIRECTORY_SEPARATOR.'styles.yaml';
        $empty = ['chosen' => null, 'styles' => []];

        if (! is_file($path)) {
            return $empty;
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            return $empty;
        }

        $styles = [];

        foreach ($parsed['styles'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (string) ($row['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $styles[] = [
                'id' => $id,
                'label' => trim((string) ($row['label'] ?? '')) ?: $this->screenLabel($id),
            ];
        }

        $chosen = $parsed['chosen'] ?? null;

        return [
            'chosen' => is_string($chosen) && $chosen !== '' ? $chosen : null,
            'styles' => $styles,
        ];
    }

    /**
     * @param  list<string>  $files
     * @return list<array{file: string, label: string, url: ?string}>
     */
    protected function mapScreens(string $code, array $files): array
    {
        return array_map(
            fn (string $file): array => [
                'file' => $file,
                'label' => $this->screenLabel($file),
                'url' => $this->screenUrl($code, $file),
            ],
            $files
        );
    }

    /**
     * @return list<string>
     */
    protected function screensIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $finder = (new Finder)
            ->files()
            ->in($directory)
            ->name('*.html')
            ->name('*.htm')
            ->exclude('styles')
            ->sortByName();

        $screens = [];

        foreach ($finder as $file) {
            $screens[] = str_replace('\\', '/', $file->getRelativePathname());
        }

        return $screens;
    }

    /**
     * @return list<string>
     */
    protected function discoverScreens(string $code): array
    {
        $directory = $this->absoluteMockupDirectory($code);

        if ($directory === null) {
            return [];
        }

        return $this->screensIn($directory);
    }

    /**
     * @param  list<string>  $screens
     */
    protected function entryScreen(array $screens): ?string
    {
        if ($screens === []) {
            return null;
        }

        foreach ($screens as $screen) {
            if (strtolower(basename($screen)) === 'index.html') {
                return $screen;
            }
        }

        return $screens[0];
    }

    protected function screenUrl(string $code, ?string $file): ?string
    {
        if ($file === null || ! $this->config->mockupsBrowsable()) {
            return null;
        }

        if (! $this->routeRegistered()) {
            return null;
        }

        $parameters = ['spec' => $code];

        if ($file !== 'index.html') {
            $parameters['path'] = $file;
        }

        return route('larapilot.mockups.show', $parameters, absolute: false);
    }

    protected function screenLabel(string $file): string
    {
        $basename = pathinfo($file, PATHINFO_FILENAME);
        $label = str_replace(['-', '_'], ' ', $basename);

        return ucwords($label);
    }

    protected function relativeMockupPath(string $code): string
    {
        return $this->relativeMockupsRoot().'/'.$code.'/';
    }

    protected function relativeMockupsRoot(): string
    {
        $config = $this->config->resolve();

        return trim((string) ($config['paths']['mockups'] ?? '.larapilot/mockups/'), '/');
    }

    protected function mockupsRoot(): ?string
    {
        $root = rtrim($this->config->absolutePath($this->relativeMockupsRoot()), DIRECTORY_SEPARATOR);

        if (! is_dir($root)) {
            return null;
        }

        $real = realpath($root);

        return $real === false ? null : $real;
    }

    protected function absoluteMockupDirectory(string $code): ?string
    {
        $config = $this->config->resolve();
        $root = rtrim($this->config->absolutePath($config['paths']['mockups'] ?? '.larapilot/mockups/'), DIRECTORY_SEPARATOR);
        $directory = $root.DIRECTORY_SEPARATOR.$code;

        if (! is_dir($directory)) {
            return null;
        }

        $realRoot = realpath($root);
        $realDirectory = realpath($directory);

        if ($realRoot === false || $realDirectory === false) {
            return null;
        }

        if (! str_starts_with($realDirectory, $realRoot.DIRECTORY_SEPARATOR) && $realDirectory !== $realRoot) {
            return null;
        }

        return $realDirectory;
    }

    protected function routeRegistered(): bool
    {
        return app('router')->has('larapilot.mockups.show');
    }
}
