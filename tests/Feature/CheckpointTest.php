<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\CheckpointService;

/**
 * Stands in for `checkpoint:scan` of andreapollastri/checkpoint, answering
 * the JSON its `--json` option prints.
 */
function registerFakeCheckpoint(int $exit = 1): void
{
    $command = new class($exit) extends Command
    {
        protected $signature = 'checkpoint:scan {--only=} {--skip=} {--json} {--fail-on-warn}';

        protected $description = 'Fake Checkpoint scan';

        public function __construct(private readonly int $exitCode)
        {
            parent::__construct();
        }

        public function handle(): int
        {
            $checks = [
                ['check' => 'Composer CVE Audit', 'status' => 'fail', 'message' => '1 CVE(s) found in Composer dependencies.', 'details' => ['[guzzlehttp/psr7] Improper header name validation (CVE-2023-29197)'], 'hashes' => ['9aad677cb4da']],
                ['check' => 'Environment Configuration', 'status' => 'warn', 'message' => '1 environment issue(s) found.', 'details' => ['APP_DEBUG is true — full stack traces will be exposed to end users.'], 'hashes' => ['5c4b3a2d1e0f']],
                ['check' => 'Hardcoded Secrets', 'status' => 'pass', 'message' => 'No hardcoded secrets detected.', 'details' => [], 'hashes' => []],
                ['check' => 'SQL Injection Risks', 'status' => 'pass', 'message' => 'No obvious SQL injection risks detected.', 'details' => [], 'hashes' => []],
            ];

            if ($this->option('only')) {
                $checks = array_values(array_filter($checks, fn (array $check): bool => in_array($check['check'], explode(',', (string) $this->option('only')), true)));
            }

            $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCode;
        }
    };

    app(Kernel::class)->registerCommand($command);
}

it('says Checkpoint is not installed, and how to install it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(Artisan::call('larapilot:checkpoint-scan'))->toBe(4)
        ->and(Artisan::output())->toContain('composer require --dev andreapollastri/checkpoint');

    expect(Artisan::call('larapilot:checkpoint-scan', ['--cached' => true]))->toBe(4)
        ->and(Artisan::output())->toContain('No Checkpoint scan was kept yet');

    $this->get('/larapilot/security/checkpoint')
        ->assertOk()
        ->assertSee('Checkpoint is not installed in this project')
        ->assertSee('composer require --dev andreapollastri/checkpoint')
        ->assertDontSee('Run the scan');

    $this->get('/larapilot/security/checkpoint.md')->assertNotFound();
});

it('runs Checkpoint, keeps the result, and answers it through Artisan', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    registerFakeCheckpoint();

    expect(Artisan::call('larapilot:checkpoint-scan', ['--report' => true, '--gate' => true]))->toBe(1);

    // The envelope survives the scan it runs inside: the MCP tool reads it here.
    $envelope = json_decode(Artisan::output(), true);
    $data = $envelope['data'];
    $areas = collect($data['areas'])->keyBy('area');

    expect($envelope['kind'])->toBe('checkpoint_scan')
        ->and($data['verdict'])->toBe('FAIL')
        ->and($data['counts'])->toBe(['fail' => 1, 'warn' => 1, 'pass' => 2])
        ->and($data['findings'])->toBe(2)
        ->and($data['checks'][0])->toMatchArray(['check' => 'Composer CVE Audit', 'status' => 'fail', 'area' => 'Dependencies'])
        ->and($data['checks'][0]['details'][0])->toBe(['text' => '[guzzlehttp/psr7] Improper header name validation (CVE-2023-29197)', 'hash' => '9aad677cb4da'])
        ->and($areas['Code'])->toMatchArray(['fail' => 0, 'warn' => 0, 'pass' => 2])
        ->and($areas['Configuration']['warn'])->toBe(1)
        ->and($data['report'])->toBe('.larapilot/docs/security/checkpoint.md')
        ->and((string) file_get_contents(base_path($data['report'])))->toContain('# Checkpoint — static security scan', '9aad677cb4da', 'config/checkpoint.php');

    // Out of git: the details can quote code.
    expect(base_path('.larapilot/cache/checkpoint/latest.json'))->toBeFile()
        ->and(file_get_contents(base_path('.larapilot/cache/.gitignore')))->toBe("*\n");

    expect(Artisan::call('larapilot:checkpoint-scan', ['--only' => 'Hardcoded Secrets', '--gate' => true]))->toBe(0);
    $partial = json_decode(Artisan::output(), true)['data'];

    expect($partial)->toMatchArray(['verdict' => 'PASS', 'partial' => true, 'only' => ['Hardcoded Secrets'], 'total' => 1]);

    expect(Artisan::call('larapilot:checkpoint-scan', ['--cached' => true]))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['data']['total'])->toBe(1);
});

it('shows the scan under Security, and runs it from the page', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    registerFakeCheckpoint();

    $this->get('/larapilot/security/checkpoint')
        ->assertOk()
        ->assertSee('No scan kept yet')
        ->assertSee('Run the scan');

    $this->post('/larapilot/security/checkpoint/scan')
        ->assertRedirect('/larapilot/security/checkpoint')
        ->assertSessionHas('larapilot_success', 'Checkpoint ran 4 checks: 2 passed, 1 warnings, 1 failed.');

    $this->get('/larapilot/security/checkpoint')
        ->assertOk()
        ->assertSee('A check failed')
        ->assertSee('Composer CVE Audit')
        ->assertSee('9aad677cb4da')
        ->assertSee('Not enforced (security_scan = NO)')
        ->assertSee('Scan again');

    $this->artisan('larapilot:settings-set', ['--security-scan' => 'YES'])->assertSuccessful();

    $this->get('/larapilot/security/checkpoint')->assertOk()->assertSee('Enforced in review and ship');

    $report = $this->get('/larapilot/security/checkpoint.md');
    $report->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($report->getContent())->toContain('FAIL — Composer CVE Audit');

    // The Aikido tab leads to Checkpoint, and back.
    $this->get('/larapilot/security')
        ->assertOk()
        ->assertSee(route('larapilot.dashboard.security.checkpoint'), false)
        ->assertSee('Dependencies (SBOM)');
});

it('reads only JSON from the scanner', function (): void {
    $parse = app(CheckpointService::class);

    expect($parse->parse('nothing here'))->toBeNull()
        ->and($parse->parse("Warning: something\n[{\"check\":\"Odd Check\",\"status\":\"weird\",\"message\":\"m\",\"details\":[\"a\"],\"hashes\":[]}]"))
        ->toBe([['check' => 'Odd Check', 'status' => 'warn', 'message' => 'm', 'details' => [['text' => 'a', 'hash' => null]], 'area' => 'Other']]);
});
