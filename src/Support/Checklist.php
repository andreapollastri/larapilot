<?php

declare(strict_types=1);

namespace Larapilot\Support;

final class Checklist
{
    /**
     * Tick every unchecked markdown task-list item ("- [ ]") in the text.
     */
    public static function tick(string $markdown): string
    {
        return preg_replace('/^(\s*(?:[-*+]|\d+[.)]) )\[ \]/m', '$1[x]', $markdown) ?? $markdown;
    }
}
