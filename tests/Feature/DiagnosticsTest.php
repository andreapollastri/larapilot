<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\DiagnosticsService;

it('collects a diagnostics snapshot with app status and checks', function (): void {
    $snapshot = app(DiagnosticsService::class)->snapshot(10, false);

    expect($snapshot)->toHaveKeys(['collected_at', 'app', 'checks', 'healthy'])
        ->and($snapshot)->not->toHaveKey('logs')
        ->and($snapshot['app'])->toHaveKeys([
            'name',
            'env',
            'debug',
            'url',
            'timezone',
            'locale',
            'laravel_version',
            'php_version',
        ])
        ->and($snapshot['checks'])->toHaveKeys([
            'storage_writable',
            'cache',
            'database',
            'queue',
            'log_file',
        ])
        ->and($snapshot['checks']['storage_writable']['ok'])->toBeTrue()
        ->and($snapshot['checks']['queue']['ok'])->toBeTrue();
});

it('returns a redacted log tail when requested', function (): void {
    $logPath = storage_path('logs/laravel.log');

    if (! is_dir(dirname($logPath))) {
        mkdir(dirname($logPath), 0777, true);
    }

    file_put_contents(
        $logPath,
        "plain info line\n".
        "Authorization: Bearer super-secret-token\n".
        "password=hunter2 failed login\n".
        "APP_KEY=base64:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa=\n"
    );

    config([
        'logging.default' => 'single',
        'logging.channels.single.path' => $logPath,
    ]);

    $snapshot = app(DiagnosticsService::class)->snapshot(50, true);

    expect($snapshot['logs']['available'])->toBeTrue()
        ->and($snapshot['logs']['redacted'])->toBeTrue()
        ->and($snapshot['logs']['lines_returned'])->toBeGreaterThan(0)
        ->and(implode("\n", $snapshot['logs']['entries']))->toContain('[REDACTED]')
        ->and(implode("\n", $snapshot['logs']['entries']))->not->toContain('super-secret-token')
        ->and(implode("\n", $snapshot['logs']['entries']))->not->toContain('hunter2');
});

it('redacts common secret patterns', function (): void {
    $service = app(DiagnosticsService::class);

    expect($service->redact('Authorization: Bearer abc.def.ghi'))
        ->toBe('Authorization: Bearer [REDACTED]')
        ->and($service->redact('api_key=sk_live_1234567890abcdef'))
        ->toContain('[REDACTED]')
        ->and($service->redact('mysql://user:s3cret@localhost/db'))
        ->toContain('[REDACTED]')
        ->and($service->redact('AKIAIOSFODNN7EXAMPLE'))
        ->toBe('[REDACTED_AWS_KEY]');
});

it('reports diagnostics via artisan', function (): void {
    expect(Artisan::call('larapilot:diagnostics', ['--no-logs' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'])->toBe('diagnostics')
        ->and($envelope['data'])->toHaveKeys(['collected_at', 'app', 'checks', 'healthy'])
        ->and($envelope['data'])->not->toHaveKey('logs');
});

it('caps log lines from artisan option', function (): void {
    $logPath = storage_path('logs/laravel.log');

    if (! is_dir(dirname($logPath))) {
        mkdir(dirname($logPath), 0777, true);
    }

    file_put_contents($logPath, implode("\n", array_map(
        fn (int $i): string => "line-{$i}",
        range(1, 40)
    ))."\n");

    config([
        'logging.default' => 'single',
        'logging.channels.single.path' => $logPath,
    ]);

    expect(Artisan::call('larapilot:diagnostics', ['--lines' => 5]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['logs']['lines_requested'])->toBe(5)
        ->and($envelope['data']['logs']['lines_returned'])->toBe(5)
        ->and($envelope['data']['logs']['entries'])->toHaveCount(5);
});
