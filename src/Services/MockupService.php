<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\SpecCode;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class MockupService
{
    /**
     * One scan of the mockup tree per request. The board asks for every
     * spec, and each of those questions reads the same folders.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $flowIndex = null;

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

        foreach ($this->flowIndex() as $entry => $flow) {
            $packed = $flow['packed'];
            $spec = $flow['spec'];
            $screenCount += count($packed['screens']);

            $items[] = array_merge($packed, [
                'code' => $entry,
                'title' => is_array($spec) ? (string) ($spec['title'] ?? $entry) : $entry,
                'priority' => is_array($spec) ? (string) ($spec['priority'] ?? '') : '',
                'status' => is_array($spec) ? (string) ($spec['status'] ?? '') : '',
                'points' => is_array($spec) ? (int) ($spec['points'] ?? 0) : 0,
                'has_spec' => is_array($spec),
                'path' => $this->relativeMockupPath($entry),
                'browsable' => $this->config->mockupsBrowsable(),
                'specs' => $flow['spec_links'],
            ]);
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

        $detail = $this->forSpec($code);

        if ($detail === null) {
            return [
                'available' => false,
                'path' => $this->relativeMockupPath($code),
                'screen_count' => 0,
                'entry' => null,
                'entry_url' => null,
                'browsable' => $this->config->mockupsBrowsable(),
                'screens' => [],
                'styles' => [],
                'chosen_style' => null,
            ];
        }

        return [
            'available' => true,
            'path' => $detail['path'],
            'screen_count' => count($detail['screens']),
            'entry' => $detail['entry'],
            'entry_url' => $detail['entry_url'],
            'browsable' => $detail['browsable'],
            'screens' => $detail['screens'],
            'styles' => $detail['styles'],
            'chosen_style' => $detail['chosen_style'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forSpec(string $code): ?array
    {
        SpecCode::ensure($code);

        $index = $this->flowIndex();
        $own = is_array($index[$code]['packed'] ?? null) ? $index[$code]['packed'] : $this->emptyPack();
        $ownScreens = is_array($own['screens'] ?? null) ? $own['screens'] : [];
        $linked = $this->linkedScreens($code);

        if ($ownScreens === [] && $linked === []) {
            return null;
        }

        $screens = $ownScreens;
        $seen = [];

        foreach ($screens as $screen) {
            if (! is_array($screen)) {
                continue;
            }

            $seen[$this->screenKey($screen)] = true;
        }

        foreach ($linked as $screen) {
            $key = $this->screenKey($screen);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $screens[] = $screen;
        }

        $paths = [];

        if ($ownScreens !== []) {
            $paths[$code] = $this->relativeMockupPath($code);
        }

        foreach ($linked as $screen) {
            $flow = (string) ($screen['flow'] ?? '');

            if ($flow !== '') {
                $paths[$flow] = $this->relativeMockupPath($flow);
            }
        }

        $entry = $ownScreens !== [] ? ($own['entry'] ?? null) : ($screens[0]['file'] ?? null);
        $entryUrl = $ownScreens !== [] ? ($own['entry_url'] ?? null) : ($screens[0]['url'] ?? null);

        return [
            'path' => implode(', ', array_values($paths)),
            'entry' => is_string($entry) ? $entry : null,
            'entry_url' => is_string($entryUrl) ? $entryUrl : null,
            'browsable' => $this->config->mockupsBrowsable(),
            'screens' => array_values(array_filter($screens, 'is_array')),
            'styles' => is_array($own['styles'] ?? null) ? $own['styles'] : [],
            'chosen_style' => $own['chosen_style'] ?? null,
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

        $this->flowIndex = null;

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

    /**
     * Every mockup folder, packed once, with the stories its README names.
     *
     * A folder named after a spec still belongs to that spec. A feature
     * folder (public-site, admin-filament) belongs to the stories listed in
     * README as `Traces to` / `Traccia a`, and to the rows of a screen table
     * that name an HTML file beside those codes.
     *
     * @return array<string, array{packed: array<string, mixed>, coverage: array{specs: list<string>, screens: array<string, list<string>>}, spec_links: list<array{code: string, title: string, url: ?string}>, spec: ?array<string, mixed>}>
     */
    protected function flowIndex(): array
    {
        if ($this->flowIndex !== null) {
            return $this->flowIndex;
        }

        $index = [];
        $root = $this->mockupsRoot();

        if ($root === null) {
            return $this->flowIndex = [];
        }

        $known = [];

        foreach ($this->specs->allSpecs() as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $code = (string) ($spec['code'] ?? '');

            if ($code !== '') {
                $known[$code] = $spec;
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

            $directory = $this->absoluteMockupDirectory($entry);
            $coverage = $directory !== null
                ? $this->filterCoverage($this->readmeCoverage($directory), $known)
                : ['specs' => [], 'screens' => []];
            $packed = $this->tagPacked($packed, $coverage);

            $links = [];

            foreach ($coverage['specs'] as $specCode) {
                $links[] = [
                    'code' => $specCode,
                    'title' => (string) ($known[$specCode]['title'] ?? $specCode),
                    'url' => $this->specUrl($specCode),
                ];
            }

            $index[$entry] = [
                'packed' => $packed,
                'coverage' => $coverage,
                'spec_links' => $links,
                'spec' => $known[$entry] ?? null,
            ];
        }

        return $this->flowIndex = $index;
    }

    /**
     * Screens in other folders whose README points at this spec.
     *
     * @return list<array<string, mixed>>
     */
    protected function linkedScreens(string $spec): array
    {
        $found = [];

        foreach ($this->flowIndex() as $flowCode => $flow) {
            if ($flowCode === $spec) {
                continue;
            }

            $links = array_column($flow['spec_links'], 'code');

            if (! in_array($spec, $links, true)) {
                continue;
            }

            foreach ($this->screensCoveringSpec($flow['packed'], $spec, $flow['coverage'], $flowCode) as $screen) {
                $found[] = $screen;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $packed
     * @param  array{specs: list<string>, screens: array<string, list<string>>}  $coverage
     * @return list<array<string, mixed>>
     */
    protected function screensCoveringSpec(array $packed, string $spec, array $coverage, string $flowCode): array
    {
        $pool = $this->flattenScreens($packed, $flowCode);
        $inRows = false;

        foreach ($coverage['screens'] as $codes) {
            if (in_array($spec, $codes, true)) {
                $inRows = true;
                break;
            }
        }

        if ($inRows) {
            return array_values(array_filter(
                $pool,
                fn (array $screen): bool => $this->fileListedFor((string) ($screen['file'] ?? ''), $spec, $coverage)
            ));
        }

        if (! in_array($spec, $coverage['specs'], true)) {
            return [];
        }

        $byStyle = [];

        foreach ($pool as $screen) {
            $byStyle[(string) ($screen['style_id'] ?? '')][] = $screen;
        }

        $entries = [];

        foreach ($byStyle as $screens) {
            $entry = null;

            foreach ($screens as $screen) {
                if (strtolower(basename((string) ($screen['file'] ?? ''))) === 'index.html') {
                    $entry = $screen;
                    break;
                }
            }

            $entries[] = $entry ?? $screens[0];
        }

        return $entries;
    }

    /**
     * Every screen of every style, so a story sees playful-brand as well as
     * the files sitting at the folder root.
     *
     * @param  array<string, mixed>  $packed
     * @return list<array<string, mixed>>
     */
    protected function flattenScreens(array $packed, string $flowCode): array
    {
        $styles = is_array($packed['styles'] ?? null) ? $packed['styles'] : [];
        $rows = [];

        $push = function (array $screen, ?string $styleId, ?string $styleLabel) use (&$rows, $flowCode): void {
            $screen['flow'] = $flowCode;

            if (is_string($styleId) && $styleId !== '' && $styleId !== 'current') {
                $screen['style'] = $styleLabel !== null && $styleLabel !== '' ? $styleLabel : $styleId;
                $screen['style_id'] = $styleId;
            } elseif ($styleId === 'current') {
                $screen['style'] = $styleLabel !== null && $styleLabel !== '' ? $styleLabel : 'Current';
                $screen['style_id'] = 'current';
            }

            $rows[] = $screen;
        };

        $named = array_values(array_filter(
            $styles,
            static fn (mixed $style): bool => is_array($style) && ($style['id'] ?? '') !== 'current'
        ));

        if ($named !== [] || count($styles) > 1) {
            foreach ($styles as $style) {
                if (! is_array($style)) {
                    continue;
                }

                $id = (string) ($style['id'] ?? '');
                $label = (string) ($style['label'] ?? $id);

                foreach (is_array($style['screens'] ?? null) ? $style['screens'] : [] as $screen) {
                    if (! is_array($screen)) {
                        continue;
                    }

                    $push($screen, $id, $label);
                }
            }

            return $rows;
        }

        foreach (is_array($packed['screens'] ?? null) ? $packed['screens'] : [] as $screen) {
            if (! is_array($screen)) {
                continue;
            }

            $push($screen, null, null);
        }

        return $rows;
    }

    /**
     * @return array{specs: list<string>, screens: array<string, list<string>>}
     */
    protected function readmeCoverage(string $directory): array
    {
        $empty = ['specs' => [], 'screens' => []];
        $path = $directory.DIRECTORY_SEPARATOR.'README.md';

        if (! is_file($path)) {
            return $empty;
        }

        $content = (string) file_get_contents($path);

        if ($content === '') {
            return $empty;
        }

        $screens = [];

        foreach (preg_split("/\R/", $content) ?: [] as $line) {
            if (! str_starts_with(ltrim($line), '|')) {
                continue;
            }

            $rowSpecs = $this->specCodesIn($line);

            if ($rowSpecs === []) {
                continue;
            }

            foreach ($this->htmlFilesIn($line) as $file) {
                $screens[$file] = array_values(array_unique(array_merge($screens[$file] ?? [], $rowSpecs)));
            }
        }

        return [
            'specs' => $this->specCodesIn($content),
            'screens' => $screens,
        ];
    }

    /**
     * @param  array{specs: list<string>, screens: array<string, list<string>>}  $coverage
     * @param  array<string, array<string, mixed>>  $known
     * @return array{specs: list<string>, screens: array<string, list<string>>}
     */
    protected function filterCoverage(array $coverage, array $known): array
    {
        $specs = array_values(array_filter(
            $coverage['specs'],
            static fn (string $code): bool => isset($known[$code])
        ));

        $screens = [];

        foreach ($coverage['screens'] as $file => $codes) {
            $kept = array_values(array_filter(
                $codes,
                static fn (string $code): bool => isset($known[$code])
            ));

            if ($kept !== []) {
                $screens[$file] = $kept;
            }
        }

        return ['specs' => $specs, 'screens' => $screens];
    }

    /**
     * @param  array<string, mixed>  $packed
     * @param  array{specs: list<string>, screens: array<string, list<string>>}  $coverage
     * @return array<string, mixed>
     */
    protected function tagPacked(array $packed, array $coverage): array
    {
        $packed['screens'] = $this->tagScreens(
            is_array($packed['screens'] ?? null) ? $packed['screens'] : [],
            $coverage
        );

        $styles = [];

        foreach (is_array($packed['styles'] ?? null) ? $packed['styles'] : [] as $style) {
            if (! is_array($style)) {
                continue;
            }

            $style['screens'] = $this->tagScreens(
                is_array($style['screens'] ?? null) ? $style['screens'] : [],
                $coverage
            );
            $styles[] = $style;
        }

        $packed['styles'] = $styles;

        return $packed;
    }

    /**
     * @param  list<mixed>  $screens
     * @param  array{specs: list<string>, screens: array<string, list<string>>}  $coverage
     * @return list<array<string, mixed>>
     */
    protected function tagScreens(array $screens, array $coverage): array
    {
        $tagged = [];

        foreach ($screens as $screen) {
            if (! is_array($screen)) {
                continue;
            }

            $codes = [];

            foreach ($coverage['screens'] as $mapped => $mappedCodes) {
                if (! $this->sameScreen((string) ($screen['file'] ?? ''), (string) $mapped)) {
                    continue;
                }

                foreach ($mappedCodes as $mappedCode) {
                    if (! in_array($mappedCode, $codes, true)) {
                        $codes[] = $mappedCode;
                    }
                }
            }

            $screen['specs'] = $codes;
            $tagged[] = $screen;
        }

        return $tagged;
    }

    /**
     * @param  array{specs: list<string>, screens: array<string, list<string>>}  $coverage
     */
    protected function fileListedFor(string $file, string $spec, array $coverage): bool
    {
        foreach ($coverage['screens'] as $mapped => $codes) {
            if (! in_array($spec, $codes, true)) {
                continue;
            }

            if ($this->sameScreen($file, (string) $mapped)) {
                return true;
            }
        }

        return false;
    }

    protected function sameScreen(string $file, string $mapped): bool
    {
        $file = str_replace('\\', '/', $file);
        $mapped = str_replace('\\', '/', ltrim($mapped, '/'));

        if ($mapped === '') {
            return false;
        }

        if ($file === $mapped || str_ends_with($file, '/'.$mapped)) {
            return true;
        }

        return ! str_contains($mapped, '/') && basename($file) === $mapped;
    }

    /**
     * @return list<string>
     */
    protected function specCodesIn(string $text): array
    {
        if (preg_match_all('/\b([A-Z][A-Z0-9]{0,9}-\d+)\b/', $text, $matches) < 1) {
            return [];
        }

        $codes = [];

        foreach ($matches[1] as $code) {
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @return list<string>
     */
    protected function htmlFilesIn(string $line): array
    {
        if (preg_match_all('/`([^`\s]+\.html?)`|(?<![A-Za-z0-9_.\/-])((?:[A-Za-z0-9_.-]+\/)*[A-Za-z0-9_.-]+\.html?)\b/i', $line, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $files = [];

        foreach ($matches as $match) {
            $file = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');
            $file = str_replace('\\', '/', $file);

            if ($file === '' || in_array($file, $files, true)) {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $screen
     */
    protected function screenKey(array $screen): string
    {
        $url = $screen['url'] ?? null;

        if (is_string($url) && $url !== '') {
            return $url;
        }

        return (string) ($screen['flow'] ?? '').'|'.(string) ($screen['file'] ?? '');
    }

    protected function specUrl(string $code): ?string
    {
        if (! app('router')->has('larapilot.dashboard.spec')) {
            return null;
        }

        return route('larapilot.dashboard.spec', ['code' => $code], absolute: false);
    }

    /**
     * @return array{entry: null, entry_url: null, screens: list<empty>, styles: list<empty>, chosen_style: null}
     */
    protected function emptyPack(): array
    {
        return [
            'entry' => null,
            'entry_url' => null,
            'screens' => [],
            'styles' => [],
            'chosen_style' => null,
        ];
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
