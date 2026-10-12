<?php

declare(strict_types=1);

namespace Larapilot\Services;

use InvalidArgumentException;
use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\Hackly\HacklyException;
use RuntimeException;
use Throwable;

/**
 * The security findings of this repository, from the scanner the project
 * chose: Aikido (SaaS) or Hackly (self-hosted). One front for the
 * `larapilot:security-*` commands, the Security page, and the skills: the
 * same calls, the same shapes, whichever scanner answers. A finding is an
 * integer in Aikido and a UUID in Hackly; both travel as they are.
 */
class SecurityService
{
    /**
     * @var array<string, array{label: string, url: string, kind: string}>
     */
    public const PROVIDERS = [
        'aikido' => ['label' => 'Aikido', 'url' => 'https://www.aikido.dev', 'kind' => 'SaaS'],
        'hackly' => ['label' => 'Hackly', 'url' => 'https://hackly.web.ap.it', 'kind' => 'self-hosted'],
    ];

    public function __construct(
        protected ConfigService $config,
        protected AikidoService $aikido,
        protected HacklyService $hackly,
        protected AikidoRegisterWriter $writer,
    ) {}

    public function enabled(): bool
    {
        return $this->provider() !== '';
    }

    /**
     * `aikido`, `hackly`, or an empty string when the findings are off.
     */
    public function provider(): string
    {
        return $this->config->securityProvider();
    }

    public function label(?string $provider = null): string
    {
        $provider ??= $this->provider();

        return self::PROVIDERS[$provider]['label'] ?? 'the scanner';
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return ['provider' => $this->provider()] + $this->scanner()->status();
    }

    /**
     * @param  array{severity?: string|null, type?: string|null, new?: bool, limit?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function findings(array $filters = [], bool $fresh = true): array
    {
        $findings = $this->scanner()->findings($filters, $fresh);

        return ['provider' => $this->provider(), 'severities' => $findings['severities'] ?? AikidoService::SEVERITIES] + $findings;
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    public function link(array $ids, string $spec, bool $push = true): array
    {
        return $this->told($this->scanner()->link($this->ids($ids), $spec, $push));
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    public function waive(array $ids, string $reason, bool $push = true, bool $falsePositive = false): array
    {
        $scanner = $this->scanner();

        return $this->told($scanner instanceof HacklyService
            ? $scanner->waive($this->ids($ids), $reason, $push, $falsePositive)
            : $scanner->waive($this->ids($ids), ($falsePositive ? 'False positive: ' : '').$reason, $push));
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    public function forget(array $ids, bool $push = true): array
    {
        return $this->told($this->scanner()->forget($this->ids($ids), $push));
    }

    /**
     * @return array<string, mixed>
     */
    public function push(): array
    {
        return $this->told($this->scanner()->push());
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    public function plan(array $ids): array
    {
        return ['provider' => $this->provider()] + $this->scanner()->resolutionPlan($this->ids($ids));
    }

    /**
     * @return array<string, mixed>
     */
    public function scan(?string $profile = null, ?bool $includeTargets = null): array
    {
        $scanner = $this->scanner();

        return ['provider' => $this->provider()] + ($scanner instanceof HacklyService ? $scanner->scan($profile, $includeTargets) : $scanner->scan());
    }

    /**
     * One finding as a document a person or an AI can act on: Hackly's own
     * brief and fix prompt, or what Aikido's list holds about it.
     *
     * @return array<string, mixed>
     */
    public function detail(string $id): array
    {
        $scanner = $this->scanner();

        if ($scanner instanceof HacklyService) {
            return ['provider' => 'hackly'] + $scanner->detail($id);
        }

        foreach ($scanner->findings([], false)['issues'] as $issue) {
            if ((string) $issue['id'] === trim($id, ' #')) {
                $markdown = implode("\n", array_filter([
                    '# '.$issue['title'],
                    '',
                    '- **Severity:** '.$issue['severity'],
                    '- **Kind:** '.$issue['type_label'],
                    $issue['where'] !== [] ? '- **Where:** '.implode(', ', $issue['where']) : null,
                    $issue['cves'] !== [] ? '- **CVE:** '.implode(', ', $issue['cves']) : null,
                    '',
                    $issue['description'],
                ], static fn (?string $line): bool => $line !== null));

                return ['provider' => 'aikido', 'id' => $issue['id'], 'markdown' => $markdown, 'prompt' => trim($issue['how_to_fix'])];
            }
        }

        throw new InvalidArgumentException('Aikido reports no open finding #'.$id.' for this repository.');
    }

    /**
     * @return array<string, mixed>
     */
    public function repositories(string $search = ''): array
    {
        return ['provider' => $this->provider()] + $this->scanner()->repositories($search);
    }

    /**
     * @return array<string, mixed>
     */
    public function useRepository(string $named): array
    {
        return $this->scanner()->useRepository($named);
    }

    public function forgetRepository(): void
    {
        $this->scanner()->forgetRepository();
    }

    /**
     * @param  array<string, mixed>  $findings
     * @return array{path: string, relative: string}
     */
    public function writeReport(array $findings): array
    {
        return $this->scanner()->writeReport($findings);
    }

    /**
     * @param  array<string, mixed>  $findings
     */
    public function report(array $findings): string
    {
        return $this->scanner()->report($findings);
    }

    /**
     * @return array<string, mixed>
     */
    public function register(bool $fresh = false): array
    {
        $register = $this->scanner()->register($fresh);

        return $register + ['scanner' => $this->provider(), 'severities' => $this->provider() === 'hackly' ? HacklyService::SEVERITIES : AikidoService::SEVERITIES];
    }

    /**
     * @param  array<string, mixed>  $register
     * @return array{path: string, relative: string}
     */
    public function writeRegister(array $register): array
    {
        return $this->writer->write($register);
    }

    /**
     * @param  array<string, mixed>  $register
     * @return array{content: string, filename: string}
     */
    public function renderRegister(array $register): array
    {
        return ['content' => $this->writer->render($register), 'filename' => $this->writer->filename($register)];
    }

    /**
     * Whether a failure is the scanner's — unreachable, refusing, not
     * configured — rather than a wrong argument.
     */
    public function isScannerError(Throwable $e): bool
    {
        return $e instanceof AikidoException || $e instanceof HacklyException;
    }

    public function hint(Throwable $e): ?string
    {
        return $e instanceof AikidoException || $e instanceof HacklyException ? $e->hint() : null;
    }

    public function scanner(): AikidoService|HacklyService
    {
        return match ($this->provider()) {
            'aikido' => $this->aikido,
            'hackly' => $this->hackly,
            default => throw new RuntimeException('No security scanner is set for this project.'),
        };
    }

    /**
     * Ids as the scanner names them: numbers in Aikido, UUIDs in Hackly.
     *
     * @param  list<int|string>  $ids
     * @return list<int|string>
     */
    protected function ids(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            foreach (preg_split('/[\s,]+/', trim((string) $id)) ?: [] as $part) {
                $part = trim($part, " \t#");

                if ($part !== '') {
                    $clean[] = $this->provider() === 'aikido' && ctype_digit($part) ? (int) $part : $part;
                }
            }
        }

        return $clean;
    }

    /**
     * The answer of a decision, with what the scanner was told under one key.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function told(array $result): array
    {
        $told = $result['aikido'] ?? $result['hackly'] ?? [];
        unset($result['aikido'], $result['hackly']);

        return ['provider' => $this->provider()] + $result + ['told' => is_array($told) ? $told : []];
    }
}
