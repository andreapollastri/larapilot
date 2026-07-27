<?php

declare(strict_types=1);

namespace Larapilot\Services\Tracker;

use Larapilot\Services\ConfigService;
use Larapilot\Services\Tracker\Drivers\AsanaDriver;
use Larapilot\Services\Tracker\Drivers\ClickUpDriver;
use Larapilot\Services\Tracker\Drivers\JiraDriver;
use Larapilot\Services\Tracker\Drivers\LinearDriver;
use Larapilot\Services\Tracker\Drivers\MondayDriver;
use Larapilot\Services\Tracker\Drivers\TrelloDriver;

/**
 * Resolves the configured tracker driver and owns the translation between
 * Larapilot workflow statuses and the provider's own vocabulary.
 */
class TrackerManager
{
    /**
     * @var array<string, class-string<TrackerDriver>>
     */
    protected const DRIVERS = [
        'linear' => LinearDriver::class,
        'asana' => AsanaDriver::class,
        'jira' => JiraDriver::class,
        'trello' => TrelloDriver::class,
        'clickup' => ClickUpDriver::class,
        'monday' => MondayDriver::class,
    ];

    /**
     * Workflow keys in delivery order. Reverse status mapping resolves an
     * ambiguous remote label to the earliest matching status.
     */
    protected const WORKFLOW_ORDER = ['todo', 'planned', 'in_progress', 'review', 'done'];

    /**
     * Default status labels, used to look up a status map when a project has
     * renamed its workflow statuses in `.larapilot/config.yaml`.
     */
    protected const CANONICAL = [
        'todo' => 'TODO',
        'planned' => 'PLANNED',
        'in_progress' => 'IN PROGRESS',
        'review' => 'REVIEW',
        'done' => 'DONE',
    ];

    protected ?TrackerDriver $driver = null;

    public function __construct(protected ConfigService $config) {}

    public function enabled(): bool
    {
        return (bool) config('larapilot.enabled', true)
            && (bool) config('larapilot.tracker.enabled', false);
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys(self::DRIVERS);
    }

    public function provider(): ?string
    {
        $provider = strtolower(trim((string) config('larapilot.tracker.provider', '')));

        return $provider === '' || ! isset(self::DRIVERS[$provider]) ? null : $provider;
    }

    /**
     * Enabled, a known provider selected, and every credential present.
     */
    public function ready(): bool
    {
        return $this->enabled() && $this->provider() !== null && $this->missingConfig() === [];
    }

    /**
     * @return list<string>
     */
    public function missingConfig(): array
    {
        $driver = $this->tryDriver();

        return $driver === null ? [] : $driver->missingConfig();
    }

    public function tryDriver(): ?TrackerDriver
    {
        return $this->provider() === null ? null : $this->driver();
    }

    public function driver(): TrackerDriver
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $provider = $this->provider();

        if ($provider === null) {
            throw new TrackerException(
                'No project tracker selected. Set LARAPILOT_TRACKER_PROVIDER to one of: '
                .implode(', ', $this->available()).'.'
            );
        }

        $class = self::DRIVERS[$provider];

        return $this->driver = new $class($this->providerConfig($provider), $this->timeout());
    }

    /**
     * @return array<string, mixed>
     */
    public function providerConfig(string $provider): array
    {
        $config = config('larapilot.tracker.providers.'.$provider, []);

        return is_array($config) ? $config : [];
    }

    public function timeout(): int
    {
        return max(1, (int) config('larapilot.tracker.timeout', 15));
    }

    public function syncTasks(): bool
    {
        return (bool) config('larapilot.tracker.sync_tasks', true);
    }

    public function pullStatuses(): bool
    {
        return (bool) config('larapilot.tracker.pull.statuses', true);
    }

    public function pullComments(): bool
    {
        return (bool) config('larapilot.tracker.pull.comments', false);
    }

    /* ---------------------------------------------------------------------
     | Status translation
     |--------------------------------------------------------------------- */

    /**
     * The configured provider's status map, keyed by Larapilot status.
     *
     * @return array<string, string>
     */
    public function statusMap(): array
    {
        $provider = $this->provider();
        $map = $provider === null ? [] : ($this->providerConfig($provider)['status_map'] ?? []);
        $normalized = [];

        if (is_array($map)) {
            foreach ($map as $local => $remote) {
                if (is_string($local) && is_string($remote) && trim($remote) !== '') {
                    $normalized[strtoupper(trim($local))] = trim($remote);
                }
            }
        }

        return $normalized;
    }

    /**
     * The remote label a Larapilot status should land on.
     */
    public function remoteFor(string $localStatus): ?string
    {
        $map = $this->statusMap();
        $key = strtoupper(trim($localStatus));

        if (isset($map[$key])) {
            return $map[$key];
        }

        // The project may have renamed its statuses; fall back to the
        // canonical label for whichever workflow slot this status occupies.
        $slot = $this->slotFor($localStatus);

        return $slot === null ? null : ($map[self::CANONICAL[$slot]] ?? null);
    }

    /**
     * The Larapilot status a remote label suggests. Several statuses can map
     * to the same label (TODO and PLANNED both sit in "Todo"), so the
     * earliest workflow slot wins — callers compare forward first, and only
     * reach here when the story has genuinely drifted.
     */
    public function localFor(string $remoteStatus): ?string
    {
        $needle = mb_strtolower(trim($remoteStatus));
        $map = $this->statusMap();

        foreach (self::WORKFLOW_ORDER as $slot) {
            $canonical = self::CANONICAL[$slot];
            $resolved = $this->config->status($slot);

            foreach ([$canonical, strtoupper($resolved)] as $candidate) {
                $remote = $map[$candidate] ?? null;

                if ($remote !== null && mb_strtolower(trim($remote)) === $needle) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    /**
     * Whether a local status and a remote label already agree.
     */
    public function inSync(string $localStatus, ?string $remoteStatus): bool
    {
        $expected = $this->remoteFor($localStatus);

        if ($expected === null || $remoteStatus === null) {
            return false;
        }

        return mb_strtolower(trim($expected)) === mb_strtolower(trim($remoteStatus));
    }

    public function isDoneStatus(string $localStatus): bool
    {
        return strtoupper(trim($localStatus)) === strtoupper($this->config->status('done'));
    }

    /**
     * The workflow slot a status occupies (`todo`, `review`, …).
     */
    protected function slotFor(string $localStatus): ?string
    {
        $needle = strtoupper(trim($localStatus));

        foreach (self::WORKFLOW_ORDER as $slot) {
            if (strtoupper($this->config->status($slot)) === $needle) {
                return $slot;
            }
        }

        return null;
    }
}
