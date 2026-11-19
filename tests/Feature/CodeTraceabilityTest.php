<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\CodeTraceabilityService;
use Larapilot\Services\ConfigService;

it('adds traceability PHPDoc while preserving existing tags', function (): void {
    $source = <<<'PHP'
<?php

class TraceFixture
{
    public function plain(): void
    {
    }

    /**
     * Old API.
     *
     * @deprecated Use plain().
     */
    public function old(): void
    {
    }

    public function closure(): callable
    {
        return function (): void {};
    }
}
PHP;

    $service = app(CodeTraceabilityService::class);
    [$annotated, $changed] = $service->annotate(
        $source,
        'US-001 | Login | TASK-01: Create model',
        ['.larapilot/docs/devs/auth.md']
    );

    expect($changed)->toBe(3)
        ->and(substr_count($annotated, 'US-001 | Login | TASK-01: Create model'))->toBe(3)
        ->and(substr_count($annotated, '@see .larapilot/docs/devs/auth.md'))->toBe(3)
        ->and($annotated)->toContain('@deprecated Use plain().');

    [$secondPass, $secondChanged] = $service->annotate(
        $annotated,
        'US-001 | Login | TASK-01: Create model',
        ['.larapilot/docs/devs/auth.md']
    );

    expect($secondChanged)->toBe(0)
        ->and($secondPass)->toBe($annotated);
});

it('backfills existing code through trace-code and honors the setting', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    $directory = base_path('app/TraceabilityFixtures');
    $file = $directory.'/Example.php';
    is_dir($directory) || mkdir($directory, 0755, true);
    file_put_contents($file, "<?php\n\nclass Example\n{\n    public function run(): void {}\n}\n");

    try {
        $this->artisan('larapilot:trace-code', [
            'spec' => 'US-001',
            '--task' => 'TASK-01',
            '--see' => ['.larapilot/docs/devs/auth.md'],
            '--path' => [$file],
        ])->assertSuccessful();

        expect(file_get_contents($file))
            ->toContain('US-001 | Login | TASK-01: Create model')
            ->toContain('@see .larapilot/docs/devs/auth.md');

        app(ConfigService::class)->updateSettings(['traceability_comments' => false]);

        expect(Artisan::call('larapilot:trace-code', [
            'spec' => 'US-001',
            '--see' => ['.larapilot/docs/devs/auth.md'],
            '--path' => [$file],
        ]))->toBe(4)
            ->and(Artisan::output())->toContain('Traceability comments are disabled');
    } finally {
        @unlink($file);
        @rmdir($directory);
        @rmdir(dirname($directory));
    }
});
