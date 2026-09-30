<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors;

/**
 * One error tracker, read the same way whichever it is: what it needs
 * from `.env`, the project this application is there, the open errors
 * as occurrences Larapilot can put together into bugs, and — when the
 * tracker allows it — closing an error there once the fix is released.
 */
interface ErrorTrackerDriver
{
    /** One row for each time an exception was thrown (Boogle, logs). */
    public const OCCURRENCE = 'occurrence';

    /** One row for each bug, with how many times it was thrown (Sentry, Flare, …). */
    public const ISSUE = 'issue';

    /** The name of the provider as `settings.errors_provider` holds it. */
    public function provider(): string;

    /** The name of the tracker as a person says it. */
    public function label(): string;

    /** Where the tracker is reached, for the status line; empty when unknown. */
    public function host(): string;

    /**
     * What is still missing in `.env` before the tracker can be read: one
     * sentence for each thing, naming the variable.
     *
     * @return list<string>
     */
    public function missingConfig(): array;

    public function configured(): bool;

    /**
     * The project this application is in the tracker — `id`, `title`,
     * `url`, `group`, `uptime`, `provider` — or null when none matches.
     *
     * @return array<string, mixed>|null
     */
    public function project(): ?array;

    /** How to name the project when none matches, in a sentence. */
    public function projectHint(): string;

    /**
     * Whether `project()` asks the tracker, and so proves the credentials
     * are taken. When it reads `.env` alone, only asking for the errors
     * proves it.
     */
    public function readsProjectFromTracker(): bool;

    /**
     * The open errors, put together into bugs.
     *
     * @return array{project: array<string, mixed>, errors: list<array<string, mixed>>, fetched_at: string, truncated: bool, granularity: string}
     */
    public function download(): array;

    public function cacheKey(string $projectRoot): string;

    /** What one row of the tracker is: OCCURRENCE or ISSUE. */
    public function granularity(): string;

    /** Whether the tracker also watches uptime and reports outages. */
    public function supportsOutages(): bool;

    /** Whether an error can be closed in the tracker from Larapilot. */
    public function supportsRemoteResolve(): bool;

    /**
     * Close one occurrence — one issue, for a tracker that groups them
     * itself — in the tracker.
     *
     * @param  array<string, mixed>  $occurrence  one row of a grouped error
     * @param  array<string, mixed>  $project
     */
    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void;
}
