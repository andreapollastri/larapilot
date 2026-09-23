<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\ArtifactLanguage;
use Larapilot\Support\FunctionalSummaryStrings;

/**
 * A readable summary of the PRD's functional analysis: one numbered point
 * per requirement, required work first. The words of each point stay the
 * PRD's; only the frame is translated.
 */
class FunctionalSummaryWriter
{
    public function label(string $prd): string
    {
        return FunctionalSummaryStrings::line(ArtifactLanguage::detect($prd), 'title');
    }

    public function filename(string $prd): string
    {
        $lang = ArtifactLanguage::detect($prd);
        $slug = $this->slug($this->projectTitle($prd));

        return $slug.'-'.FunctionalSummaryStrings::line($lang, 'file').'.md';
    }

    public function render(string $prd): string
    {
        $lang = ArtifactLanguage::detect($prd);
        $t = static fn (string $key): string => FunctionalSummaryStrings::line($lang, $key);

        $sections = $this->sections($prd);
        $points = $this->points($this->section($sections, $this->requirementHeadings()));
        $people = $this->people($this->section($sections, $this->personaHeadings()));
        $scope = $this->scope($this->section($sections, $this->scopeHeadings()));
        $brief = $this->brief($this->section($sections, $this->briefHeadings()));

        $lines = ['# '.$t('title'), '', $t('lead'), ''];

        $title = $this->projectTitle($prd);

        if ($title !== '') {
            $lines[] = '**'.$t('product').':** '.$title;
        }

        $date = $this->date($prd);

        if ($date !== '') {
            $lines[] = '**'.$t('date').':** '.$date;
        }

        if ($title !== '' || $date !== '') {
            $lines[] = '';
        }

        if ($brief !== '') {
            $lines[] = '## '.$t('brief');
            $lines[] = '';
            $lines[] = $brief;
            $lines[] = '';
        }

        if ($people !== []) {
            $lines[] = '## '.$t('who');
            $lines[] = '';

            foreach ($people as $person) {
                $lines[] = $person['role'] !== ''
                    ? '- **'.$person['name'].'** — '.$person['role']
                    : '- **'.$person['name'].'**';
            }

            $lines[] = '';
        }

        $lines[] = '## '.$t('what');
        $lines[] = '';

        if ($points === []) {
            $lines[] = $t('empty');
            $lines[] = '';
        } else {
            $number = 1;

            foreach ($this->grouped($points) as $moscow => $group) {
                $lines[] = '### '.$t($this->groupKey($moscow));
                $lines[] = '';

                foreach ($group as $point) {
                    $lines[] = $number.'. **'.$this->pointTitle($point).'**';

                    if ($point['prose'] !== '') {
                        $lines[] = '';
                        $lines[] = $point['prose'];
                    }

                    foreach ($point['bullets'] as $bullet) {
                        $lines[] = '   - '.$bullet;
                    }

                    $lines[] = '';
                    $number++;
                }
            }
        }

        if ($scope['in'] !== []) {
            $lines[] = '## '.$t('included');
            $lines[] = '';

            foreach ($scope['in'] as $item) {
                $lines[] = '- '.$item;
            }

            $lines[] = '';
        }

        if ($scope['out'] !== []) {
            $lines[] = '## '.$t('excluded');
            $lines[] = '';

            foreach ($scope['out'] as $item) {
                $lines[] = '- '.$item;
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    /**
     * @param  list<array{code: string, title: string, theme: string, moscow: string, prose: string, bullets: list<string>, index: int}>  $points
     * @return array<string, list<array{code: string, title: string, theme: string, moscow: string, prose: string, bullets: list<string>, index: int}>>
     */
    protected function grouped(array $points): array
    {
        $order = ['Must' => 0, 'Should' => 1, 'Could' => 2, "Won't" => 3, '' => 4];

        usort($points, function (array $a, array $b) use ($order): int {
            $byPriority = ($order[$a['moscow']] ?? 4) <=> ($order[$b['moscow']] ?? 4);

            if ($byPriority !== 0) {
                return $byPriority;
            }

            $byCode = $this->codeNumber($a['code']) <=> $this->codeNumber($b['code']);

            return $byCode !== 0 ? $byCode : $a['index'] <=> $b['index'];
        });

        $groups = [];

        foreach ($points as $point) {
            $groups[$point['moscow']][] = $point;
        }

        return $groups;
    }

    protected function groupKey(string $moscow): string
    {
        return match ($moscow) {
            'Must' => 'must',
            'Should' => 'should',
            'Could' => 'could',
            "Won't" => 'wont',
            default => 'open',
        };
    }

    /**
     * @param  array{code: string, title: string, theme: string, moscow: string, prose: string, bullets: list<string>, index: int}  $point
     */
    protected function pointTitle(array $point): string
    {
        $title = $point['code'] !== ''
            ? $point['code'].' — '.$point['title']
            : $point['title'];

        return $point['theme'] !== '' ? $point['theme'].' — '.$title : $title;
    }

    protected function codeNumber(string $code): int
    {
        if (preg_match('/(\d+)/', $code, $matches) !== 1) {
            return PHP_INT_MAX;
        }

        return (int) $matches[1];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    protected function sections(string $markdown): array
    {
        $parts = preg_split('/^##\s+(.+)$/m', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $sections = [];

        for ($index = 1; $index < count($parts); $index += 2) {
            $sections[] = [
                'title' => trim($parts[$index]),
                'body' => trim($parts[$index + 1] ?? ''),
            ];
        }

        return $sections;
    }

    /**
     * @param  list<array{title: string, body: string}>  $sections
     * @param  list<string>  $headings
     */
    protected function section(array $sections, array $headings): string
    {
        foreach ($sections as $section) {
            foreach ($headings as $heading) {
                if (strcasecmp($section['title'], $heading) === 0) {
                    return $section['body'];
                }
            }
        }

        return '';
    }

    /**
     * @return list<array{code: string, title: string, theme: string, moscow: string, prose: string, bullets: list<string>, index: int}>
     */
    protected function points(string $body): array
    {
        if (trim($body) === '') {
            return [];
        }

        $chunks = preg_split('/^###\s+(.+)$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        if (count($chunks) < 3) {
            return $this->bulletPoints($body, '');
        }

        $points = [];

        for ($index = 1; $index < count($chunks); $index += 2) {
            $heading = trim($chunks[$index]);
            $chunk = trim($chunks[$index + 1] ?? '');
            $parsed = $this->parseHeading($heading);

            if ($parsed['code'] !== '') {
                $points[] = $this->point($parsed['code'], $parsed['title'], '', $chunk, count($points));

                continue;
            }

            $children = preg_split('/^####\s+(.+)$/m', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

            if (count($children) >= 3) {
                for ($child = 1; $child < count($children); $child += 2) {
                    $childHeading = $this->parseHeading(trim($children[$child]));
                    $points[] = $this->point(
                        $childHeading['code'],
                        $childHeading['title'],
                        $heading,
                        trim($children[$child + 1] ?? ''),
                        count($points),
                    );
                }

                continue;
            }

            $points[] = $this->point('', $heading, '', $chunk, count($points));
        }

        return $points;
    }

    /**
     * @return list<array{code: string, title: string, theme: string, moscow: string, prose: string, bullets: list<string>, index: int}>
     */
    protected function bulletPoints(string $body, string $theme): array
    {
        $points = [];
        $current = null;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches) === 1) {
                if ($current !== null) {
                    $points[] = $current;
                }

                $current = $this->point('', $this->clean($matches[1]), $theme, '', count($points));

                continue;
            }

            if ($current !== null && preg_match('/^\s+[-*]\s+(.+)$/', $line, $matches) === 1) {
                $current['bullets'][] = $this->clean($matches[1]);
            }
        }

        if ($current !== null) {
            $points[] = $current;
        }

        return $points;
    }

    /**
     * @return array{code: string, title: string, theme: string, moscow: string, prose: string, bullets: list<string>, index: int}
     */
    protected function point(string $code, string $title, string $theme, string $body, int $index): array
    {
        $moscow = $this->moscow($body);
        $body = preg_replace('/<!--.*?-->/s', '', $body) ?? $body;
        $body = preg_replace('/^\*\*MoSCoW:\*\*.*$/mi', '', $body) ?? $body;

        $bullets = [];
        $prose = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches) === 1) {
                $bullets[] = $this->clean($matches[1]);

                continue;
            }

            $prose[] = $this->clean($trimmed);
        }

        return [
            'code' => $code,
            'title' => $this->clean($title),
            'theme' => $this->clean($theme),
            'moscow' => $moscow,
            'prose' => $this->sentence(implode(' ', $prose)),
            'bullets' => $bullets,
            'index' => $index,
        ];
    }

    /**
     * @return array{code: string, title: string}
     */
    protected function parseHeading(string $heading): array
    {
        if (preg_match('/^(FR-\d+)\s*[:—–\-]\s*(.+)$/iu', $heading, $matches) === 1) {
            return ['code' => strtoupper($matches[1]), 'title' => trim($matches[2])];
        }

        return ['code' => '', 'title' => $heading];
    }

    protected function moscow(string $body): string
    {
        if (preg_match('/\*\*MoSCoW:\*\*\s*(Must|Should|Could|Won[\'’]t)/iu', $body, $matches) !== 1) {
            return '';
        }

        $value = strtolower(str_replace('’', "'", $matches[1]));

        return match ($value) {
            'must' => 'Must',
            'should' => 'Should',
            'could' => 'Could',
            default => "Won't",
        };
    }

    /**
     * @return list<array{name: string, role: string}>
     */
    protected function people(string $body): array
    {
        if (trim($body) === '') {
            return [];
        }

        $chunks = preg_split('/^###\s+(.+)$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $people = [];

        if (count($chunks) < 3) {
            foreach ($this->topBullets($body) as $bullet) {
                $people[] = ['name' => $bullet, 'role' => ''];
            }

            return $people;
        }

        for ($index = 1; $index < count($chunks); $index += 2) {
            $role = '';

            if (preg_match('/\*\*(?:Role|Ruolo|Rol|Rôle|Rolle|Função|Papel|Functie|Rola):\*\*\s*(.+)$/mi', $chunks[$index + 1] ?? '', $matches) === 1) {
                $role = $this->clean($matches[1]);
            }

            $people[] = ['name' => $this->clean(trim($chunks[$index])), 'role' => $role];
        }

        return $people;
    }

    /**
     * @return array{in: list<string>, out: list<string>}
     */
    protected function scope(string $body): array
    {
        $scope = ['in' => [], 'out' => []];

        if (trim($body) === '') {
            return $scope;
        }

        $chunks = preg_split('/^###\s+(.+)$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        for ($index = 1; $index < count($chunks); $index += 2) {
            $side = $this->scopeSide(trim($chunks[$index]));

            if ($side === null) {
                continue;
            }

            $scope[$side] = $this->topBullets($chunks[$index + 1] ?? '');
        }

        return $scope;
    }

    protected function scopeSide(string $heading): ?string
    {
        $in = ['In Scope', 'In ambito', 'Dentro', "Dentro l'ambito", 'En alcance', 'Dans le périmètre', 'Im Umfang', 'No âmbito', 'No escopo', 'W zakresie'];
        $out = ['Out of Scope', 'Fuori ambito', 'Fuori scope', 'Fuera de alcance', 'Hors périmètre', 'Nicht im Umfang', 'Fora de âmbito', 'Fora do escopo', 'Buiten scope', 'Poza zakresem'];

        foreach ($in as $label) {
            if (strcasecmp($heading, $label) === 0) {
                return 'in';
            }
        }

        foreach ($out as $label) {
            if (strcasecmp($heading, $label) === 0) {
                return 'out';
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function topBullets(string $body): array
    {
        $bullets = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches) === 1) {
                $bullets[] = $this->clean($matches[1]);
            }
        }

        return $bullets;
    }

    protected function brief(string $body): string
    {
        $paragraphs = preg_split('/\R\s*\R/', trim($body)) ?: [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '' || str_starts_with($paragraph, '#') || str_starts_with($paragraph, '-') || str_starts_with($paragraph, '*')) {
                continue;
            }

            return $this->sentence($this->clean($paragraph));
        }

        return '';
    }

    protected function date(string $prd): string
    {
        if (preg_match('/\*\*(?:Date|Data|Fecha|Datum):\*\*\s*(.+)$/mi', $prd, $matches) !== 1) {
            return '';
        }

        return $this->clean($matches[1]);
    }

    protected function projectTitle(string $prd): string
    {
        if (preg_match('/^#\s+(.+)$/m', $prd, $matches) !== 1) {
            return '';
        }

        return $this->clean($matches[1]);
    }

    protected function sentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '' || str_ends_with($text, '.') || str_ends_with($text, '。') || str_ends_with($text, '!') || str_ends_with($text, '?')) {
            return $text;
        }

        return $text.'.';
    }

    protected function clean(string $text): string
    {
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[*_`]{1,3}([^*_`]+)[*_`]{1,3}/', '$1', $text) ?? $text;
        $text = str_replace(['<!--', '-->'], '', $text);

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    protected function slug(string $title): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii !== false ? $ascii : $title), '-'));

        return $slug !== '' ? $slug : 'product';
    }

    /**
     * @return list<string>
     */
    protected function requirementHeadings(): array
    {
        return ['Functional Requirements', 'Requisiti funzionali', 'Funzionalità', 'Funzionalità principali', 'Requisitos funcionales', 'Funcionalidades', 'Exigences fonctionnelles', 'Fonctionnalités', 'Funktionale Anforderungen', 'Funktionen', 'Requisitos funcionais', 'Functionele eisen', 'Functionele vereisten', 'Functionaliteiten', 'Wymagania funkcjonalne', 'Funkcjonalności'];
    }

    /**
     * @return list<string>
     */
    protected function personaHeadings(): array
    {
        return ['User Personas', 'Personas', 'Personas utente', 'Personas de usuario', 'Utenti e ruoli', 'Benutzer und Rollen', 'Utilizadores e perfis', 'Usuários e perfis', 'Gebruikers en rollen', 'Użytkownicy i role'];
    }

    /**
     * @return list<string>
     */
    protected function scopeHeadings(): array
    {
        return ['MVP Scope', 'Ambito MVP', 'Alcance MVP', 'Périmètre MVP', 'MVP-Umfang', 'Âmbito MVP', 'Escopo MVP', 'MVP-scope', 'Zakres MVP'];
    }

    /**
     * @return list<string>
     */
    protected function briefHeadings(): array
    {
        return ['Elevator Pitch', 'Pitch', 'Sintesi', 'Panoramica', 'Accroche', 'Resumen', 'Synthèse', 'Overview', 'Kurzbeschreibung', 'Überblick', 'Übersicht', 'Resumo', 'Visão geral', 'Samenvatting', 'Overzicht', 'Streszczenie', 'Przegląd'];
    }
}
