<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\NotifyService;
use Larapilot\Services\PlanService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecApproveCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-approve
                            {code : Spec code}
                            {--commit= : Optional merge commit SHA to link (auto-detected from recent history when omitted)}
                            {--force : Approve even with blocking feedback or incomplete plan tasks}';

    protected $description = 'Mark a reviewed spec as DONE after human approval';

    public function handle(
        SpecService $specs,
        ConfigService $config,
        PlanService $plans,
        InternalFeedbackService $feedback,
        NotifyService $notify,
    ): int {
        $code = (string) $this->argument('code');
        $spec = $specs->find($code);

        if ($spec === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        if (($guard = $this->guardStatus($spec, [$config->status('review')], 'approve')) !== null) {
            return $guard;
        }

        if (! (bool) $this->option('force')) {
            $blocking = $feedback->blockingCount($code);

            if ($blocking > 0) {
                return $this->failure(
                    'E_PRECONDITION',
                    "Spec {$code} has {$blocking} blocking feedback comment(s).",
                    $this->exitForCode('E_PRECONDITION'),
                    'Resolve the [blocks-merge] comments (or rework via spec-request-changes), or pass --force to override.'
                );
            }

            $progress = $plans->taskProgress($code);

            if ($progress['total'] > 0 && $progress['done'] < $progress['total']) {
                return $this->failure(
                    'E_PRECONDITION',
                    "Spec {$code} has incomplete plan tasks ({$progress['done']}/{$progress['total']} done).",
                    $this->exitForCode('E_PRECONDITION'),
                    'Finish the remaining tasks via larapilot:task-done, or pass --force to override.'
                );
            }
        }

        $commitOption = $this->option('commit');
        $commitSha = is_string($commitOption) && $commitOption !== '' ? $commitOption : null;

        try {
            $commit = $specs->approve($code, $commitSha);
        } catch (\RuntimeException $exception) {
            return $this->failure('E_NOT_FOUND', $exception->getMessage(), $this->exitForCode('E_NOT_FOUND'));
        }

        $title = trim((string) ($spec['title'] ?? $code));
        $notification = $this->safeNotify($notify, [
            'event' => 'spec_done',
            'title' => "{$code} approved — {$title}",
            'body' => 'Status: '.$config->status('done'),
            'url' => is_array($commit) ? ($commit['url'] ?? null) : null,
        ]);

        return $this->success('approve_result', [
            'code' => $code,
            'status' => $config->status('done'),
            'merge_commit' => $commit,
            'notification' => $notification,
        ]);
    }

    /**
     * @param  array{event: string, title: string, body?: string|null, url?: string|null}  $payload
     * @return array<string, mixed>|null
     */
    protected function safeNotify(NotifyService $notify, array $payload): ?array
    {
        try {
            return $notify->send($payload);
        } catch (\Throwable) {
            return null;
        }
    }
}
