<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * The identifiers a PRD defines and cites: FR-, J-, NFR-, Q-.
 *
 * FRs and journeys are defined by a heading (`### FR-001: …`), NFRs and open
 * questions by the first cell of a table row (`| NFR-001 | …`). Ids are
 * permanent: a revision retires one, it never renumbers or reuses it.
 * Definitions are returned by type (FR, J, NFR, Q), then by number.
 */
class PrdIds
{
    public const TYPES = ['FR', 'J', 'NFR', 'Q'];

    /**
     * @return array<string, array{type: string, title: string, moscow: string|null, count: int}>
     */
    public static function defined(string $content): array
    {
        $defined = [];

        $parts = preg_split(
            '/^#{2,6}[ \t]+((?:FR|J)-\d+)\b[ \t]*[:—–-]?[ \t]*([^\n]*)$/miu',
            $content,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        // [text before, id, title, block, id, title, block, …]
        foreach (array_chunk(array_slice($parts === false ? [] : $parts, 1), 3) as $chunk) {
            if (count($chunk) !== 3) {
                continue;
            }

            [$rawId, $rawTitle, $rawBlock] = $chunk;

            $id = strtoupper($rawId);
            $title = trim($rawTitle);
            $block = preg_split('/^#{1,6}[ \t]/m', $rawBlock)[0] ?? '';
            $moscow = null;

            if (preg_match('/\*\*MoSCoW:\*\*[ \t]*(Must|Should|Could|Won\'t|Wont)/i', $block, $match) === 1) {
                $moscow = ucfirst(strtolower($match[1]));
                $moscow = $moscow === 'Wont' ? "Won't" : $moscow;
            }

            $defined[$id] = [
                'type' => self::typeOf($id),
                'title' => trim(preg_replace('/[ \t]*_\([^)]*\)_[ \t]*$/', '', $title) ?? $title),
                'moscow' => $defined[$id]['moscow'] ?? $moscow,
                'count' => ($defined[$id]['count'] ?? 0) + 1,
            ];
        }

        if (preg_match_all('/^[ \t]*\|[ \t]*((?:NFR|Q)-\d+)[ \t]*\|[ \t]*([^|\n]*)\|/mi', $content, $rows, PREG_SET_ORDER) > 0) {
            foreach ($rows as $row) {
                $id = strtoupper($row[1]);

                $defined[$id] = [
                    'type' => self::typeOf($id),
                    'title' => trim($row[2]),
                    'moscow' => null,
                    'count' => ($defined[$id]['count'] ?? 0) + 1,
                ];
            }
        }

        $order = array_flip(self::TYPES);

        uksort($defined, static fn (string $a, string $b): int => [$order[self::typeOf($a)] ?? 99, self::numberOf($a)]
            <=> [$order[self::typeOf($b)] ?? 99, self::numberOf($b)]);

        return $defined;
    }

    /**
     * Every id cited in a text, upper-cased, in order of first appearance.
     *
     * @return list<string>
     */
    public static function cited(string $text): array
    {
        if (preg_match_all('/(?<![A-Za-z0-9])((?:NFR|FR|J|Q)-\d+)\b/i', $text, $matches) === 0) {
            return [];
        }

        return array_values(array_unique(array_map('strtoupper', $matches[1])));
    }

    /**
     * Ids defined more than once.
     *
     * @return list<string>
     */
    public static function duplicates(string $content): array
    {
        return array_keys(array_filter(
            self::defined($content),
            static fn (array $definition): bool => $definition['count'] > 1
        ));
    }

    /**
     * Ids the PRD cites and never defines. The revision history is left out:
     * it is the one place where a retired id may be named after it is gone.
     *
     * @return list<string>
     */
    public static function dangling(string $content): array
    {
        $defined = self::defined($content);

        return array_values(array_filter(
            self::cited(self::withoutRevisionHistory($content)),
            static fn (string $id): bool => ! isset($defined[$id])
        ));
    }

    public static function isValid(string $id): bool
    {
        return preg_match('/^(?:NFR|FR|J|Q)-\d+$/i', trim($id)) === 1;
    }

    public static function numberOf(string $id): int
    {
        return (int) substr((string) strrchr(trim($id), '-'), 1);
    }

    public static function typeOf(string $id): string
    {
        return strtoupper((string) strstr(trim($id), '-', true));
    }

    protected static function withoutRevisionHistory(string $content): string
    {
        $sections = preg_split('/^(?=##[ \t]+\S)/m', $content);

        if ($sections === false) {
            return $content;
        }

        $kept = array_filter($sections, static function (string $section): bool {
            $heading = strtok($section, "\n") ?: '';

            return preg_match('/^##[ \t]+.*(revision|revisioni|revisiones|révisions|history|cronologia|historial|historique)/iu', $heading) !== 1;
        });

        return implode('', $kept);
    }
}
