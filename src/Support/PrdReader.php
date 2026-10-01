<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * Reads a PRD by the piece: its outline, the blocks of the ids a spec
 * traces to, or one section. A skill that needs three requirements does not
 * have to load the whole document to find them.
 */
final class PrdReader
{
    /**
     * The sections with their size, and every id with its title.
     *
     * @return array{
     *     sections: list<array{title: string, tokens: int, parts?: list<string>}>,
     *     ids: list<array{id: string, title: string, moscow?: string}>
     * }
     */
    public static function outline(string $content): array
    {
        $lines = self::lines($content);
        $headings = self::headings($lines);
        $sections = [];

        foreach ($headings as $index => $heading) {
            if ($heading['level'] !== 2) {
                continue;
            }

            $end = self::blockEnd($headings, $index, count($lines));
            $parts = [];

            foreach ($headings as $inner) {
                if ($inner['line'] > $heading['line'] && $inner['line'] < $end && $inner['level'] === 3
                    && preg_match('/^(?:FR|J)-\d+\b/i', $inner['title']) !== 1) {
                    $parts[] = $inner['title'];
                }
            }

            $section = [
                'title' => $heading['title'],
                'tokens' => self::tokens(array_slice($lines, $heading['line'], $end - $heading['line'])),
            ];

            if ($parts !== []) {
                $section['parts'] = $parts;
            }

            $sections[] = $section;
        }

        $ids = [];

        foreach (PrdIds::defined($content) as $id => $definition) {
            $entry = ['id' => $id, 'title' => $definition['title']];

            if ($definition['moscow'] !== null) {
                $entry['moscow'] = $definition['moscow'];
            }

            $ids[] = $entry;
        }

        return ['sections' => $sections, 'ids' => $ids];
    }

    /**
     * The block of each id: an FR or a journey from its heading to the next
     * heading of its level, an NFR or an open question as its table row under
     * the header of that table.
     *
     * @param  list<string>  $ids
     * @return array{blocks: list<array{id: string, markdown: string}>, unknown: list<string>}
     */
    public static function blocks(string $content, array $ids): array
    {
        $lines = self::lines($content);
        $headings = self::headings($lines);
        $blocks = [];
        $unknown = [];

        foreach ($ids as $id) {
            $id = strtoupper(trim($id));
            $markdown = in_array(PrdIds::typeOf($id), ['FR', 'J'], true)
                ? self::headingBlock($lines, $headings, $id)
                : self::rowBlock($lines, $id);

            if ($markdown === null) {
                $unknown[] = $id;

                continue;
            }

            $blocks[] = ['id' => $id, 'markdown' => $markdown];
        }

        return ['blocks' => $blocks, 'unknown' => $unknown];
    }

    /**
     * Sections by title, in the language the PRD uses or in English.
     *
     * @param  list<string>  $titles
     * @return array{sections: list<array{title: string, markdown: string}>, unknown: list<string>}
     */
    public static function sections(string $content, array $titles): array
    {
        $lines = self::lines($content);
        $headings = self::headings($lines);
        $sections = [];
        $unknown = [];

        foreach ($titles as $title) {
            $wanted = self::aliases($title);
            $found = null;

            foreach ($headings as $index => $heading) {
                if ($heading['level'] <= 3 && in_array(self::normalize($heading['title']), $wanted, true)) {
                    $found = $index;

                    break;
                }
            }

            if ($found === null) {
                $unknown[] = trim($title);

                continue;
            }

            $start = $headings[$found]['line'];
            $end = self::blockEnd($headings, $found, count($lines));

            $sections[] = [
                'title' => $headings[$found]['title'],
                'markdown' => self::join(array_slice($lines, $start, $end - $start)),
            ];
        }

        return ['sections' => $sections, 'unknown' => $unknown];
    }

    /**
     * @return list<string>
     */
    protected static function lines(string $content): array
    {
        return preg_split('/\r\n|\r|\n/', $content) ?: [];
    }

    /**
     * Headings outside fenced code, with the line each one sits on.
     *
     * @param  list<string>  $lines
     * @return list<array{line: int, level: int, title: string}>
     */
    protected static function headings(array $lines): array
    {
        $headings = [];
        $fenced = false;

        foreach ($lines as $number => $line) {
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $fenced = ! $fenced;

                continue;
            }

            if (! $fenced && preg_match('/^(#{1,6})[ \t]+(.+?)[ \t]*$/', $line, $match) === 1) {
                $headings[] = ['line' => $number, 'level' => strlen($match[1]), 'title' => trim($match[2])];
            }
        }

        return $headings;
    }

    /**
     * The line where the block of a heading stops: the next heading of the
     * same level or above, or the end of the document.
     *
     * @param  list<array{line: int, level: int, title: string}>  $headings
     */
    protected static function blockEnd(array $headings, int $index, int $total): int
    {
        for ($next = $index + 1; $next < count($headings); $next++) {
            if ($headings[$next]['level'] <= $headings[$index]['level']) {
                return $headings[$next]['line'];
            }
        }

        return $total;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<array{line: int, level: int, title: string}>  $headings
     */
    protected static function headingBlock(array $lines, array $headings, string $id): ?string
    {
        foreach ($headings as $index => $heading) {
            if (preg_match('/^'.preg_quote($id, '/').'\b/i', $heading['title']) === 1) {
                $end = self::blockEnd($headings, $index, count($lines));

                return self::join(array_slice($lines, $heading['line'], $end - $heading['line']));
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    protected static function rowBlock(array $lines, string $id): ?string
    {
        foreach ($lines as $number => $line) {
            if (preg_match('/^[ \t]*\|[ \t]*'.preg_quote($id, '/').'[ \t]*\|/i', $line) !== 1) {
                continue;
            }

            $top = $number;

            while ($top > 0 && str_starts_with(ltrim($lines[$top - 1]), '|')) {
                $top--;
            }

            // The first two lines of a table are its header and its rule.
            $header = $top + 1 < $number ? array_slice($lines, $top, 2) : [];

            return self::join(array_merge($header, [$line]));
        }

        return null;
    }

    /**
     * A title and what the PRD may call it in another language.
     *
     * @return list<string>
     */
    protected static function aliases(string $title): array
    {
        $wanted = [self::normalize($title)];

        foreach (ArtifactSections::prd() + ArtifactSections::prdRecommended() as $canonical => $names) {
            $normalized = array_map([self::class, 'normalize'], array_merge([$canonical], $names));

            if (in_array($wanted[0], $normalized, true)) {
                $wanted = array_merge($wanted, $normalized);
            }
        }

        return array_values(array_unique($wanted));
    }

    protected static function normalize(string $title): string
    {
        $title = (string) preg_replace('/[ \t]*_\([^)]*\)_[ \t]*$/', '', trim($title));

        return mb_strtolower(trim($title, " \t#"));
    }

    /**
     * @param  list<string>  $lines
     */
    protected static function join(array $lines): string
    {
        return trim(implode("\n", $lines));
    }

    /**
     * @param  list<string>  $lines
     */
    protected static function tokens(array $lines): int
    {
        return (int) ceil(mb_strlen(implode("\n", $lines)) / 4);
    }
}
