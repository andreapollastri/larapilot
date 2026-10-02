<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Larapilot\Services\ConfigService;
use Larapilot\Services\LogViewerService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogViewerController
{
    public function __construct(
        protected ConfigService $config,
        protected LogViewerService $logs,
    ) {}

    /**
     * The file the application writes to now, or what to do when the
     * folder holds no log yet.
     */
    public function index(Request $request): View|StreamedResponse
    {
        $this->guard();

        $file = $this->logs->current();

        if ($file === null) {
            return view('larapilot::dashboard.logs', [
                'files' => [],
                'file' => null,
                'directory' => $this->logs->directoryLabel(),
                'secrets_allowed' => $this->secretsAllowed(),
            ]);
        }

        return $this->open($request, $file);
    }

    /**
     * A file by its name without the `.log`. The address it had with the
     * extension — a bookmark, a link in a ticket — leads to this one.
     */
    public function show(Request $request, string $file): View|StreamedResponse|RedirectResponse
    {
        $this->guard();

        $found = $this->logs->locate($file);

        if ($found !== null) {
            return $this->open($request, $found);
        }

        $named = $this->logs->find($file);

        if ($named === null) {
            abort(404);
        }

        $query = $request->getQueryString();

        return redirect()->to($this->logs->url($named['key']).($query === null ? '' : '?'.$query));
    }

    /**
     * @param  array{key: string, name: string, absolute: string, size: int, size_label: string, modified: int, modified_label: string, current: bool}  $file
     */
    protected function open(Request $request, array $file): View|StreamedResponse
    {
        if ($request->boolean('download')) {
            return $this->download($request, $file);
        }

        $options = [
            'level' => $this->text($request, 'level'),
            'search' => $this->text($request, 'q'),
            'since' => $this->text($request, 'since'),
        ];
        $grouped = $request->query('view') === 'groups';
        $overview = $this->logs->overview($file);

        $data = $grouped
            ? $this->logs->groups($file, $options + ['limit' => 100])
            : $this->logs->read($file, $options + ['before' => max(0, $request->integer('before'))]);

        return view('larapilot::dashboard.logs', $data + [
            'files' => $this->logs->files(),
            'file' => $file,
            'directory' => $this->logs->directoryLabel(),
            'overview' => $overview,
            'grouped' => $grouped,
            'before' => $grouped ? 0 : max(0, $request->integer('before')),
            'per_page' => $this->logs->perPage(),
            'scan_label' => $this->logs->formatBytes($this->logs->budget()),
            'secrets_allowed' => $this->secretsAllowed(),
            'link' => fn (array $query = []): string => $this->logs->url($file['key'], $query),
            'open' => fn (string $key): string => $this->logs->url($key),
        ]);
    }

    /**
     * The file, written while it downloads. Secrets are redacted; they go
     * out as they were written only when that is asked for, and only from
     * a developer's own machine.
     *
     * @param  array{name: string, absolute: string}  $file
     */
    protected function download(Request $request, array $file): StreamedResponse
    {
        $asWritten = $request->boolean('secrets') && $this->secretsAllowed();

        return response()->streamDownload(function () use ($file, $asWritten): void {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $this->logs->download($file, static function (string $chunk): void {
                echo $chunk;
            }, $asWritten);
        }, $this->downloadName($file['name']), [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function text(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_scalar($value) ? (string) $value : null;
    }

    protected function downloadName(string $name): string
    {
        $name = (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\"]/', '_', $name);

        return $name === '' ? 'laravel.log' : $name;
    }

    /**
     * Tokens and passwords a log quotes leave the server only from a
     * developer's own machine — never from a shared host, sign-in or not.
     */
    protected function secretsAllowed(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    protected function guard(): void
    {
        if (! $this->config->logViewerBrowsable()) {
            abort(404);
        }
    }
}
