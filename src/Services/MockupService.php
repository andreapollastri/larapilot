<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\SpecCode;
use Symfony\Component\Finder\Finder;

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

                $screens = $this->discoverScreens($entry);

                if ($screens === []) {
                    continue;
                }

                $screenCount += count($screens);
                $spec = $specsByCode[$entry] ?? null;
                $entryScreen = $this->entryScreen($screens);

                $items[] = [
                    'code' => $entry,
                    'title' => is_array($spec) ? (string) ($spec['title'] ?? $entry) : $entry,
                    'priority' => is_array($spec) ? (string) ($spec['priority'] ?? '') : '',
                    'status' => is_array($spec) ? (string) ($spec['status'] ?? '') : '',
                    'points' => is_array($spec) ? (int) ($spec['points'] ?? 0) : 0,
                    'has_spec' => is_array($spec),
                    'path' => $this->relativeMockupPath($entry),
                    'entry' => $entryScreen,
                    'entry_url' => $this->screenUrl($entry, $entryScreen),
                    'browsable' => $this->config->mockupsBrowsable(),
                    'screens' => array_map(
                        fn (string $file): array => [
                            'file' => $file,
                            'label' => $this->screenLabel($file),
                            'url' => $this->screenUrl($entry, $file),
                        ],
                        $screens
                    ),
                ];
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

        $relativePath = $this->relativeMockupPath($code);
        $screens = $this->discoverScreens($code);

        $entry = $this->entryScreen($screens);

        return [
            'available' => $screens !== [],
            'path' => $relativePath,
            'screen_count' => count($screens),
            'entry' => $entry,
            'entry_url' => $this->screenUrl($code, $entry),
            'browsable' => $this->config->mockupsBrowsable(),
            'screens' => array_map(
                fn (string $file): array => [
                    'file' => $file,
                    'label' => $this->screenLabel($file),
                    'url' => $this->screenUrl($code, $file),
                ],
                $screens
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forSpec(string $code): ?array
    {
        SpecCode::ensure($code);

        $screens = $this->discoverScreens($code);

        if ($screens === []) {
            return null;
        }

        $entry = $this->entryScreen($screens);

        return [
            'path' => $this->relativeMockupPath($code),
            'entry' => $entry,
            'entry_url' => $this->screenUrl($code, $entry),
            'browsable' => $this->config->mockupsBrowsable(),
            'screens' => array_map(
                fn (string $file): array => [
                    'file' => $file,
                    'label' => $this->screenLabel($file),
                    'url' => $this->screenUrl($code, $file),
                ],
                $screens
            ),
        ];
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

        $finder = (new Finder)
            ->files()
            ->in($directory)
            ->name('*.html')
            ->name('*.htm')
            ->sortByName();

        $screens = [];

        foreach ($finder as $file) {
            $screens[] = str_replace('\\', '/', $file->getRelativePathname());
        }

        return $screens;
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

        if (strtolower(basename($file)) !== 'index.html') {
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
