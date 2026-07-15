<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecCommentCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-comment
                            {code : Spec code}
                            {--author= : Comment author label (e.g. PM, Dev)}
                            {--message= : Comment markdown}
                            {--blocks-merge : Mark this comment as blocking merge / rework}';

    protected $description = 'Append an internal feedback comment to a spec';

    public function handle(
        SpecService $specs,
        InternalFeedbackService $feedback,
        ConfigService $config,
    ): int {
        if (! $feedback->enabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Internal feedback comments are disabled.',
                $this->exitForCode('E_PRECONDITION'),
                'Set LARAPILOT_COMMENTS_ENABLED=true in your environment.'
            );
        }

        $code = (string) $this->argument('code');
        $spec = $specs->find($code);

        if ($spec === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        if (! $feedback->canComment($spec)) {
            return $this->failure(
                'E_PRECONDITION',
                'Comments are closed for this spec.',
                $this->exitForCode('E_PRECONDITION'),
                'Comments are disabled on DONE specs.'
            );
        }

        $author = trim((string) $this->option('author'));
        $message = trim((string) $this->option('message'));

        if ($author === '' || $message === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Both --author and --message are required.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $feedback->append(
            $code,
            $author,
            $message,
            strtoupper((string) ($spec['status'] ?? $config->status('todo'))),
            (bool) $this->option('blocks-merge')
        );

        $summary = $feedback->summary($code, $spec);

        return $this->success('comment_result', [
            'code' => $code,
            'path' => $summary['path'],
            'entry_count' => $summary['entry_count'],
            'blocking_count' => $summary['blocking_count'],
        ]);
    }
}
