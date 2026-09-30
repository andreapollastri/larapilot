<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors\Drivers;

use Illuminate\Support\Facades\Process;
use Larapilot\Services\Errors\ErrorTrackerException;

/**
 * AWS CloudWatch Logs: the events of the last two weeks that match the
 * error filter of one log group, read with the AWS CLI already signed
 * in on this machine. One event is one throw.
 */
class CloudWatchDriver extends AbstractErrorDriver
{
    protected const MAX_EVENTS = 300;

    public function provider(): string
    {
        return 'cloudwatch';
    }

    public function label(): string
    {
        return 'AWS CloudWatch Logs';
    }

    public function host(): string
    {
        return 'https://'.$this->region().'.console.aws.amazon.com/cloudwatch/home?region='.$this->region().'#logsV2:log-groups';
    }

    protected function requiredConfig(): array
    {
        return [
            'log_group' => 'Set LARAPILOT_CLOUDWATCH_LOG_GROUP in .env: the name of the log group the application writes to.',
        ];
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('larapilot.errors.cloudwatch.'.$key, $default);
    }

    public function granularity(): string
    {
        return self::OCCURRENCE;
    }

    public function cacheKey(string $projectRoot): string
    {
        return $this->buildCacheKey($projectRoot, $this->logGroup(), $this->region(), (string) $this->config('filter', ''));
    }

    public function project(): ?array
    {
        if ($this->logGroup() === '') {
            return null;
        }

        return [
            'id' => $this->logGroup(),
            'title' => basename($this->logGroup()),
            'url' => $this->host(),
            'group' => 'AWS · '.$this->region(),
            'uptime' => false,
            'provider' => $this->provider(),
        ];
    }

    public function download(): array
    {
        $project = $this->project();

        if ($project === null) {
            throw new ErrorTrackerException('The log group of CloudWatch is not set.', 'Set LARAPILOT_CLOUDWATCH_LOG_GROUP in .env.');
        }

        [$start, $end] = $this->window();
        $filter = trim((string) $this->config('filter', '?ERROR ?Exception ?CRITICAL'));

        $command = [
            'aws', 'logs', 'filter-log-events',
            '--log-group-name', $this->logGroup(),
            '--start-time', (string) $start,
            '--end-time', (string) $end,
            '--filter-pattern', $filter,
            '--max-items', (string) self::MAX_EVENTS,
            '--region', $this->region(),
            '--output', 'json',
        ];

        $profile = trim((string) $this->config('profile'));

        if ($profile !== '') {
            $command[] = '--profile';
            $command[] = $profile;
        }

        $result = Process::timeout(max(15, $this->timeout()))->run($command);

        if ($result->failed()) {
            $detail = trim($result->errorOutput());

            throw new ErrorTrackerException(
                'CloudWatch Logs could not be read.',
                $detail !== '' ? mb_substr($this->helper->scrub($detail), 0, 240) : 'Install the AWS CLI and sign in (`aws configure`, or AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY in the environment).'
            );
        }

        $decoded = json_decode($result->output(), true);
        $events = is_array($decoded['events'] ?? null) ? $decoded['events'] : [];
        $occurrences = [];

        foreach ($events as $event) {
            if (is_array($event)) {
                $occurrences[] = $this->fromEvent($event);
            }
        }

        return $this->pack($project, $occurrences, isset($decoded['NextToken']) || count($events) >= self::MAX_EVENTS);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function fromEvent(array $event): array
    {
        $message = trim((string) ($event['message'] ?? ''));
        $class = preg_match('/(?P<class>[A-Za-z0-9_\\\\]+(?:Exception|Error))\b/', $message, $match) === 1 ? $match['class'] : 'LogError';
        [$file, $line] = $this->helper->frame($message);
        $id = trim((string) ($event['eventId'] ?? ''));

        if ($id === '') {
            $id = sha1($message.'|'.(string) ($event['timestamp'] ?? ''));
        }

        return $this->occurrence(
            $id,
            null,
            $class,
            $message,
            $file,
            $line,
            $event['timestamp'] ?? null,
        );
    }

    protected function logGroup(): string
    {
        return trim((string) $this->config('log_group'));
    }

    protected function region(): string
    {
        $region = trim((string) $this->config('region', 'eu-west-1'));

        return $region !== '' ? $region : 'eu-west-1';
    }
}
