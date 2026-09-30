<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Larapilot\Services\Errors\ErrorDataHelper;
use Larapilot\Services\Errors\ErrorOccurrenceGrouper;
use Larapilot\Services\Errors\ErrorTrackerDriver;
use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * What the drivers share: reading `.env`, one HTTP client, one way to
 * say a call failed, and one shape for an occurrence, scrubbed.
 */
abstract class AbstractErrorDriver implements ErrorTrackerDriver
{
    public function __construct(protected ErrorDataHelper $helper) {}

    /**
     * What has to be set, and the sentence that says how, for each key
     * of the config the driver reads.
     *
     * @return array<string, string>
     */
    abstract protected function requiredConfig(): array;

    abstract protected function config(string $key, mixed $default = null): mixed;

    public function missingConfig(): array
    {
        $missing = [];

        foreach ($this->requiredConfig() as $key => $hint) {
            $value = $this->config($key);

            if (! is_scalar($value) || trim((string) $value) === '') {
                $missing[] = $hint;
            }
        }

        return $missing;
    }

    public function configured(): bool
    {
        return $this->missingConfig() === [];
    }

    public function host(): string
    {
        return '';
    }

    public function projectHint(): string
    {
        return 'Check the '.$this->label().' settings in .env.';
    }

    public function readsProjectFromTracker(): bool
    {
        return false;
    }

    public function granularity(): string
    {
        return self::ISSUE;
    }

    public function supportsOutages(): bool
    {
        return false;
    }

    public function supportsRemoteResolve(): bool
    {
        return false;
    }

    public function resolveOccurrence(array $occurrence, string $status, string $comment, array $project): void
    {
        throw new ErrorTrackerException(
            $this->label().' cannot close an error from Larapilot.',
            'Close it in '.$this->label().' itself; what was decided here stays in the ledger.'
        );
    }

    protected function timeout(): int
    {
        return max(1, (int) config('larapilot.errors.timeout', 15));
    }

    protected function client(): PendingRequest
    {
        return Http::timeout($this->timeout())->acceptJson();
    }

    /**
     * The body of an answer, or the failure it was.
     *
     * @return array<array-key, mixed>
     */
    protected function json(Response $response, string $context): array
    {
        $this->check($response, $context);

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    protected function check(Response $response, string $context): void
    {
        if ($response->failed()) {
            throw new ErrorTrackerException(
                $context.' answered '.$response->status().'.',
                trim($response->body()) !== '' ? mb_substr($this->helper->scrub($response->body()), 0, 240) : null,
                $response->status()
            );
        }
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  list<array<string, mixed>>  $occurrences
     * @return array{project: array<string, mixed>, errors: list<array<string, mixed>>, fetched_at: string, truncated: bool, granularity: string}
     */
    protected function pack(array $project, array $occurrences, bool $truncated = false): array
    {
        return [
            'project' => $project,
            'errors' => ErrorOccurrenceGrouper::group($occurrences),
            'fetched_at' => now()->toIso8601String(),
            'truncated' => $truncated,
            'granularity' => $this->granularity(),
        ];
    }

    protected function buildCacheKey(string $projectRoot, string ...$parts): string
    {
        return 'larapilot.errors.'.$this->provider().'.'.sha1(implode('|', [$projectRoot, ...$parts]));
    }

    /**
     * One occurrence as every driver hands it over: the message scrubbed,
     * the file read as one of the repository, the moment as ISO 8601.
     * `times` is how many throws the row stands for, `group` the name the
     * tracker gives the bug when it groups by itself.
     *
     * @return array<string, mixed>
     */
    protected function occurrence(
        string $id,
        ?string $code,
        string $class,
        string $message,
        string $file,
        ?int $line,
        mixed $at,
        ?string $request = null,
        string $kind = ErrorOccurrenceGrouper::ERROR,
        string $status = 'SEEN',
        int $times = 1,
        ?string $group = null,
        ?string $url = null,
    ): array {
        return [
            'id' => $id,
            'code' => $code,
            'kind' => $kind,
            'class' => $class !== '' ? $class : 'Error',
            'message' => $this->helper->scrub($message),
            'file' => $this->helper->place($file),
            'line' => $line !== null && $line > 0 ? $line : null,
            'status' => $status,
            'request' => $request,
            'at' => $this->helper->moment($at),
            'times' => max(1, $times),
            'group' => $group,
            'url' => $url,
        ];
    }

    protected function wrapConnection(callable $call): mixed
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw new ErrorTrackerException('Could not reach '.$this->label().'.', $e->getMessage());
        }
    }

    /**
     * A window of the last days as milliseconds since the epoch, for the
     * trackers that ask for one.
     *
     * @return array{0: int, 1: int}
     */
    protected function window(int $days = 14): array
    {
        return [now()->subDays($days)->getTimestamp() * 1000, now()->getTimestamp() * 1000];
    }
}
