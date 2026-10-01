<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * A date of the plan — a milestone, the deadline of an epic — as the
 * forecast compares it: a plain day, `YYYY-MM-DD`.
 *
 * YAML reads a date written without quotes as a timestamp, so a payload
 * that says `deadline: 2027-01-29` arrives as a number. The forecast
 * compares days as strings, and a number is not one.
 */
final class PlanDate
{
    /**
     * Null when the value is not a date.
     */
    public static function day(mixed $value): ?string
    {
        if (is_int($value)) {
            return gmdate('Y-m-d', $value);
        }

        if (! is_string($value)) {
            return null;
        }

        $day = substr(trim($value), 0, 10);

        return self::isDay($day) ? $day : null;
    }

    public static function isDay(string $date): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $part) === 1
            && checkdate((int) $part[2], (int) $part[3], (int) $part[1]);
    }
}
