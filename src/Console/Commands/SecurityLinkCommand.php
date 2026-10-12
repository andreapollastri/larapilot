<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SecurityService;
use Larapilot\Support\SecurityCommand;
use Larapilot\Support\SpecCode;
use Throwable;

class SecurityLinkCommand extends SecurityCommand
{
    protected $signature = 'larapilot:security-link
                            {issues : The findings, by id, separated by commas — numbers in Aikido, UUIDs in Hackly}
                            {--spec= : The spec of the backlog that fixes them, e.g. US-012}
                            {--waive : Accept the findings as they are instead of fixing them}
                            {--false-positive : The findings are not real — with --reason}
                            {--reason= : Why they are accepted or not real — required with --waive and --false-positive}
                            {--forget : Drop what was decided about them}
                            {--local : Keep the decision here and do not tell the scanner}';

    protected $description = 'Record what was decided about security findings — the spec that fixes them, or why they are waived or false positives — and tell the scanner';

    public function handle(SecurityService $security): int
    {
        if (($off = $this->scannerOff($security)) !== null) {
            return $off;
        }

        $spec = trim((string) $this->option('spec'));
        $waive = (bool) $this->option('waive');
        $falsePositive = (bool) $this->option('false-positive');
        $chosen = count(array_filter([$spec !== '', $waive || $falsePositive, (bool) $this->option('forget')]));

        if ($chosen !== 1) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Say one thing about the findings: --spec=US-XXX, --waive --reason="…", --false-positive --reason="…", or --forget.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        if ($spec !== '' && ! SpecCode::isValid($spec)) {
            return $this->failure('E_INVALID_INPUT', "Invalid spec code: {$spec}.", $this->exitForCode('E_INVALID_INPUT'));
        }

        $ids = [(string) $this->argument('issues')];
        $push = ! (bool) $this->option('local');

        try {
            $result = match (true) {
                $spec !== '' => $security->link($ids, $spec, $push),
                $waive || $falsePositive => $security->waive($ids, (string) $this->option('reason'), $push, $falsePositive),
                default => $security->forget($ids, $push),
            };
        } catch (Throwable $e) {
            return $this->failed($security, $e);
        }

        $kept = array_values(array_filter($result['told'], static fn (array $told): bool => ($told['kept'] ?? false) === true));

        if ($kept !== []) {
            return $this->failure(
                'E_CONNECTOR',
                $security->label().' did not take back the decision on '.implode(', ', array_column($kept, 'id')).': '.$kept[0]['error'],
                $this->exitForCode('E_CONNECTOR'),
                'The decision is kept, so the project and '.$security->label().' say the same. Run the command again, or with --local to drop it here only.',
                $result
            );
        }

        $refused = array_values(array_filter($result['told'], static fn (array $told): bool => ! $told['sent']));

        return $this->success('security_link', $result + [
            'hint' => $refused !== [] ? $refused[0]['error'].' '.($refused[0]['hint'] ?? '') : null,
        ]);
    }
}
