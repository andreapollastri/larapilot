<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\UsageService;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\LarapilotCommand;

class UsageReportCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:usage-report
                            {--format=json : json|md|human}
                            {--output= : Write Markdown report to this path}
                            {--category= : Filter by category}
                            {--user= : Filter by user substring}
                            {--skill= : Filter by skill substring}
                            {--spec= : Filter by US-XXX}
                            {--from= : Start date YYYY-MM-DD or ISO}
                            {--to= : End date YYYY-MM-DD or ISO}
                            {--limit= : Limit to the last N matching entries}
                            {--insights : Include Lucille insights (top categories, hot specs, deadline drift)}';

    protected $description = 'Query and summarize Lucille usage ledger (tokens, time, schedule)';

    public function handle(UsageService $usage): int
    {
        try {
            $filters = $this->filters();
            $format = strtolower((string) $this->option('format'));
            $markdown = $usage->reportMarkdown($filters);
            $summary = $usage->summary($filters);
            $insights = (bool) $this->option('insights') ? $usage->insights($filters) : null;

            if ($output = $this->option('output')) {
                AtomicFile::write((string) $output, $markdown);
            }

            if ($format === 'md') {
                $this->line($markdown);

                return self::SUCCESS;
            }

            if ($format === 'human') {
                $this->table(['Metric', 'Value'], [
                    ['Entries', (string) $summary['entry_count']],
                    ['Tokens', (string) $summary['total_tokens']],
                    ['Minutes', (string) $summary['total_minutes']],
                    ['Hours', (string) $summary['total_hours']],
                    ['Avg min/entry', (string) $summary['avg_minutes_per_entry']],
                ]);

                if ($insights !== null && ($insights['top_categories'] ?? []) !== []) {
                    $this->newLine();
                    $this->table(
                        ['Category', 'Minutes', 'Share %', 'Tokens'],
                        array_map(
                            static fn (array $row): array => [
                                (string) $row['category'],
                                (string) $row['minutes'],
                                (string) $row['share_minutes'],
                                (string) $row['tokens'],
                            ],
                            $insights['top_categories']
                        )
                    );
                }

                return self::SUCCESS;
            }

            return $this->success('usage_report', [
                'summary' => $summary,
                'schedule' => $usage->schedule(),
                'gantt' => $usage->gantt(),
                'entries' => $usage->query($filters),
                'insights' => $insights,
                'markdown' => $markdown,
                'output' => $this->option('output'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        $limit = $this->option('limit');

        return array_filter([
            'category' => $this->option('category'),
            'user' => $this->option('user'),
            'skill' => $this->option('skill'),
            'spec' => $this->option('spec'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'limit' => is_numeric($limit) ? (int) $limit : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
