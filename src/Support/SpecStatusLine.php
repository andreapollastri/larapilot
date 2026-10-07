<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * The `**Status:**` the header line of a spec body carries
 * (`**Epic:** EP-001 | **Priority:** HIGH | **Points:** 3 | **Status:** TODO`).
 *
 * The skills write it once, as TODO, and the backlog moves the spec on: the
 * body said TODO on the dashboard, in the downloaded spec, and on a tracker
 * while the spec was DONE. Every change of status writes it here too.
 */
final class SpecStatusLine
{
    /**
     * The label and the value after it, up to the next `|` or the end of the
     * line, in the languages the skills write a spec in.
     */
    private const STATUS = '/(\*\*(?:Status|Stato|Estado|Statut):?\*\*:?[ \t]*)[^|\r\n]*?(?=[ \t]*(?:\||\r?$))/mi';

    /**
     * Only the head of the body is the header: a status named further down,
     * in a rework note or a quoted ticket, is left as it was written.
     */
    private const HEAD_LINES = 12;

    public static function write(string $body, string $status): string
    {
        if (preg_match('/\A(?:[^\n]*(?:\n|\z)){0,'.self::HEAD_LINES.'}/', $body, $head) !== 1) {
            return $body;
        }

        $updated = preg_replace_callback(
            self::STATUS,
            static fn (array $match): string => $match[1].$status,
            $head[0],
            1,
        );

        if ($updated === null || $updated === $head[0]) {
            return $body;
        }

        return $updated.substr($body, strlen($head[0]));
    }
}
