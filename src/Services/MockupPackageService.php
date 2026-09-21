<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\ArtifactLanguage;
use Larapilot\Support\MockupAssetResolver;
use Larapilot\Support\MockupCssProcessor;
use Larapilot\Support\MockupHtmlProcessor;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

class MockupPackageService
{
    public function __construct(
        protected ConfigService $config,
        protected MockupService $mockups,
        protected PrdService $prd,
        protected MockupAssetResolver $assets,
        protected MockupHtmlProcessor $htmlProcessor,
        protected MockupCssProcessor $cssProcessor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return $this->mockups->catalog();
    }

    /**
     * Standalone HTML cover + table of contents. Dashboard iframe uses
     * live mockup URLs; the zip uses relative paths next to each screen.
     */
    public function presentationHtml(bool $forZip = false): string
    {
        $catalog = $this->mockups->catalog();
        $prd = $this->prd->read();
        $lang = ArtifactLanguage::detect($prd);
        $title = $this->projectTitle($prd);
        $copy = $this->copy($lang);
        $items = is_array($catalog['items'] ?? null) ? $catalog['items'] : [];

        $cards = '';
        $index = 1;
        $startHref = null;
        $startLabel = null;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = (string) ($item['code'] ?? '');
            $safeCode = $this->escape($code);
            $itemTitle = $this->escape((string) ($item['title'] ?? $code));
            $screens = is_array($item['screens'] ?? null) ? $item['screens'] : [];
            $entry = $item['entry'] ?? null;
            $links = '';

            foreach ($screens as $screen) {
                if (! is_array($screen)) {
                    continue;
                }

                $file = (string) ($screen['file'] ?? '');
                $label = $this->escape((string) ($screen['label'] ?? $file));
                $href = $this->screenHref($code, $screen, $forZip);
                $isEntry = $file !== '' && $file === $entry;

                $links .= '<a class="screen'.($isEntry ? ' is-entry' : '').'" href="'.$href.'">'.$label.'</a>';

                if ($startHref === null && $isEntry) {
                    $startHref = $href;
                    $startLabel = $safeCode.' · '.$label;
                }
            }

            $number = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $count = $this->escape(sprintf($copy['screens'], count($screens)));
            $cards .= <<<HTML
            <article class="card">
                <div class="num">{$number}</div>
                <div class="body">
                    <h2>{$safeCode} — {$itemTitle}</h2>
                    <p class="count">{$count}</p>
                    <div class="screens">{$links}</div>
                </div>
            </article>
HTML;
            $index++;
        }

        if ($cards === '') {
            $cards = '<p class="empty">'.$this->escape($copy['empty']).'</p>';
        }

        $start = '';

        if ($startHref !== null) {
            $start = '<p class="start"><a class="cta" href="'.$startHref.'">'.$this->escape($copy['start']).'</a>'
                .'<span class="start-hint">'.$this->escape($copy['start_hint']).' '.(string) $startLabel.'</span></p>';
        }

        $safeTitle = $this->escape($title);
        $kicker = $this->escape($copy['kicker']);
        $contents = $this->escape($copy['contents']);
        $lead = $this->escape($copy['lead']);
        $meta = $this->escape(
            sprintf($copy['meta'], (int) ($catalog['spec_count'] ?? 0), (int) ($catalog['screen_count'] ?? 0))
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$kicker} — {$safeTitle}</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f8fafc;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --accent: #2563eb;
            --card: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1220;
                --ink: #e5e7eb;
                --muted: #9ca3af;
                --line: #1f2937;
                --accent: #60a5fa;
                --card: #111827;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.5;
        }
        .sheet {
            max-width: 880px;
            margin: 0 auto;
            padding: 48px 28px 72px;
        }
        .kicker {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--accent);
            margin: 0 0 10px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            letter-spacing: -0.03em;
            line-height: 1.15;
        }
        .lead, .meta { color: var(--muted); margin: 0 0 8px; }
        .meta { font-size: 0.9rem; margin-bottom: 24px; }
        .start {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin: 0 0 32px;
        }
        .cta {
            display: inline-flex;
            padding: 10px 20px;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
        }
        .cta:hover { filter: brightness(1.08); }
        .start-hint { color: var(--muted); font-size: 0.84rem; }
        h2.toc {
            margin: 0 0 16px;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
            border-top: 1px solid var(--line);
            padding-top: 28px;
        }
        .card {
            display: grid;
            grid-template-columns: 56px 1fr;
            gap: 14px;
            padding: 18px 20px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--card);
            margin-bottom: 12px;
        }
        .num {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: -0.04em;
        }
        .card h2 {
            margin: 0 0 2px;
            font-size: 1.05rem;
        }
        .card .count {
            margin: 0 0 10px;
            color: var(--muted);
            font-size: 0.78rem;
        }
        .screens { display: flex; flex-wrap: wrap; gap: 8px; }
        .screen {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid var(--line);
            color: var(--ink);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
        }
        .screen:hover { border-color: var(--accent); color: var(--accent); }
        .screen.is-entry {
            border-color: var(--accent);
            color: var(--accent);
            background: color-mix(in srgb, var(--accent) 10%, transparent);
        }
        .screen.is-entry::before {
            content: '▶';
            font-size: 0.6rem;
            margin-right: 6px;
        }
        .empty { color: var(--muted); }
    </style>
</head>
<body>
    <main class="sheet">
        <p class="kicker">{$kicker}</p>
        <h1>{$safeTitle}</h1>
        <p class="lead">{$lead}</p>
        <p class="meta">{$meta}</p>
        {$start}
        <h2 class="toc">{$contents}</h2>
        {$cards}
    </main>
</body>
</html>
HTML;
    }

    /**
     * Build a zip of the presentation index, every mockup HTML file, local
     * assets, and the design-system files those screens actually reference.
     */
    public function writeZip(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to package mockups.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'lp-design-');

        if ($tmp === false) {
            throw new RuntimeException('Unable to create a temporary file for the design package.');
        }

        $zipPath = $tmp.'.zip';
        unlink($tmp);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open the design package zip.');
        }

        $zip->addFromString('index.html', $this->presentationHtml(true));

        $collected = [];
        $catalog = $this->mockups->catalog();
        $items = is_array($catalog['items'] ?? null) ? $catalog['items'] : [];
        $mockupsRoot = rtrim($this->assets->mockupsRoot(), DIRECTORY_SEPARATOR);
        $designRoot = rtrim($this->assets->designSystemsRoot(), DIRECTORY_SEPARATOR);

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = (string) ($item['code'] ?? '');
            $specRoot = $mockupsRoot.DIRECTORY_SEPARATOR.$code;

            if ($code === '' || ! is_dir($specRoot)) {
                continue;
            }

            $realSpec = realpath($specRoot);

            if ($realSpec === false) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($realSpec, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $absolute = $file->getPathname();
                $relative = ltrim(str_replace($realSpec, '', $absolute), DIRECTORY_SEPARATOR);
                $relative = str_replace('\\', '/', $relative);
                $zipEntry = $code.'/'.$relative;
                $extension = strtolower($file->getExtension());

                if (in_array($extension, ['html', 'htm'], true)) {
                    $html = (string) file_get_contents($absolute);
                    $zip->addFromString(
                        $zipEntry,
                        $this->htmlProcessor->process(
                            $html,
                            $code,
                            $realSpec,
                            $relative,
                            $this->packageUrlMapper($code, $relative, $realSpec, $designRoot, $collected)
                        )
                    );

                    continue;
                }

                if ($extension === 'css') {
                    $css = (string) file_get_contents($absolute);
                    $zip->addFromString(
                        $zipEntry,
                        $this->cssProcessor->process(
                            $css,
                            $code,
                            $realSpec,
                            $relative,
                            null,
                            $this->packageUrlMapper($code, $relative, $realSpec, $designRoot, $collected)
                        )
                    );

                    continue;
                }

                $zip->addFile($absolute, $zipEntry);
            }
        }

        foreach ($collected as $entry => $absolute) {
            if ($zip->locateName($entry) !== false) {
                continue;
            }

            if (is_file($absolute)) {
                $zip->addFile($absolute, $entry);
            }
        }

        $zip->close();

        return $zipPath;
    }

    public function downloadFilename(): string
    {
        $slug = $this->slug($this->projectTitle($this->prd->read()));

        return $slug.'-design.zip';
    }

    /**
     * @param  array<string, string>  $collected
     * @return callable(array{path: string, url: string}): string
     */
    protected function packageUrlMapper(string $spec, string $currentRelativePath, string $specRoot, string $designRoot, array &$collected): callable
    {
        $fromDir = dirname($spec.'/'.str_replace('\\', '/', $currentRelativePath));
        $fromDir = $fromDir === '.' ? $spec : $fromDir;

        return function (array $resolved) use ($spec, $specRoot, $designRoot, $fromDir, &$collected): string {
            $absolute = $resolved['path'];
            $real = realpath($absolute) ?: $absolute;
            $zipPath = $this->zipPathFor($real, $spec, $specRoot, $designRoot);

            if ($zipPath !== null) {
                $collected[$zipPath] = $real;

                return $this->relativeUrl($fromDir, $zipPath);
            }

            return $resolved['url'];
        };
    }

    protected function zipPathFor(string $absolute, string $spec, string $specRoot, string $designRoot): ?string
    {
        $absolute = str_replace('\\', '/', $absolute);
        $specRoot = str_replace('\\', '/', $specRoot);
        $designRoot = str_replace('\\', '/', $designRoot);

        if (str_starts_with($absolute, rtrim($specRoot, '/').'/') || $absolute === $specRoot) {
            $relative = ltrim(substr($absolute, strlen($specRoot)), '/');

            return $relative === '' ? $spec : $spec.'/'.$relative;
        }

        if (str_starts_with($absolute, rtrim($designRoot, '/').'/')) {
            $relative = ltrim(substr($absolute, strlen($designRoot)), '/');

            return $relative === '' ? null : 'design-systems/'.$relative;
        }

        return 'assets/'.basename($absolute);
    }

    protected function relativeUrl(string $fromDir, string $toFile): string
    {
        $from = array_values(array_filter(explode('/', str_replace('\\', '/', trim($fromDir, '/'))), fn (string $s): bool => $s !== ''));
        $to = array_values(array_filter(explode('/', str_replace('\\', '/', trim($toFile, '/'))), fn (string $s): bool => $s !== ''));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        $prefix = str_repeat('../', count($from));

        return $prefix.implode('/', $to);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Dashboard links hit the live mockup route; the zip links sit next to the
     * screen files themselves.
     *
     * @param  array<string, mixed>  $screen
     */
    protected function screenHref(string $code, array $screen, bool $forZip): string
    {
        if ($forZip) {
            return $this->escape($code.'/'.(string) ($screen['file'] ?? ''));
        }

        return $this->escape((string) ($screen['url'] ?? '#'));
    }

    protected function projectTitle(?string $prd): string
    {
        if (is_string($prd) && preg_match('/^#\s+(.+)$/m', $prd, $matches) === 1) {
            $title = trim($matches[1]);

            if ($title !== '') {
                return $title;
            }
        }

        $root = basename($this->config->projectRoot());

        return $root !== '' ? $root : 'Larapilot';
    }

    protected function slug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'design';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'design';
    }

    /**
     * @return array{kicker: string, contents: string, lead: string, meta: string, empty: string, start: string, start_hint: string, screens: string}
     */
    protected function copy(string $lang): array
    {
        return match ($lang) {
            'it' => [
                'kicker' => 'Presentazione design',
                'contents' => 'Sommario',
                'lead' => 'Punto di partenza per navigare tutte le schermate del prodotto, flusso per flusso.',
                'meta' => '%d flussi · %d schermate',
                'empty' => 'Nessun mockup ancora. Esegui /larapilot-design per generarli.',
                'start' => 'Inizia dalla prima schermata',
                'start_hint' => 'Parti da:',
                'screens' => '%d schermate · la prima è il punto di ingresso',
            ],
            'es' => [
                'kicker' => 'Presentación de diseño',
                'contents' => 'Índice',
                'lead' => 'Punto de partida para recorrer todas las pantallas del producto, flujo por flujo.',
                'meta' => '%d flujos · %d pantallas',
                'empty' => 'Todavía no hay mockups. Ejecuta /larapilot-design para generarlos.',
                'start' => 'Empieza por la primera pantalla',
                'start_hint' => 'Empieza en:',
                'screens' => '%d pantallas · la primera es la de entrada',
            ],
            'fr' => [
                'kicker' => 'Présentation design',
                'contents' => 'Sommaire',
                'lead' => 'Point de départ pour parcourir tous les écrans du produit, parcours par parcours.',
                'meta' => '%d parcours · %d écrans',
                'empty' => 'Aucun mockup pour le moment. Lancez /larapilot-design pour les générer.',
                'start' => 'Commencer par le premier écran',
                'start_hint' => 'Départ :',
                'screens' => '%d écrans · le premier est l\'écran d\'entrée',
            ],
            'de' => [
                'kicker' => 'Design-Präsentation',
                'contents' => 'Inhalt',
                'lead' => 'Der Ausgangspunkt, um jeden Bildschirm dieses Produkts durchzugehen, Ablauf für Ablauf.',
                'meta' => '%d Abläufe · %d Bildschirme',
                'empty' => 'Noch keine Mockups. Führen Sie /larapilot-design aus, um sie zu erzeugen.',
                'start' => 'Mit dem ersten Bildschirm beginnen',
                'start_hint' => 'Beginn bei:',
                'screens' => '%d Bildschirme · der erste ist der Einstieg',
            ],
            'pt' => [
                'kicker' => 'Apresentação de design',
                'contents' => 'Índice',
                'lead' => 'O ponto de partida para percorrer todos os ecrãs do produto, fluxo a fluxo.',
                'meta' => '%d fluxos · %d ecrãs',
                'empty' => 'Ainda não há mockups. Execute /larapilot-design para os gerar.',
                'start' => 'Começar pelo primeiro ecrã',
                'start_hint' => 'Início em:',
                'screens' => '%d ecrãs · o primeiro é o ponto de entrada',
            ],
            'nl' => [
                'kicker' => 'Designpresentatie',
                'contents' => 'Inhoud',
                'lead' => 'Het startpunt om elk scherm van dit product door te lopen, flow voor flow.',
                'meta' => '%d flows · %d schermen',
                'empty' => 'Nog geen mockups. Voer /larapilot-design uit om ze te genereren.',
                'start' => 'Begin bij het eerste scherm',
                'start_hint' => 'Start bij:',
                'screens' => '%d schermen · het eerste is het startpunt',
            ],
            'pl' => [
                'kicker' => 'Prezentacja projektu',
                'contents' => 'Spis treści',
                'lead' => 'Punkt wyjścia, by przejść przez każdy ekran produktu, ścieżka po ścieżce.',
                'meta' => '%d ścieżek · %d ekranów',
                'empty' => 'Nie ma jeszcze makiet. Uruchom /larapilot-design, aby je wygenerować.',
                'start' => 'Zacznij od pierwszego ekranu',
                'start_hint' => 'Start od:',
                'screens' => '%d ekranów · pierwszy jest ekranem wejściowym',
            ],
            default => [
                'kicker' => 'Design presentation',
                'contents' => 'Contents',
                'lead' => 'The starting point for walking every screen of this product, flow by flow.',
                'meta' => '%d flows · %d screens',
                'empty' => 'No mockups yet. Run /larapilot-design to generate them.',
                'start' => 'Start at the first screen',
                'start_hint' => 'Starting at:',
                'screens' => '%d screens · the first one is the entry point',
            ],
        };
    }
}
