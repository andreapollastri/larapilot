<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Larapilot\Services\AzureDevopsService;
use Larapilot\Services\BitbucketService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\GithubService;
use Larapilot\Services\GitlabService;
use Larapilot\Services\NotifyService;

it('no-ops notify when notifications master switch is off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:notify', [
        '--event' => 'custom',
        '--title' => 'Hello',
    ])->assertSuccessful();

    $result = app(NotifyService::class)->send([
        'event' => 'custom',
        'title' => 'Hello',
    ]);

    expect($result['skipped'])->toBeTrue()
        ->and($result['sent'])->toBeFalse()
        ->and($result['channels'])->toBe([]);
});

it('fans out to enabled channels and skips missing credentials', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:settings-set', [
        '--notifications' => 'YES',
        '--notify-slack' => 'YES',
        '--notify-discord' => 'YES',
        '--notify-telegram' => 'YES',
    ])->assertSuccessful();

    config([
        'larapilot.integrations.slack_webhook_url' => 'https://hooks.example.test/slack',
        'larapilot.integrations.discord_webhook_url' => '',
        'larapilot.integrations.telegram_bot_token' => 'token',
        'larapilot.integrations.telegram_chat_id' => '42',
    ]);

    Http::fake([
        'hooks.example.test/*' => Http::response('ok', 200),
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $result = app(NotifyService::class)->send([
        'event' => 'task_done',
        'title' => 'US-001 TASK-01 done',
        'body' => 'commit abc1234',
        'url' => 'https://github.com/acme/app/commit/abc',
    ]);

    expect($result['sent'])->toBeTrue()
        ->and($result['skipped'])->toBeFalse()
        ->and($result['channels']['slack']['ok'])->toBeTrue()
        ->and($result['channels']['discord']['skipped'] ?? false)->toBeTrue()
        ->and($result['channels']['telegram']['ok'])->toBeTrue();

    Http::assertSentCount(2);
});

it('rejects invalid notify events', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:notify', [
        '--event' => 'not_a_real_event',
        '--title' => 'x',
    ])->assertExitCode(2)
        ->expectsOutputToContain('E_INVALID_INPUT');
});

it('returns github gitlab bitbucket and azure status envelopes', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->artisan('larapilot:github-status')->assertSuccessful();
    $this->artisan('larapilot:gitlab-status')->assertSuccessful();
    $this->artisan('larapilot:bitbucket-status')->assertSuccessful();
    $this->artisan('larapilot:azure-status')->assertSuccessful();

    expect(app(GithubService::class)->status()['enabled'])->toBeFalse()
        ->and(app(GitlabService::class)->status()['enabled'])->toBeFalse()
        ->and(app(BitbucketService::class)->status()['enabled'])->toBeFalse()
        ->and(app(AzureDevopsService::class)->status()['enabled'])->toBeFalse()
        ->and(app(GitlabService::class)->status())->toHaveKeys([
            'glab_installed',
            'authenticated',
            'is_gitlab_remote',
            'ready',
            'hints',
        ])
        ->and(app(BitbucketService::class)->status())->toHaveKeys([
            'authenticated',
            'auth_method',
            'is_bitbucket_remote',
            'ready',
            'hints',
        ])
        ->and(app(AzureDevopsService::class)->status())->toHaveKeys([
            'az_installed',
            'devops_extension',
            'authenticated',
            'auth_method',
            'is_azure_remote',
            'ready',
            'hints',
        ]);

    $this->artisan('larapilot:settings-set', [
        '--github' => 'YES',
        '--gitlab' => 'YES',
        '--bitbucket' => 'YES',
        '--azure' => 'YES',
    ])->assertSuccessful();

    expect(app(ConfigService::class)->githubEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->gitlabEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->bitbucketEnabled())->toBeTrue()
        ->and(app(ConfigService::class)->azureEnabled())->toBeTrue();
});

it('hooks task-done notification without failing when notifications are off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    $calls = [];
    $fake = new class($calls, app(ConfigService::class)) extends NotifyService
    {
        /** @param list<array<string, mixed>> $calls */
        public function __construct(
            public array &$calls,
            ConfigService $config,
        ) {
            parent::__construct($config);
        }

        public function send(array $payload): array
        {
            $this->calls[] = $payload;

            return parent::send($payload);
        }
    };

    $this->app->instance(NotifyService::class, $fake);

    $this->artisan('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-01'])
        ->assertSuccessful();

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['event'])->toBe('task_done')
        ->and($calls[0]['title'])->toContain('US-001');
});
