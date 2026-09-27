<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Larapilot\Services\ConfigService;
use Larapilot\Services\CustomSkillService;
use Larapilot\Services\FileManagerService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileManagerController
{
    public function __construct(
        protected ConfigService $config,
        protected FileManagerService $files,
        protected CustomSkillService $customSkills,
    ) {}

    public function index(): View
    {
        $this->guard();

        return view('larapilot::dashboard.files', [
            'folders' => $this->files->summary(),
        ]);
    }

    public function browse(string $root, ?string $path = null): View
    {
        $this->guard();

        $data = $this->files->browse($root, (string) $path);

        if ($data === null) {
            abort(404);
        }

        return view('larapilot::dashboard.files-browse', array_merge($data, [
            'folders' => array_values($this->files->roots()),
            'browse' => fn (string $to = ''): string => $this->files->url($root, $to),
            'raw' => fn (string $of, bool $download = false): string => $this->files->url($root, $of, 'raw').($download ? '?download=1' : ''),
        ]));
    }

    public function raw(Request $request, string $root, string $path): BinaryFileResponse
    {
        $this->guard();

        $absolute = $this->files->file($root, $path);

        if ($absolute === null) {
            abort(404);
        }

        $delivery = $this->files->delivery($absolute);
        $inline = $delivery['inline'] && ! $request->boolean('download');

        $headers = ['Content-Type' => $inline ? $delivery['type'] : 'application/octet-stream'];

        // An uploaded file is never trusted: no scripts and no requests of
        // its own, even for an SVG opened in its own tab. Browsers refuse to
        // show a sandboxed PDF, and a PDF cannot reach the page anyway.
        if ($delivery['type'] !== 'application/pdf') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox";
        }

        $response = response()->file($absolute, $headers);
        $response->setContentDisposition($inline ? 'inline' : 'attachment', $this->downloadName($absolute));

        return $response;
    }

    public function upload(Request $request, string $root): JsonResponse|RedirectResponse
    {
        $this->guardWrites($request);

        $directory = (string) $request->input('path', '');
        $files = $request->file('files', []);
        $files = is_array($files) ? $files : [$files];
        $paths = $request->input('paths', []);

        try {
            if ($files === []) {
                throw new InvalidArgumentException('Choose at least one file or folder to upload.');
            }

            $result = $this->keepingSkillsRegistered($root, $directory, fn (): array => $this->files->upload(
                $root,
                $directory,
                $files,
                is_array($paths) ? array_values($paths) : [],
                $request->boolean('replace')
            ));
        } catch (InvalidArgumentException $e) {
            return $this->failed($request, $root, $directory, $e->getMessage());
        }

        $stored = count($result['stored']);
        $skipped = count($result['skipped']);
        $message = $stored === 1 ? '1 file uploaded.' : $stored.' files uploaded.';

        if ($skipped > 0) {
            $message .= ' '.($skipped === 1 ? '1 was skipped.' : $skipped.' were skipped.');
        }

        if ($request->expectsJson()) {
            return response()->json(array_merge($result, ['message' => $message]), $stored === 0 && $skipped > 0 ? 422 : 200);
        }

        return $this->back($root, $directory)
            ->with($stored > 0 ? 'larapilot_success' : 'larapilot_error', $message)
            ->with('larapilot_details', array_map(
                static fn (array $item): string => $item['path'].' — '.$item['reason'],
                $result['skipped']
            ));
    }

    public function folder(Request $request, string $root): JsonResponse|RedirectResponse
    {
        $this->guardWrites($request);

        $directory = (string) $request->input('path', '');

        try {
            $created = $this->keepingSkillsRegistered($root, $directory, fn (): array => $this->files->createFolder(
                $root,
                $directory,
                (string) $request->input('name', '')
            ));
        } catch (InvalidArgumentException $e) {
            return $this->failed($request, $root, $directory, $e->getMessage());
        }

        return $this->back($root, $created['path'])
            ->with('larapilot_success', 'Folder “'.$created['name'].'” created.');
    }

    public function rename(Request $request, string $root): JsonResponse|RedirectResponse
    {
        $this->guardWrites($request);

        $path = (string) $request->input('path', '');
        $directory = $this->directoryOf($path);

        try {
            $renamed = $this->keepingSkillsRegistered($root, $path, fn (): array => $this->files->rename(
                $root,
                $path,
                (string) $request->input('name', '')
            ));
        } catch (InvalidArgumentException $e) {
            return $this->failed($request, $root, $directory, $e->getMessage());
        }

        $message = '“'.$renamed['from'].'” is now “'.$renamed['name'].'”.';

        if ($renamed['is_directory'] && $this->files->isSkillFolder($root, $renamed['path'])) {
            $message .= ' The slash command follows the name in SKILL.md — update it there too.';
        }

        // Stay where the user was: the folder that holds the renamed item, or
        // the item itself when it was open on screen.
        $target = $request->boolean('open') ? $renamed['path'] : $directory;

        return $this->back($root, $target)->with('larapilot_success', $message);
    }

    public function destroy(Request $request, string $root): JsonResponse|RedirectResponse
    {
        $this->guardWrites($request);

        $path = (string) $request->input('path', '');
        $directory = $this->directoryOf($path);
        $kept = [];

        try {
            $deleted = $this->keepingSkillsRegistered($root, $path, fn (): array => $this->files->delete($root, $path), $kept);
        } catch (InvalidArgumentException $e) {
            return $this->failed($request, $root, $directory, $e->getMessage());
        }

        $message = $deleted['is_directory']
            ? 'Folder “'.$deleted['name'].'” deleted'.($deleted['removed'] > 1 ? ' with everything in it.' : '.')
            : '“'.$deleted['name'].'” deleted.';

        $response = $this->back($root, $deleted['parent'])->with('larapilot_success', $message);

        if ($kept !== []) {
            $response->with('larapilot_details', array_map(
                static fn (string $mirror): string => $mirror.' — a registered copy that no longer matched the skill was left in place. Remove it by hand.',
                $kept
            ));
        }

        return $response;
    }

    /**
     * Custom skills are mirrored into `.ai/skills/` and the agent folders.
     * A change under `.larapilot/skills/{name}/` drops the mirror of that
     * skill first and mirrors what is left afterwards, so a deleted file or
     * a deleted skill does not live on as a slash command.
     *
     * @template T
     *
     * @param  callable(): T  $change
     * @param  list<string>  $kept
     * @return T
     */
    protected function keepingSkillsRegistered(string $root, string $path, callable $change, array &$kept = []): mixed
    {
        if ($root !== 'skills') {
            return $change();
        }

        try {
            $skill = explode('/', $this->files->normalize($path))[0];
        } catch (InvalidArgumentException) {
            $skill = '';
        }

        if ($skill !== '') {
            $kept = $this->customSkills->unregister($skill)['kept'];
        }

        try {
            return $change();
        } finally {
            try {
                $this->customSkills->registerAll();
            } catch (\Throwable) {
                // The files are saved; the Skills page registers on its next visit.
            }
        }
    }

    protected function directoryOf(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $position = strrpos($path, '/');

        return $position === false ? '' : substr($path, 0, $position);
    }

    protected function downloadName(string $absolute): string
    {
        $name = (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\"]/', '_', basename($absolute));

        return $name === '' ? 'download' : $name;
    }

    protected function back(string $root, string $path): RedirectResponse
    {
        try {
            $path = $this->files->normalize($path);
        } catch (InvalidArgumentException) {
            $path = '';
        }

        return redirect()->to($this->files->url($root, $path));
    }

    protected function failed(Request $request, string $root, string $directory, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'stored' => [], 'skipped' => []], 422);
        }

        return $this->back($root, $directory)->with('larapilot_error', $message);
    }

    protected function guard(): void
    {
        if (! $this->config->fileManagerBrowsable()) {
            abort(404);
        }
    }

    protected function guardWrites(Request $request): void
    {
        $this->guard();

        if ($this->files->root((string) $request->route('root')) === null) {
            abort(404);
        }
    }
}
