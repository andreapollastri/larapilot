<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\NotifyService;
use Larapilot\Support\LarapilotCommand;

class NotifyCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:notify
                            {--event=custom : Event name (task_done, spec_done, pr_opened, …)}
                            {--title= : Short title (required)}
                            {--body= : Optional body text}
                            {--url= : Optional link (PR URL, dashboard, …)}';

    protected $description = 'Fan-out a Larapilot notification to enabled Slack/Discord/Telegram channels';

    public function handle(NotifyService $notify): int
    {
        $title = trim((string) $this->option('title'));

        if ($title === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide --title.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        try {
            $result = $notify->send([
                'event' => $this->option('event'),
                'title' => $title,
                'body' => $this->option('body'),
                'url' => $this->option('url'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        return $this->success('notify_result', $result);
    }
}
