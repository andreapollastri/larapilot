<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DatabaseDumpService;
use Larapilot\Services\DatabaseViewerService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseViewerController
{
    public function __construct(
        protected ConfigService $config,
        protected DatabaseViewerService $database,
        protected DatabaseDumpService $dumps,
    ) {}

    public function index(): View
    {
        $this->guard();

        return view('larapilot::dashboard.database', $this->database->overview() + [
            'credentials_allowed' => $this->credentialsAllowed(),
        ]);
    }

    /**
     * The whole database as one SQL file, written while it downloads.
     */
    public function dump(Request $request): StreamedResponse|RedirectResponse
    {
        $this->guard();

        $error = $this->database->overview()['error'];

        if ($error !== null) {
            return redirect()
                ->route('larapilot.dashboard.database')
                ->with('larapilot_error', 'The database cannot be read, so there is nothing to download.');
        }

        $credentials = $request->boolean('credentials') && $this->credentialsAllowed();

        return response()->streamDownload(function () use ($credentials): void {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $this->dumps->dump(static function (string $chunk): void {
                echo $chunk;
            }, $credentials);
        }, $this->dumps->filename(), [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function table(Request $request, string $table): View
    {
        $this->guard();

        $data = $this->database->table($table, [
            'page' => max(1, $request->integer('page', 1)),
            'sort' => $this->text($request, 'sort'),
            'direction' => $this->text($request, 'dir'),
            'search' => $this->text($request, 'q'),
            'where' => $this->text($request, 'where'),
            'is' => $request->has('is') ? (string) $this->text($request, 'is') : null,
        ]);

        if ($data === null) {
            abort(404);
        }

        if ($data['object'] === null) {
            return view('larapilot::dashboard.database', $data + [
                'credentials_allowed' => $this->credentialsAllowed(),
            ]);
        }

        return view('larapilot::dashboard.database-table', $data + [
            'tab' => $request->query('tab') === 'structure' ? 'structure' : 'rows',
        ]);
    }

    protected function text(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Passwords, tokens, and secrets leave the server in a dump only from
     * a developer's own machine — never from a shared host, sign-in or not.
     */
    protected function credentialsAllowed(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    protected function guard(): void
    {
        if (! $this->config->databaseViewerBrowsable()) {
            abort(404);
        }
    }
}
