<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Larapilot\Services\ConfigService;
use Larapilot\Services\GitService;
use Larapilot\Services\HacklyService;
use Larapilot\Services\SecurityService;
use Symfony\Component\Yaml\Yaml;

const HACKLY_REPO = '0b8f6c2e-1a2b-4c3d-8e9f-000000000001';
const HACKLY_OTHER_REPO = '0b8f6c2e-1a2b-4c3d-8e9f-000000000002';
const HACKLY_TARGET = '7c1d2e3f-4a5b-4c6d-9e8f-000000000010';
const FINDING_SQL = '5f0e1d2c-3b4a-4958-8776-000000000101';
const FINDING_GUZZLE = '5f0e1d2c-3b4a-4958-8776-000000000102';
const FINDING_SECRET = '5f0e1d2c-3b4a-4958-8776-000000000103';
const FINDING_TLS = '5f0e1d2c-3b4a-4958-8776-000000000104';
const FINDING_ACK = '5f0e1d2c-3b4a-4958-8776-000000000105';
const FINDING_FIXED = '5f0e1d2c-3b4a-4958-8776-000000000106';
const FINDING_FP = '5f0e1d2c-3b4a-4958-8776-000000000107';

/**
 * @return array<string, mixed>
 */
function hacklyFinding(string $id, string $title, string $severity, string $status, string $category, array $more = []): array
{
    return array_merge([
        'id' => $id,
        'title' => $title,
        'severity' => $severity,
        'status' => $status,
        'category' => $category,
        'category_label' => ucfirst(str_replace('_', ' ', $category)),
        'source' => 'semgrep',
        'cve' => null,
        'reachability' => null,
        'confidence' => 0.8,
        'noise_filtered' => false,
        'description' => $title.' — described.',
        'false_positive_note' => null,
        'false_positive_noted_at' => null,
        'evidence_lines' => ['app/Http/Controllers/InvoiceController.php:42'],
        'subject' => 'example/fjord-invoices',
        'repository' => ['id' => HACKLY_REPO, 'full_name' => 'example/fjord-invoices'],
        'created_at' => '2026-09-01T10:00:00+00:00',
        'updated_at' => '2026-09-20T10:00:00+00:00',
    ], $more);
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function hacklyFindings(): array
{
    return [
        'repo:open' => [
            hacklyFinding(FINDING_SQL, 'SQL built from request input', 'high', 'open', 'sast', ['reachability' => 'reachable']),
            hacklyFinding(FINDING_GUZZLE, 'guzzlehttp/psr7 header parsing', 'high', 'open', 'dependency', ['source' => 'trivy', 'cve' => 'CVE-2026-1111']),
            hacklyFinding(FINDING_SECRET, 'AWS key committed in config', 'medium', 'open', 'secrets', ['source' => 'gitleaks']),
        ],
        'repo:ack' => [
            hacklyFinding(FINDING_ACK, 'Package under AGPL-3.0', 'low', 'ack', 'license', ['source' => 'composer']),
        ],
        'target:open' => [
            hacklyFinding(FINDING_TLS, 'TLS 1.0 still offered', 'medium', 'open', 'tls', ['source' => 'tls_check', 'subject' => 'app.fjord.test', 'repository' => null, 'asset' => ['id' => HACKLY_TARGET, 'domain' => 'app.fjord.test']]),
        ],
        'repo:fixed' => [
            hacklyFinding(FINDING_FIXED, 'Debug mode on', 'high', 'fixed', 'laravel', ['updated_at' => '2026-09-25T10:00:00+00:00']),
        ],
        'repo:false_positive' => [
            hacklyFinding(FINDING_FP, 'Weak hash in a test fixture', 'low', 'false_positive', 'sast', ['false_positive_note' => 'Only in a test fixture, never in production.', 'false_positive_noted_at' => '2026-09-21T10:00:00+00:00']),
        ],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function hacklyRepositories(): array
{
    return [
        ['id' => HACKLY_OTHER_REPO, 'owner' => 'example', 'name' => 'fjord-website', 'full_name' => 'example/fjord-website', 'default_branch' => 'main', 'status' => 'active', 'html_url' => 'https://github.com/example/fjord-website', 'last_scanned_at' => '2026-09-30T08:00:00+00:00', 'targets' => []],
        ['id' => HACKLY_REPO, 'owner' => 'example', 'name' => 'fjord-invoices', 'full_name' => 'example/fjord-invoices', 'default_branch' => 'main', 'status' => 'active', 'html_url' => 'https://github.com/example/fjord-invoices', 'last_scanned_at' => '2026-09-30T08:00:00+00:00', 'targets' => [['id' => HACKLY_TARGET, 'domain' => 'app.fjord.test']]],
    ];
}

/**
 * A Hackly instance, answering the way its API does.
 */
function fakeHackly(bool $readOnly = false): void
{
    Http::swap(new Factory);

    Http::fake(function (Request $request) use ($readOnly) {
        $url = $request->url();
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (! str_starts_with($url, 'https://hackly.example.com/api/v1/')) {
            return Http::response(['message' => 'Not Found'], 404);
        }

        if ($request->header('Authorization')[0] !== 'Bearer hk_test_token') {
            return Http::response(['message' => 'Unauthenticated.'], 401);
        }

        if ($request->method() !== 'GET' && $readOnly) {
            return Http::response(['message' => 'Invalid ability provided.'], 403);
        }

        return match (true) {
            $path === '/api/v1/me' => Http::response(['data' => ['name' => 'Andrea', 'email' => 'andrea@example.test']]),
            $path === '/api/v1/repositories' => Http::response(['data' => hacklyRepositories(), 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2]]),
            $path === '/api/v1/repositories/'.HACKLY_REPO => Http::response(['data' => hacklyRepositories()[1]]),
            $path === '/api/v1/findings' => Http::response(['data' => hacklyFindings()[(isset($query['target_id']) ? 'target' : 'repo').':'.($query['status'] ?? 'open')] ?? [], 'meta' => ['current_page' => 1, 'last_page' => 1]]),
            $path === '/api/v1/findings/triage' => Http::response(['data' => []]),
            str_starts_with($path, '/api/v1/findings/') && str_ends_with($path, '/markdown') => Http::response("# SQL built from request input\n\nThe brief.", 200, ['Content-Type' => 'text/markdown']),
            str_starts_with($path, '/api/v1/findings/') && str_ends_with($path, '/prompt') => Http::response('Fix the query in InvoiceController with bindings.', 200, ['Content-Type' => 'text/markdown']),
            str_starts_with($path, '/api/v1/findings/') && $request->method() === 'PATCH' => Http::response(['data' => ['id' => basename($path), 'status' => $request['status']]]),
            $path === '/api/v1/repositories/'.HACKLY_REPO.'/scans' => Http::response(['data' => ['id' => 'scan-1', 'status' => 'queued']], 201),
            default => Http::response(['message' => 'Not Found'], 404),
        };
    });
}

function enableHackly(): void
{
    test()->artisan('larapilot:settings-set', ['--security-provider' => 'hackly'])->assertSuccessful();

    config()->set('larapilot.hackly.url', 'https://hackly.example.com');
    config()->set('larapilot.hackly.token', 'hk_test_token');
    config()->set('larapilot.hackly.fail_on', 'high');

    hacklyOrigin('git@github.com:example/fjord-invoices.git');
}

function hacklyOrigin(string $url): void
{
    app()->instance(GitService::class, new class(app(ConfigService::class), $url) extends GitService
    {
        public function __construct(ConfigService $config, private readonly string $url)
        {
            parent::__construct($config);
        }

        public function originUrl(): ?string
        {
            return $this->url;
        }

        public function originRepoSlug(): ?string
        {
            return 'example/fjord-invoices';
        }
    });

    app()->forgetInstance(HacklyService::class);
    app()->forgetInstance(SecurityService::class);
}

/**
 * @return array<string, mixed>
 */
function hacklyEnvelope(): array
{
    $decoded = json_decode(trim(Artisan::output()), true);

    return is_array($decoded) ? $decoded : [];
}

it('reads security from the scanner the project chose, and keeps the old aikido flag working', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $config = app(ConfigService::class);

    expect($config->securityProvider())->toBe('')
        ->and($config->settings()['security'])->toBe('NO')
        ->and($config->allowedSecurityProviders())->toBe(['aikido', 'hackly']);

    $this->artisan('larapilot:settings-set', ['--security-provider' => 'hackly'])->assertSuccessful();
    expect(app(ConfigService::class)->securityProvider())->toBe('hackly')
        ->and(app(ConfigService::class)->hacklyEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->aikidoEnabled())->toBeFalse()
        ->and(Yaml::parseFile(base_path('.larapilot/config.yaml'))['settings'])->toMatchArray(['security' => true, 'security_provider' => 'hackly', 'aikido' => false]);

    $this->artisan('larapilot:settings-set', ['--aikido' => 'YES'])->assertSuccessful();
    expect(app(ConfigService::class)->securityProvider())->toBe('aikido')
        ->and(app(ConfigService::class)->aikidoEnabled())->toBeTrue();

    $this->artisan('larapilot:settings-set', ['--security-provider' => 'none'])->assertSuccessful();
    expect(app(ConfigService::class)->securityProvider())->toBe('');

    $this->artisan('larapilot:settings-set', ['--security-provider' => 'snyk'])->assertFailed();

    // A config.yaml written before 5.1, with the old flag only.
    $yaml = Yaml::parseFile(base_path('.larapilot/config.yaml'));
    unset($yaml['settings']['security'], $yaml['settings']['security_provider']);
    $yaml['settings']['aikido'] = true;
    file_put_contents(base_path('.larapilot/config.yaml'), Yaml::dump($yaml, 4, 2));
    app()->forgetInstance(ConfigService::class);

    expect(app(ConfigService::class)->securityProvider())->toBe('aikido');
});

it('says what is missing before it asks Hackly anything', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    Http::fake();

    $this->artisan('larapilot:security-issues')->assertExitCode(4)->expectsOutputToContain('No security scanner is set');

    $this->artisan('larapilot:settings-set', ['--security-provider' => 'hackly'])->assertSuccessful();
    hacklyOrigin('git@github.com:example/fjord-invoices.git');

    $status = app(HacklyService::class)->status();
    expect($status['configured'])->toBeFalse()
        ->and($status['ready'])->toBeFalse()
        ->and(implode(' ', $status['hints']))->toContain('LARAPILOT_HACKLY_URL')->toContain('not the documentation site');

    $this->artisan('larapilot:security-issues')->assertExitCode(3)->expectsOutputToContain('Hackly is not configured');
    Http::assertNothingSent();

    // The documentation site is not a Hackly.
    config()->set('larapilot.hackly.url', 'https://hackly.web.ap.it/api');
    config()->set('larapilot.hackly.token', 'hk_test_token');
    Http::fake(['*' => Http::response(['message' => 'Not Found'], 404)]);
    app()->forgetInstance(HacklyService::class);

    expect(implode(' ', app(HacklyService::class)->status()['hints']))->toContain('documentation of Hackly');
});

it('finds the repository from the git remote and reads its findings and those of its linked domain', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHackly();
    fakeHackly();

    $status = app(HacklyService::class)->status();
    expect($status['ready'])->toBeTrue()
        ->and($status['user'])->toBe('andrea@example.test')
        ->and($status['repository']['id'])->toBe(HACKLY_REPO)
        ->and($status['repository']['targets'][0]['domain'])->toBe('app.fjord.test')
        ->and($status['repository_source'])->toBe('git remote');

    $findings = app(SecurityService::class)->findings();

    expect($findings['provider'])->toBe('hackly')
        ->and($findings['severities'])->toBe(['high', 'medium', 'low'])
        ->and($findings['total'])->toBe(5)
        ->and(array_column($findings['issues'], 'id'))->toContain(FINDING_SQL, FINDING_GUZZLE, FINDING_SECRET, FINDING_TLS, FINDING_ACK)
        ->and($findings['issues'][0]['id'])->toBe(FINDING_SQL) // high and reachable first
        ->and($findings['counts'])->toMatchArray(['high' => 2, 'medium' => 2, 'low' => 1, 'all' => 5])
        ->and($findings['gate']['verdict'])->toBe('FAIL')
        ->and($findings['gate']['blocking'])->toBe([FINDING_SQL, FINDING_GUZZLE])
        ->and($findings['targets'][0]['domain'])->toBe('app.fjord.test');

    $tls = collect($findings['issues'])->firstWhere('id', FINDING_TLS);
    expect($tls['origin'])->toBe('target')->and($tls['where'])->toContain('app.fjord.test');

    $guzzle = collect($findings['issues'])->firstWhere('id', FINDING_GUZZLE);
    expect($guzzle['cves'])->toBe(['CVE-2026-1111'])->and($guzzle['source'])->toBe('trivy');

    // Without the domains, only the repository.
    config()->set('larapilot.hackly.include_targets', false);
    app()->forgetInstance(HacklyService::class);
    expect(app(HacklyService::class)->findings()['total'])->toBe(4);
});

it('records a spec, a waiver, and a false positive, and tells Hackly each one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['code' => 'US-012', 'title' => 'Bind the invoice query']);
    enableHackly();
    fakeHackly();

    $this->artisan('larapilot:security-link', ['issues' => FINDING_SQL, '--spec' => 'US-012'])->assertSuccessful();
    $this->artisan('larapilot:security-link', ['issues' => FINDING_GUZZLE, '--waive' => true, '--reason' => 'Only used by an offline import job.'])->assertSuccessful();
    $this->artisan('larapilot:security-link', ['issues' => FINDING_SECRET, '--false-positive' => true, '--reason' => 'A placeholder key from the AWS docs.'])->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/findings/triage') && $request['status'] === 'ack' && $request['ids'] === [FINDING_SQL]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/findings/triage') && $request['status'] === 'ack' && $request['ids'] === [FINDING_GUZZLE]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/findings/triage') && $request['status'] === 'false_positive' && $request['note'] === 'A placeholder key from the AWS docs.');

    $ledger = Yaml::parseFile(base_path('.larapilot/hackly.yaml'));
    expect($ledger['issues'][FINDING_SQL])->toMatchArray(['state' => 'in_backlog', 'spec' => 'US-012', 'sent' => 'ack'])
        ->and($ledger['issues'][FINDING_GUZZLE])->toMatchArray(['state' => 'waived', 'sent' => 'ack'])
        ->and($ledger['issues'][FINDING_SECRET])->toMatchArray(['state' => 'false_positive', 'sent' => 'false_positive']);

    $findings = app(HacklyService::class)->findings();
    expect($findings['states'])->toMatchArray(['new' => 2, 'in_backlog' => 1, 'waived' => 2])
        ->and($findings['gate']['verdict'])->toBe('FAIL')
        ->and($findings['gate']['blocking'])->toBe([FINDING_SQL])
        ->and($findings['gate']['undecided'])->toBe(0)
        ->and($findings['unsent'])->toBe([]);

    // Forgetting opens the finding again in Hackly.
    $this->artisan('larapilot:security-link', ['issues' => FINDING_GUZZLE, '--forget' => true])->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && str_ends_with($request->url(), '/findings/'.FINDING_GUZZLE) && $request['status'] === 'open');
    expect(Yaml::parseFile(base_path('.larapilot/hackly.yaml'))['issues'])->not->toHaveKey(FINDING_GUZZLE);

    // Wrong input is refused before anything is asked.
    $this->artisan('larapilot:security-link', ['issues' => '24', '--spec' => 'US-012'])->assertExitCode(2)->expectsOutputToContain('UUID');
    $this->artisan('larapilot:security-link', ['issues' => FINDING_TLS, '--waive' => true, '--reason' => 'short'])->assertExitCode(2);
    $this->artisan('larapilot:security-link', ['issues' => FINDING_TLS, '--spec' => 'US-999'])->assertExitCode(4);
});

it('keeps a decision Hackly refuses, and tells it later', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['code' => 'US-012', 'title' => 'Bind the invoice query']);
    enableHackly();
    fakeHackly(readOnly: true);

    $this->artisan('larapilot:security-link', ['issues' => FINDING_SQL, '--spec' => 'US-012'])
        ->assertSuccessful()
        ->expectsOutputToContain('write ability');

    expect(app(HacklyService::class)->findings()['unsent'])->toBe([FINDING_SQL]);

    fakeHackly();
    $this->artisan('larapilot:security-push')->assertSuccessful();
    expect(app(HacklyService::class)->findings()['unsent'])->toBe([]);
});

it('groups confirmed findings, never merging a leaked secret', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHackly();
    fakeHackly();

    expect(Artisan::call('larapilot:security-plan', ['--ids' => FINDING_SQL.','.FINDING_SECRET.','.FINDING_TLS]))->toBe(0);
    $plan = hacklyEnvelope()['data'];

    expect($plan['provider'])->toBe('hackly')
        ->and($plan['groups'])->toHaveCount(3)
        ->and($plan['groups'][0]['ids'])->toBe([FINDING_SQL])
        ->and(collect($plan['groups'])->firstWhere('ids', [FINDING_SECRET])['reason'])->toContain('never merged');
});

it('asks Hackly for a scan, a brief, and the fix prompt of a finding', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHackly();
    fakeHackly();

    $this->artisan('larapilot:security-scan', ['--profile' => 'deep', '--include-targets' => true])->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/repositories/'.HACKLY_REPO.'/scans') && $request['profile'] === 'deep' && $request['include_targets'] === true);

    $this->artisan('larapilot:security-scan', ['--profile' => 'reckless'])->assertExitCode(2);

    expect(Artisan::call('larapilot:security-issues', ['--detail' => FINDING_SQL]))->toBe(0);
    $detail = hacklyEnvelope()['data'];
    expect($detail['markdown'])->toContain('SQL built from request input')
        ->and($detail['prompt'])->toContain('bindings');
});

it('lets the user name the repository when the git remote finds none', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableHackly();
    hacklyOrigin('git@github.com:example/unknown.git');
    app()->instance(GitService::class, new class(app(ConfigService::class)) extends GitService
    {
        public function originUrl(): ?string
        {
            return 'git@github.com:example/unknown.git';
        }

        public function originRepoSlug(): ?string
        {
            return 'example/unknown';
        }
    });
    app()->forgetInstance(HacklyService::class);
    app()->forgetInstance(SecurityService::class);
    fakeHackly();

    expect(app(HacklyService::class)->status()['needs_repository'])->toBeTrue();

    expect(Artisan::call('larapilot:security-repos'))->toBe(0);
    expect(array_column(hacklyEnvelope()['data']['repositories'], 'name'))->toContain('example/fjord-invoices');

    $this->artisan('larapilot:security-repos', ['--use' => 'example/fjord-invoices'])->assertSuccessful();
    expect(Yaml::parseFile(base_path('.larapilot/hackly.yaml'))['repository']['id'])->toBe(HACKLY_REPO);

    app()->forgetInstance(HacklyService::class);
    expect(app(HacklyService::class)->status()['ready'])->toBeTrue()
        ->and(app(HacklyService::class)->status()['repository_source'])->toBe('ledger');
});

it('writes the report and the register for the client, with Hackly as the scanner', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['code' => 'US-012', 'title' => 'Bind the invoice query']);
    enableHackly();
    fakeHackly();

    $this->artisan('larapilot:security-link', ['issues' => FINDING_SQL, '--spec' => 'US-012'])->assertSuccessful();
    $this->artisan('larapilot:security-issues', ['--report' => true])->assertSuccessful();

    $report = (string) file_get_contents(base_path('.larapilot/docs/security/security-report.md'));
    expect($report)->toContain('# Hackly findings — example/fjord-invoices')
        ->toContain('app.fjord.test')
        ->toContain('in the backlog as US-012')
        ->toContain('CVE-2026-1111');

    $this->artisan('larapilot:security-register', ['--fresh' => true])->assertSuccessful();
    $register = (string) file_get_contents(base_path('.larapilot/docs/security/security-register.md'));

    expect($register)->toContain('Hackly — code (SAST)')
        ->toContain('Debug mode on')
        ->toContain('Only in a test fixture, never in production.')
        ->not->toContain('Critical')
        ->not->toContain('Aikido');
});

it('asks on the Security page which repository is this project, then shows its findings', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['code' => 'US-012', 'title' => 'Bind the invoice query']);
    enableHackly();
    app()->instance(GitService::class, new class(app(ConfigService::class)) extends GitService
    {
        public function originUrl(): ?string
        {
            return 'git@github.com:example/unknown.git';
        }

        public function originRepoSlug(): ?string
        {
            return 'example/unknown';
        }
    });
    app()->forgetInstance(HacklyService::class);
    app()->forgetInstance(SecurityService::class);
    fakeHackly();

    $this->get('/larapilot/security')
        ->assertOk()
        ->assertSee('Which repository of Hackly is this project?', false)
        ->assertSee('example/fjord-invoices', false)
        ->assertSee('value="'.HACKLY_REPO.'"', false);

    $this->withSession(['_token' => 'hackly-test']);
    $this->post('/larapilot/security/repository', ['_token' => 'hackly-test', 'repository' => 'not-a-uuid'])->assertSessionHasErrors('repository');
    $this->post('/larapilot/security/repository', ['_token' => 'hackly-test', 'repository' => HACKLY_REPO])
        ->assertRedirect()
        ->assertSessionHas('larapilot_success', fn (string $message): bool => str_contains($message, 'example/fjord-invoices') && str_contains($message, '.larapilot/hackly.yaml'));

    expect(Yaml::parseFile(base_path('.larapilot/hackly.yaml'))['repository']['id'])->toBe(HACKLY_REPO);

    $this->artisan('larapilot:security-link', ['issues' => FINDING_SQL, '--spec' => 'US-012'])->assertSuccessful();

    $this->get('/larapilot/security?refresh=1')
        ->assertOk()
        ->assertSee('What Hackly found in this repository and in the domains linked to it (app.fjord.test)', false)
        ->assertSee('SQL built from request input', false)
        ->assertSee('TLS 1.0 still offered', false)
        ->assertSee('Acknowledged', false)
        ->assertSee('A release is stopped', false)
        ->assertSee('>Hackly</a>', false)
        ->assertSee('larapilot:security-scan', false)
        ->assertDontSee('Aikido', false);

    expect($this->get('/larapilot/security/report.md')->assertOk()->headers->get('Content-Disposition'))->toContain('security-report-')
        ->and($this->get('/larapilot/security/register.md')->assertOk()->getContent())->toContain('Hackly');
});

it('runs the security commands on Aikido too', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--security-provider' => 'aikido'])->assertSuccessful();

    expect(Artisan::call('larapilot:security-status'))->toBe(0);
    $status = hacklyEnvelope()['data'];

    expect($status['provider'])->toBe('aikido')
        ->and($status['configured'])->toBeFalse()
        ->and(implode(' ', $status['hints']))->toContain('LARAPILOT_AIKIDO_CLIENT_ID');
});
