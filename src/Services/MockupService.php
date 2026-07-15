<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\SpecCode;
use Symfony\Component\Finder\Finder;

class MockupService
{
    public function __construct(protected ConfigService $config) {}

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
        $config = $this->config->resolve();
        $mockupsPath = trim((string) ($config['paths']['mockups'] ?? '.larapilot/mockups/'), '/');

        return $mockupsPath.'/'.$code.'/';
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
