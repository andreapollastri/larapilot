<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Carbon;
use Larapilot\Support\ArtifactLanguage;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\SecurityRegisterStrings;

/**
 * The register of security findings as a document for the client: what is
 * open, what was resolved, and what was ignored with its reason. It is
 * written in the language of the PRD, like everything the client reads.
 *
 * The register says what Aikido reports and what the project decided. It
 * rates nothing and leaves nothing out: a finding ignored with no reason
 * kept here is listed, and says where its reason is.
 */
class AikidoRegisterWriter
{
    public function __construct(
        protected PrdService $prd,
        protected ConfigService $config,
    ) {}

    public function language(): string
    {
        return ArtifactLanguage::detect($this->prd->read());
    }

    /**
     * @param  array<string, mixed>  $register
     */
    public function filename(array $register): string
    {
        $name = (string) ($register['repository']['name'] ?? '');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii !== false ? $ascii : $name), '-'));

        return ($slug !== '' ? $slug.'-' : '')
            .SecurityRegisterStrings::line($this->language(), 'file')
            .'-'.Carbon::parse($register['generated_at'])->format('Y-m-d').'.md';
    }

    /**
     * @param  array<string, mixed>  $register
     */
    public function render(array $register): string
    {
        $lang = $this->language();
        $t = static fn (string $key, array $replace = []): string => SecurityRegisterStrings::line($lang, $key, $replace);
        $repository = $register['repository'];
        $name = (string) ($repository['name'] ?? '');

        $lines = ['# '.$t('title').($name !== '' ? ' — '.$name : ''), '', $t('lead'), ''];
        $lines[] = '- **'.$t('repository').':** '.$this->cell($name !== '' ? $name : '—').(($repository['url'] ?? '') !== '' ? ' ('.$repository['url'].')' : '');

        if (($repository['branch'] ?? '') !== '') {
            $lines[] = '- **'.$t('branch').':** '.$this->cell((string) $repository['branch']);
        }

        $lines[] = '- **'.$t('scanner').':** '.$t('scanner_value');
        $lines[] = '- **'.$t('last_scan').':** '.$this->day($repository['last_scanned_at'] ?? null);
        $lines[] = '- **'.$t('generated').':** '.Carbon::parse($register['generated_at'])->format('Y-m-d H:i');

        if ($register['truncated']) {
            $lines[] = '';
            $lines[] = '> '.$t('truncated');
        }

        $lines[] = '';
        $lines[] = '## '.$t('summary');
        $lines[] = '';
        $lines[] = '| '.$t('severity').' | '.$t('open').' | '.$t('resolved').' | '.$t('ignored').' |';
        $lines[] = '| --- | ---: | ---: | ---: |';

        foreach (AikidoService::SEVERITIES as $severity) {
            $lines[] = '| '.$t('severity_'.$severity)
                .' | '.$register['counts']['open'][$severity]
                .' | '.$register['counts']['resolved'][$severity]
                .' | '.$register['counts']['ignored'][$severity].' |';
        }

        $lines[] = '| **'.$t('total').'** | **'.$register['counts']['open']['all'].'** | **'.$register['counts']['resolved']['all'].'** | **'.$register['counts']['ignored']['all'].'** |';

        $lines = [...$lines, ...$this->section(
            $t('open_heading'),
            null,
            $t('none_open'),
            [$t('col_first_seen'), $t('col_decision')],
            $register['open'],
            fn (array $entry): array => [$this->day($entry['first_detected_at'] ?? null), $this->decision($entry, $lang)],
            $lang
        )];

        $lines = [...$lines, ...$this->section(
            $t('resolved_heading'),
            null,
            $t('none_resolved'),
            [$t('col_first_seen'), $t('col_resolved_on'), $t('col_resolved_by')],
            $register['resolved'],
            fn (array $entry): array => [
                $this->day($entry['first_detected_at'] ?? null),
                $this->day($entry['closed_at'] ?? null),
                ($entry['spec'] ?? null) !== null ? $t('resolved_spec', ['spec' => $entry['spec']]) : $t('resolved_scanner'),
            ],
            $lang
        )];

        $lines = [...$lines, ...$this->section(
            $t('ignored_heading'),
            $t('ignored_lead'),
            $t('none_ignored'),
            [$t('col_first_seen'), $t('col_ignored_on'), $t('col_reason')],
            $register['ignored'],
            fn (array $entry): array => [
                $this->day($entry['first_detected_at'] ?? null),
                $this->day($entry['ignored_at'] ?? null),
                $this->reason($entry, $lang),
            ],
            $lang
        )];

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = $t('footer');

        return implode("\n", $lines)."\n";
    }

    /**
     * Keep the register among the documents of the project.
     *
     * @param  array<string, mixed>  $register
     * @return array{path: string, relative: string}
     */
    public function write(array $register): array
    {
        $directory = rtrim($this->config->resolve()['paths']['security'] ?? '.larapilot/docs/security/', '/\\');
        $directory = $this->config->absolutePath($directory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'aikido-register.md';
        AtomicFile::write($path, $this->render($register));

        return ['path' => $path, 'relative' => str_replace('\\', '/', $this->config->relativePath($path))];
    }

    /**
     * One list of the register: a heading with its count, then a table
     * whose first columns are the same in every list.
     *
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $entries
     * @param  callable(array<string, mixed>): list<string>  $cells
     * @return list<string>
     */
    protected function section(string $heading, ?string $lead, string $none, array $columns, array $entries, callable $cells, string $lang): array
    {
        $t = static fn (string $key): string => SecurityRegisterStrings::line($lang, $key);
        $lines = ['', '## '.$heading.' ('.count($entries).')', ''];

        if ($entries === []) {
            $lines[] = $none;

            return $lines;
        }

        if ($lead !== null) {
            $lines[] = $lead;
            $lines[] = '';
        }

        $head = ['#', $t('severity'), $t('col_kind'), $t('col_finding'), 'CVE', ...$columns];
        $lines[] = '| '.implode(' | ', $head).' |';
        $lines[] = '|'.str_repeat(' --- |', count($head));

        foreach ($entries as $entry) {
            $row = [
                (string) $entry['id'],
                $t('severity_'.$entry['severity']),
                SecurityRegisterStrings::for($lang)['type_'.$entry['type']] ?? (string) $entry['type_label'],
                (string) $entry['title'],
                $entry['cves'] !== [] ? implode(', ', $entry['cves']) : '—',
                ...$cells($entry),
            ];

            $lines[] = '| '.implode(' | ', array_map(fn (string $cell): string => $this->cell($cell), $row)).' |';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function decision(array $entry, string $lang): string
    {
        $t = static fn (string $key, array $replace = []): string => SecurityRegisterStrings::line($lang, $key, $replace);

        if (($entry['status'] ?? '') === 'snoozed') {
            return ($entry['snoozed_until'] ?? null) !== null
                ? $t('decision_snoozed', ['date' => $this->day($entry['snoozed_until'])])
                : $t('decision_snoozed_open');
        }

        return ($entry['spec'] ?? null) !== null
            ? $t('decision_spec', ['spec' => $entry['spec']])
            : $t('decision_none');
    }

    /**
     * The reason kept here, as it was written. A finding ignored in Aikido
     * by hand has none here: the register says where it is.
     *
     * @param  array<string, mixed>  $entry
     */
    protected function reason(array $entry, string $lang): string
    {
        $reason = trim((string) ($entry['reason'] ?? ''));

        if ($reason !== '') {
            return $reason;
        }

        return SecurityRegisterStrings::line($lang, match ($entry['ignored_by'] ?? null) {
            'user' => 'reason_user',
            'rule' => 'reason_rule',
            'auto' => 'reason_auto',
            default => 'reason_unknown',
        });
    }

    protected function day(?string $iso): string
    {
        return $iso !== null && $iso !== '' ? Carbon::parse($iso)->format('Y-m-d') : '—';
    }

    /**
     * Text that stays in its cell: one line, and no bar that would open
     * another column.
     */
    protected function cell(string $text): string
    {
        return str_replace('|', '\\|', trim((string) preg_replace('/\s+/', ' ', $text)));
    }
}
