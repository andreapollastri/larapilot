<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * How a spec is turned into hours: the hours of its plan first, its story
 * points when it has no plan, and a default size when it has neither.
 *
 * Economics prices the backlog with this rule and Usage sets it beside the
 * time a spec took, so the two always name the same estimate.
 */
final class EffortEstimate
{
    /**
     * Size assumed for a spec that carries neither a plan nor story points.
     */
    public const DEFAULT_SPEC_POINTS = 3;

    /**
     * Hours a story point stands for under each `settings.effort`.
     */
    public static function settingHoursPerPoint(string $effort): float
    {
        return match (strtoupper(trim($effort))) {
            'ECO' => 3.0,
            'MAX' => 5.5,
            default => 4.0,
        };
    }

    /**
     * Plans are the strongest estimate the project has. Once enough of the
     * backlog is planned, the rest of the story points convert at the rate
     * those plans actually imply instead of the generic effort constant.
     */
    public static function calibratedHoursPerPoint(float $plannedHours, int $plannedPoints, int $plannedSpecs): ?float
    {
        if ($plannedSpecs < 2 || $plannedPoints < 5 || $plannedHours <= 0) {
            return null;
        }

        return min(12.0, max(0.5, round($plannedHours / $plannedPoints, 2)));
    }

    /**
     * @return array{hours: float, from: 'plan'|'points'|'unsized'}
     */
    public static function specHours(float $planHours, int $points, float $hoursPerPoint): array
    {
        if ($planHours > 0) {
            return ['hours' => $planHours, 'from' => 'plan'];
        }

        if ($points > 0) {
            return ['hours' => $points * $hoursPerPoint, 'from' => 'points'];
        }

        return ['hours' => self::DEFAULT_SPEC_POINTS * $hoursPerPoint, 'from' => 'unsized'];
    }
}
