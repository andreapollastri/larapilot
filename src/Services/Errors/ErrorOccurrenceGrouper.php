<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors;

/**
 * The occurrences a tracker answers with, put together into the bugs
 * Larapilot triages: the same exception at the same line is one bug,
 * however many times it was thrown. A tracker that groups by itself
 * names the group, and its grouping is kept.
 */
class ErrorOccurrenceGrouper
{
    public const ERROR = 'error';

    public const OUTAGE = 'outage';

    /**
     * @param  list<array<string, mixed>>  $occurrences
     * @return list<array<string, mixed>>
     */
    public static function group(array $occurrences): array
    {
        $groups = [];

        foreach ($occurrences as $occurrence) {
            $group = trim((string) ($occurrence['group'] ?? ''));

            $signature = match (true) {
                $occurrence['kind'] === self::OUTAGE => self::OUTAGE.'|'.$occurrence['class'],
                $group !== '' => 'issue|'.$group,
                default => self::ERROR.'|'.$occurrence['class'].'|'.$occurrence['file'].'|'.$occurrence['line'],
            };

            $groups[substr(sha1($signature), 0, 10)][] = $occurrence;
        }

        $errors = [];

        foreach ($groups as $key => $of) {
            usort($of, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

            $latest = $of[0];
            $requests = array_count_values(array_filter(array_column($of, 'request')));
            arsort($requests);

            $codes = array_values(array_filter(array_column($of, 'code')));
            usort($codes, 'strnatcasecmp');

            $errors[] = [
                'key' => (string) $key,
                'kind' => $latest['kind'],
                'class' => $latest['class'],
                'short' => (string) substr((string) strrchr('\\'.$latest['class'], '\\'), 1),
                'message' => $latest['message'],
                'file' => $latest['file'],
                'line' => $latest['line'],
                'where' => $latest['file'] !== '' ? $latest['file'].($latest['line'] !== null ? ':'.$latest['line'] : '') : null,
                'in_vendor' => str_starts_with($latest['file'], 'vendor/'),
                'request' => $requests === [] ? null : (string) array_key_first($requests),
                'requests' => count($requests),
                'codes' => $codes,
                'count' => array_sum(array_map(static fn (array $occurrence): int => max(1, (int) ($occurrence['times'] ?? 1)), $of)),
                'unseen' => count(array_filter($of, static fn (array $occurrence): bool => ($occurrence['status'] ?? 'SEEN') === 'OPEN')),
                'first_seen' => end($of)['at'],
                'last_seen' => $latest['at'],
                'url' => $latest['url'] ?? null,
                'occurrences' => array_map(static fn (array $occurrence): array => [
                    'id' => $occurrence['id'],
                    'code' => $occurrence['code'],
                    'status' => $occurrence['status'] ?? 'SEEN',
                    'at' => $occurrence['at'],
                ], $of),
            ];
        }

        usort($errors, static fn (array $a, array $b): int => [$a['kind'] === self::OUTAGE ? 1 : 0, -$a['count'], $b['last_seen']]
            <=> [$b['kind'] === self::OUTAGE ? 1 : 0, -$b['count'], $a['last_seen']]);

        return $errors;
    }
}
