<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\Envelope;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Workflow hooks: the project's own commands and skills, attached to the
 * moments of the delivery loop in `.larapilot/hooks.yaml`.
 *
 * A `run` hook is a shell command the Artisan command of its event runs
 * itself, so no agent can forget it. `before` runs ahead of the write and a
 * failure blocks the transition; `after` runs once the state is written, and
 * a failure is reported, never undone. A `skill` hook is a skill the agent
 * runs at that moment: the CLI cannot run it, so a transition whose `before`
 * skills were not reported done is refused, and the `after` ones are listed
 * in the answer.
 *
 * Nothing runs while `settings.hooks` is NO, or on a machine where
 * LARAPILOT_HOOKS_ENABLED is false. While hooks are on, a file with errors
 * refuses every transition: a gate that cannot be read is not passed.
 */
class HookService
{
    /**
     * Each event, and what fires it.
     *
     * @var array<string, string>
     */
    public const EVENTS = [
        'prd.written' => 'larapilot:prd-write',
        'spec.added' => 'larapilot:spec-add',
        'spec.planned' => 'larapilot:spec-plan',
        'spec.started' => 'larapilot:spec-start',
        'task.done' => 'larapilot:task-done',
        'spec.review' => 'larapilot:spec-review',
        'spec.approved' => 'larapilot:spec-approve',
        'spec.changes_requested' => 'larapilot:spec-request-changes',
        'release.shipped' => 'larapilot:release-ship',
        'ship' => 'larapilot:hook-run ship (/larapilot-ship)',
    ];

    /**
     * @var list<string>
     */
    public const PHASES = ['before', 'after'];

    /**
     * Longest a hook may run, in seconds, whatever it asks for.
     */
    public const MAX_TIMEOUT = 3600;

    /**
     * What an answer keeps of a hook's output. The log keeps all of it.
     */
    protected const OUTPUT_LINES = 40;

    protected const OUTPUT_BYTES = 4000;

    /**
     * @var list<string>
     */
    protected const KEYS = ['name', 'run', 'skill', 'timeout', 'blocking'];

    public function __construct(
        protected ConfigService $config,
    ) {}

    /**
     * The machine-level switch, `LARAPILOT_HOOKS_ENABLED`.
     */
    public function machineEnabled(): bool
    {
        $value = config('larapilot.hooks.enabled', true);

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * Whether hooks run: the project turned them on, and this machine lets them.
     */
    public function active(): bool
    {
        return $this->config->hooksEnabled() && $this->machineEnabled();
    }

    /**
     * Whether `doctor` should call the hooks healthy: off, or a file that reads.
     */
    public function healthy(): bool
    {
        return ! $this->active() || $this->definitions()['ok'];
    }

    /**
     * The hooks the file defines, normalized, and what is wrong with it.
     *
     * @return array{
     *     exists: bool,
     *     ok: bool,
     *     hooks: array<string, array<string, list<array{name: string, kind: string, run: string|null, skill: string|null, timeout: int, blocking: bool}>>>,
     *     findings: list<array{code: string, severity: string, path: string, message: string, hint: string}>
     * }
     */
    public function definitions(): array
    {
        $path = $this->config->hooksPath();
        $exists = is_file($path);
        $hooks = [];
        $findings = [];

        if ($exists) {
            $hooks = $this->parse((string) file_get_contents($path), $findings);
        }

        return [
            'exists' => $exists,
            'ok' => ! in_array('error', array_column($findings, 'severity'), true),
            'hooks' => $hooks,
            'findings' => $findings,
        ];
    }

    /**
     * The hooks a file defines, by event and phase. What cannot run is left
     * out and named in `$findings`.
     *
     * @param  list<array{code: string, severity: string, path: string, message: string, hint: string}>  $findings
     * @return array<string, array<string, list<array{name: string, kind: string, run: string|null, skill: string|null, timeout: int, blocking: bool}>>>
     */
    protected function parse(string $yaml, array &$findings): array
    {
        try {
            $parsed = Yaml::parse($yaml);
        } catch (ParseException $exception) {
            $this->finding($findings, 'HOOKS_PARSE_ERROR', 'error', 'hooks', 'hooks.yaml is not valid YAML: '.$exception->getMessage(), 'Fix the YAML; php artisan larapilot:hook-list reads it again.');

            return [];
        }

        if ($parsed === null) {
            return [];
        }

        if (! is_array($parsed) || ! array_key_exists('hooks', $parsed)) {
            $this->finding($findings, 'HOOKS_NO_ROOT', 'error', 'hooks', 'hooks.yaml has no top-level `hooks:` key.', 'Put every event under `hooks:`, as the commented examples in the file do.');

            return [];
        }

        $events = $parsed['hooks'];

        if ($events === null || $events === []) {
            return [];
        }

        if (! is_array($events) || array_is_list($events)) {
            $this->finding($findings, 'HOOKS_NOT_A_MAP', 'error', 'hooks', '`hooks:` must map each event to its phases.', 'Write `task.done:` under `hooks:`, then `before:` or `after:` under it.');

            return [];
        }

        $hooks = [];

        foreach ($events as $event => $phases) {
            $event = (string) $event;

            if (! array_key_exists($event, self::EVENTS)) {
                $this->finding($findings, 'HOOKS_UNKNOWN_EVENT', 'error', "hooks.{$event}", "Unknown event {$event}.", 'Events: '.implode(', ', array_keys(self::EVENTS)).'.');

                continue;
            }

            if ($phases === null) {
                continue;
            }

            if (! is_array($phases) || (array_is_list($phases) && $phases !== [])) {
                $this->finding($findings, 'HOOKS_NOT_A_MAP', 'error', "hooks.{$event}", "{$event} must map `before:` or `after:` to a list of hooks.", 'Indent the hooks under `before:` or `after:`.');

                continue;
            }

            foreach ($phases as $phase => $entries) {
                $phase = (string) $phase;

                if (! in_array($phase, self::PHASES, true)) {
                    $this->finding($findings, 'HOOKS_UNKNOWN_PHASE', 'error', "hooks.{$event}.{$phase}", "Unknown phase {$phase}.", 'Phases: before, after.');

                    continue;
                }

                if ($entries === null) {
                    continue;
                }

                if (! is_array($entries) || ! array_is_list($entries)) {
                    $this->finding($findings, 'HOOKS_NOT_A_LIST', 'error', "hooks.{$event}.{$phase}", "{$event}.{$phase} must be a list of hooks.", 'Start each hook with `- run:` or `- skill:`.');

                    continue;
                }

                foreach ($entries as $index => $entry) {
                    $hook = $this->normalize($findings, $event, $phase, $index, $entry);

                    if ($hook !== null) {
                        $hooks[$event][$phase][] = $hook;
                    }
                }
            }
        }

        return $hooks;
    }

    /**
     * What `hook-list` answers, and the dashboard shows.
     *
     * @return array<string, mixed>
     */
    public function listing(?string $event = null): array
    {
        $definitions = $this->definitions();
        $events = [];
        $count = 0;

        foreach (self::EVENTS as $name => $firedBy) {
            $before = $definitions['hooks'][$name]['before'] ?? [];
            $after = $definitions['hooks'][$name]['after'] ?? [];
            $count += count($before) + count($after);

            if (($event === null && $before === [] && $after === []) || ($event !== null && $event !== $name)) {
                continue;
            }

            $events[$name] = ['fired_by' => $firedBy, 'before' => $before, 'after' => $after];
        }

        return [
            'active' => $this->active(),
            'setting' => $this->config->settings()['hooks'],
            'machine_enabled' => $this->machineEnabled(),
            'path' => $this->config->relativePath($this->config->hooksPath()),
            'exists' => $definitions['exists'],
            'ok' => $definitions['ok'],
            'count' => $count,
            'events' => $events,
            'findings' => $definitions['findings'],
            'catalog' => self::EVENTS,
        ];
    }

    /**
     * Run the hooks of one event in one phase.
     *
     * `$skillsDone` names the skill hooks the caller reports run. On a
     * `before` phase it is enforced: a blocking skill hook it does not name
     * refuses the transition before any command runs. Null lists the skills
     * without enforcing them (`hook-run`).
     *
     * @param  array<string, mixed>  $context
     * @param  list<string>|null  $skillsDone
     * @return array{
     *     event: string,
     *     phase: string,
     *     ran: list<array<string, mixed>>,
     *     skills: list<array<string, mixed>>,
     *     blocked: bool,
     *     reason: string|null,
     *     hint: string|null,
     *     warnings: list<string>,
     *     findings?: list<array<string, string>>
     * }|null Null when hooks are off, or the event has none in that phase.
     */
    public function fire(string $event, string $phase, array $context = [], ?array $skillsDone = null): ?array
    {
        if (! $this->active()) {
            return null;
        }

        $report = [
            'event' => $event,
            'phase' => $phase,
            'ran' => [],
            'skills' => [],
            'blocked' => false,
            'reason' => null,
            'hint' => null,
            'warnings' => [],
        ];

        $definitions = $this->definitions();

        if (! $definitions['ok']) {
            $report['blocked'] = $phase === 'before';
            $report['reason'] = $this->config->relativePath($this->config->hooksPath()).' has errors, so no hook of it can be trusted.';
            $report['hint'] = 'Run php artisan larapilot:hook-list for the findings and fix the file, or ask the user to turn hooks off with larapilot:settings-set --hooks=NO.';
            $report['findings'] = array_values(array_filter(
                $definitions['findings'],
                static fn (array $finding): bool => $finding['severity'] === 'error'
            ));

            if ($phase !== 'before') {
                $report['warnings'][] = $report['reason'];
            }

            return $report;
        }

        $hooks = $definitions['hooks'][$event][$phase] ?? [];

        if ($hooks === []) {
            return null;
        }

        $where = $this->where($event, $phase, $context);
        $skills = array_values(array_filter($hooks, static fn (array $hook): bool => $hook['kind'] === 'skill'));

        foreach ($skills as $hook) {
            $report['skills'][] = [
                'name' => $hook['name'],
                'skill' => $hook['skill'],
                'blocking' => $hook['blocking'],
                'instruction' => "Run /{$hook['skill']} now ({$where}), then go on.",
            ];
        }

        if ($phase === 'before' && $skillsDone !== null) {
            $done = array_map(static fn (string $skill): string => strtolower(ltrim(trim($skill), '/')), $skillsDone);
            $pending = array_values(array_filter(
                $skills,
                static fn (array $hook): bool => $hook['blocking'] && ! in_array(strtolower((string) $hook['skill']), $done, true)
            ));

            if ($pending !== []) {
                $names = array_map(static fn (array $hook): string => (string) $hook['skill'], $pending);
                $all = array_map(static fn (array $hook): string => (string) $hook['skill'], $skills);

                $report['blocked'] = true;
                $report['reason'] = 'The project runs /'.implode(', /', $names)." before {$event}, and it was not reported done.";
                $report['hint'] = "Run each skill now ({$where}) and act on what it finds, then repeat this command with --skill-hooks-done=".implode(',', $all).'.';

                return $report;
            }
        }

        $position = 0;

        foreach ($hooks as $hook) {
            $position++;

            if ($hook['kind'] !== 'run') {
                continue;
            }

            $result = $this->execute($event, $phase, $position, $hook, $context);
            $report['ran'][] = $result;

            if ($result['ok']) {
                continue;
            }

            $failure = $result['timed_out']
                ? "did not finish within {$hook['timeout']}s"
                : (isset($result['error']) ? 'could not start: '.$result['error'] : 'failed with exit code '.$result['exit_code']);

            if ($phase === 'before' && $hook['blocking']) {
                $report['blocked'] = true;
                $report['reason'] = "Hook \"{$hook['name']}\" (before {$event}) {$failure}.";
                $report['hint'] = 'Read its output — `log` holds all of it — fix the cause, and run the command again. The hook is the gate of the project in .larapilot/hooks.yaml: never edit it, or turn hooks off, to get past it unless the user asks.';

                return $report;
            }

            $report['warnings'][] = "Hook \"{$hook['name']}\" ({$phase} {$event}) {$failure}"
                .($phase === 'after' ? '; the transition stands.' : '; it does not block.');
        }

        return $report;
    }

    /**
     * @param  list<array{code: string, severity: string, path: string, message: string, hint: string}>  $findings
     */
    protected function finding(array &$findings, string $code, string $severity, string $path, string $message, string $hint): void
    {
        $findings[] = [
            'code' => $code,
            'severity' => $severity,
            'path' => $path,
            'message' => $message,
            'hint' => $hint,
        ];
    }

    /**
     * One hook as the file writes it, or null when it cannot run. A plain
     * string is a `run` hook.
     *
     * @param  list<array{code: string, severity: string, path: string, message: string, hint: string}>  $findings
     * @return array{name: string, kind: string, run: string|null, skill: string|null, timeout: int, blocking: bool}|null
     */
    protected function normalize(array &$findings, string $event, string $phase, int $index, mixed $entry): ?array
    {
        $path = "hooks.{$event}.{$phase}.{$index}";

        if (is_string($entry)) {
            $entry = ['run' => $entry];
        }

        if (! is_array($entry)) {
            $this->finding($findings, 'HOOKS_NO_ACTION', 'error', $path, 'A hook is a `run:` command or a `skill:` name.', 'Write `- run: php artisan test` or `- skill: my-skill`.');

            return null;
        }

        foreach (array_diff(array_map('strval', array_keys($entry)), self::KEYS) as $unknown) {
            $this->finding($findings, 'HOOKS_UNKNOWN_KEY', 'warning', "{$path}.{$unknown}", "Unknown key {$unknown}; it is ignored.", 'Keys: '.implode(', ', self::KEYS).'.');
        }

        $run = is_string($entry['run'] ?? null) ? trim($entry['run']) : '';
        $skill = is_string($entry['skill'] ?? null) ? ltrim(trim($entry['skill']), '/') : '';

        if (($run === '') === ($skill === '')) {
            $this->finding($findings, 'HOOKS_NO_ACTION', 'error', $path, 'A hook needs exactly one of `run:` and `skill:`.', 'Split a command and a skill into two hooks.');

            return null;
        }

        $timeout = $entry['timeout'] ?? $this->defaultTimeout();

        if (! is_int($timeout) || $timeout < 1 || $timeout > self::MAX_TIMEOUT) {
            $this->finding($findings, 'HOOKS_BAD_TIMEOUT', 'error', "{$path}.timeout", 'timeout is a whole number of seconds from 1 to '.self::MAX_TIMEOUT.'.', 'Write `timeout: 600`.');

            return null;
        }

        $blocking = $entry['blocking'] ?? true;

        if (! is_bool($blocking)) {
            $this->finding($findings, 'HOOKS_BAD_BLOCKING', 'error', "{$path}.blocking", 'blocking is true or false.', 'Write `blocking: false` for a before hook that should only warn.');

            return null;
        }

        if ($phase === 'after' && array_key_exists('blocking', $entry)) {
            $this->finding($findings, 'HOOKS_BLOCKING_AFTER', 'warning', "{$path}.blocking", 'An after hook runs once the state is written and never blocks; blocking is ignored.', 'Move the hook to before: to make it a gate.');
        }

        if ($skill !== '' && ! $this->skillExists($skill)) {
            $this->finding($findings, 'HOOKS_UNKNOWN_SKILL', 'warning', "{$path}.skill", "No skill named {$skill} was found in the project.", 'Create it with /larapilot-custom-skill, or check the name against .larapilot/skills/.');
        }

        $name = is_string($entry['name'] ?? null) && trim($entry['name']) !== ''
            ? trim($entry['name'])
            : ($run !== '' ? $run : '/'.$skill);

        return [
            'name' => $name,
            'kind' => $run !== '' ? 'run' : 'skill',
            'run' => $run !== '' ? $run : null,
            'skill' => $skill !== '' ? $skill : null,
            'timeout' => $timeout,
            'blocking' => $phase === 'before' && $blocking,
        ];
    }

    protected function defaultTimeout(): int
    {
        return max(1, min(self::MAX_TIMEOUT, (int) config('larapilot.hooks.timeout', 300)));
    }

    /**
     * A packaged skill, a custom one, or one an agent folder of the project holds.
     */
    protected function skillExists(string $skill): bool
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $skill) !== 1) {
            return false;
        }

        if (is_file(dirname(__DIR__, 2).'/resources/boost/skills/'.$skill.'/SKILL.md')) {
            return true;
        }

        $custom = rtrim($this->config->absolutePath((string) ($this->config->resolve()['paths']['custom_skills'] ?? '.larapilot/skills/')), '/\\');

        return is_file($custom.'/'.$skill.'/SKILL.md')
            || (glob($this->config->absolutePath('.*/skills/'.$skill.'/SKILL.md')) ?: []) !== [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function where(string $event, string $phase, array $context): string
    {
        $subject = is_array($context['spec'] ?? null) ? (string) ($context['spec']['code'] ?? '') : '';
        $subject .= is_string($context['task'] ?? null) && $context['task'] !== '' ? ' '.$context['task'] : '';
        $subject .= is_string($context['release'] ?? null) && $context['release'] !== '' ? ' '.$context['release'] : '';

        return trim("{$phase} {$event}".($subject !== '' ? ', '.trim($subject) : ''));
    }

    /**
     * @param  array{name: string, kind: string, run: string|null, skill: string|null, timeout: int, blocking: bool}  $hook
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function execute(string $event, string $phase, int $position, array $hook, array $context): array
    {
        $started = microtime(true);
        $output = '';
        $timedOut = false;
        $error = null;

        $process = Process::fromShellCommandline(
            (string) $hook['run'],
            $this->config->projectRoot(),
            $this->environment($event, $phase, $context),
            Envelope::success('hook_event', ['event' => $event, 'phase' => $phase] + $context),
            (float) $hook['timeout']
        );

        try {
            $process->run(static function (string $type, string $buffer) use (&$output): void {
                $output .= $buffer;
            });
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        $output = (string) preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $output);
        $exitCode = $timedOut || $error !== null ? null : $process->getExitCode();

        $result = [
            'name' => $hook['name'],
            'run' => $hook['run'],
            'ok' => ! $timedOut && $error === null && $exitCode === 0,
            'exit_code' => $exitCode,
            'timed_out' => $timedOut,
            'blocking' => $hook['blocking'],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'output' => $this->tail($output),
            'log' => $this->log($event, $phase, $position, $output.($error !== null ? "\n".$error."\n" : '')),
        ];

        if ($error !== null) {
            $result['error'] = $error;
        }

        return $result;
    }

    /**
     * What a `run` hook finds in its environment. The JSON on its stdin
     * carries the same, and the whole context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function environment(string $event, string $phase, array $context): array
    {
        $spec = is_array($context['spec'] ?? null) ? $context['spec'] : [];
        $specs = is_array($context['specs'] ?? null) ? $context['specs'] : [];
        $status = is_array($context['status'] ?? null) ? $context['status'] : [];
        $commit = $context['commit'] ?? null;

        return [
            'LARAPILOT_HOOK_EVENT' => $event,
            'LARAPILOT_HOOK_PHASE' => $phase,
            'LARAPILOT_HOOK_SPEC' => (string) ($spec['code'] ?? implode(',', array_map('strval', $specs))),
            'LARAPILOT_HOOK_SPEC_TITLE' => (string) ($spec['title'] ?? ''),
            'LARAPILOT_HOOK_TASK' => (string) ($context['task'] ?? ''),
            'LARAPILOT_HOOK_STATUS_FROM' => (string) ($status['from'] ?? ''),
            'LARAPILOT_HOOK_STATUS_TO' => (string) ($status['to'] ?? ''),
            'LARAPILOT_HOOK_COMMIT' => is_array($commit) ? (string) ($commit['sha'] ?? '') : (string) ($commit ?? ''),
            'LARAPILOT_HOOK_RELEASE' => (string) ($context['release'] ?? ''),
            'LARAPILOT_HOOK_PROJECT_ROOT' => $this->config->projectRoot(),
        ];
    }

    /**
     * The last lines of the output, short enough for an answer.
     */
    protected function tail(string $output): string
    {
        $lines = preg_split('/\r\n|\r|\n/', rtrim($output)) ?: [];
        $tail = implode("\n", array_slice($lines, -self::OUTPUT_LINES));

        if (strlen($tail) > self::OUTPUT_BYTES) {
            $tail = mb_strcut($tail, strlen($tail) - self::OUTPUT_BYTES);
        }

        return $tail;
    }

    /**
     * The whole output, in `.larapilot/cache/hooks/` — derived, never committed.
     */
    protected function log(string $event, string $phase, int $position, string $output): string
    {
        $cache = $this->config->absolutePath('.larapilot/cache');

        if (! is_file($cache.'/.gitignore')) {
            AtomicFile::write($cache.'/.gitignore', "*\n");
        }

        $file = "{$cache}/hooks/{$event}.{$phase}.{$position}.log";
        AtomicFile::write($file, $output);

        return $this->config->relativePath($file);
    }
}
