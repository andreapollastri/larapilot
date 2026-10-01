<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * The `**Blocked by:**` line of a spec body: the specs a story waits for.
 *
 * The body is the one place the line lives — the forecast reads it there, and
 * a re-plan writes it back there, so the two never disagree.
 */
final class SpecBlockers
{
    private const LINE = '/^([ \t>*-]*\*\*Blocked by:?\*\*:?)[^\n]*$/mi';

    /**
     * The line the template puts next to it: priority, points, epic.
     */
    private const HEADER = '/^[^\n]*\*\*(?:Priority|Points|Epic):?\*\*[^\n]*$/mi';

    /**
     * Codes the body names, upper case. Null when the body has no such line,
     * which is not the same as a line that names nothing.
     *
     * @return list<string>|null
     */
    public static function read(string $body): ?array
    {
        if (preg_match_all(self::LINE, $body, $lines) < 1) {
            return null;
        }

        preg_match_all('/[A-Za-z][A-Za-z0-9]*-\d+/', implode(' ', $lines[0]), $codes);

        return array_values(array_unique(array_map('strtoupper', $codes[0])));
    }

    /**
     * The body with its line naming these codes, and `-` for none. A body
     * without the line gets one under its header, or on top.
     *
     * @param  list<string>  $codes
     */
    public static function write(string $body, array $codes): string
    {
        $value = $codes === [] ? '-' : implode(', ', $codes);

        if (preg_match(self::LINE, $body) === 1) {
            return (string) preg_replace_callback(
                self::LINE,
                static fn (array $match): string => $match[1].' '.$value,
                $body
            );
        }

        $line = '**Blocked by:** '.$value;

        if (preg_match(self::HEADER, $body, $header, PREG_OFFSET_CAPTURE) === 1) {
            $at = $header[0][1] + strlen($header[0][0]);

            return substr($body, 0, $at)."\n".$line.substr($body, $at);
        }

        return $line."\n\n".$body;
    }
}
