<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

use Larapilot\Support\AtomicFile;
use Symfony\Component\Process\Process;

/**
 * The project graph as Nx computes it — targets inferred by plugins
 * included — read with the `nx` the workspace installed. Nothing is
 * downloaded: without `node_modules/.bin/nx` the inspector reads the files
 * instead. A graph is kept until the workspace files or the commit move.
 */
final class NxGraph
{
    public const TIMEOUT = 180;

    public function __construct(
        protected string $cacheDirectory,
    ) {}

    public static function binary(string $root): ?string
    {
        foreach (['nx', 'nx.cmd'] as $name) {
            $path = $root.'/node_modules/.bin/'.$name;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     ok: bool,
     *     cached?: bool,
     *     error?: string,
     *     projects?: array<string, array<string, mixed>>,
     *     dependencies?: array<string, list<string>>
     * }
     */
    public function read(string $root, string $fingerprint, bool $fresh = false): array
    {
        $cacheFile = $this->cacheDirectory.'/nx-graph-'.substr(sha1($root), 0, 12).'.json';

        if (! $fresh) {
            $cached = RepoFiles::json($cacheFile);

            if (is_array($cached) && ($cached['fingerprint'] ?? null) === $fingerprint && is_array($cached['projects'] ?? null)) {
                return [
                    'ok' => true,
                    'cached' => true,
                    'projects' => $cached['projects'],
                    'dependencies' => is_array($cached['dependencies'] ?? null) ? $cached['dependencies'] : [],
                ];
            }
        }

        $binary = self::binary($root);

        if ($binary === null) {
            return ['ok' => false, 'error' => 'nx is not installed in node_modules — the packages of the workspace are not installed.'];
        }

        $output = sys_get_temp_dir().'/larapilot-nx-graph-'.getmypid().'-'.bin2hex(random_bytes(4)).'.json';

        $process = new Process([$binary, 'graph', '--file='.$output], $root, [
            'NX_DAEMON' => 'false',
            'NX_NO_CLOUD' => 'true',
            'NX_TUI' => 'false',
            'NX_INTERACTIVE' => 'false',
            'CI' => 'true',
            'FORCE_COLOR' => '0',
        ], null, self::TIMEOUT);

        try {
            $process->run();
        } catch (\Throwable $exception) {
            @unlink($output);

            return ['ok' => false, 'error' => 'nx graph did not run: '.$exception->getMessage()];
        }

        $decoded = RepoFiles::json($output);
        @unlink($output);

        if (! $process->isSuccessful() || ! is_array($decoded)) {
            $tail = trim(substr(trim($process->getErrorOutput()."\n".$process->getOutput()), -400));

            return ['ok' => false, 'error' => 'nx graph failed'.($tail !== '' ? ': '.$tail : '.')];
        }

        $graph = is_array($decoded['graph'] ?? null) ? $decoded['graph'] : $decoded;
        [$projects, $dependencies] = $this->normalize($graph);

        if ($projects === []) {
            return ['ok' => false, 'error' => 'nx graph returned no projects.'];
        }

        if (! is_dir($this->cacheDirectory)) {
            @mkdir($this->cacheDirectory, 0755, true);
        }

        if (is_dir($this->cacheDirectory)) {
            if (! is_file($this->cacheDirectory.'/.gitignore')) {
                @file_put_contents($this->cacheDirectory.'/.gitignore', "*\n");
            }

            AtomicFile::write($cacheFile, (string) json_encode([
                'fingerprint' => $fingerprint,
                'generated_at' => date(DATE_ATOM),
                'projects' => $projects,
                'dependencies' => $dependencies,
            ], JSON_UNESCAPED_SLASHES));
        }

        return ['ok' => true, 'cached' => false, 'projects' => $projects, 'dependencies' => $dependencies];
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, list<string>>}
     */
    public function normalize(array $graph): array
    {
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $projects = [];

        foreach ($nodes as $key => $node) {
            if (! is_array($node)) {
                continue;
            }

            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $name = is_string($node['name'] ?? null) ? $node['name'] : (string) $key;
            $root = is_string($data['root'] ?? null) && $data['root'] !== '' ? trim($data['root'], '/') : '.';
            $targets = [];

            foreach (is_array($data['targets'] ?? null) ? $data['targets'] : [] as $target => $definition) {
                if (! is_string($target) || ! is_array($definition)) {
                    continue;
                }

                $targets[$target] = WorkspaceInspector::target($definition, 'nx-cli');
            }

            $nodeType = is_string($node['type'] ?? null) ? $node['type'] : null;
            $projectType = is_string($data['projectType'] ?? null) ? $data['projectType'] : null;

            $projects[$name] = [
                'name' => $name,
                'root' => $root === '' ? '.' : $root,
                'source_root' => is_string($data['sourceRoot'] ?? null) ? trim($data['sourceRoot'], '/') : null,
                'type' => match (true) {
                    $nodeType === 'e2e' => 'e2e',
                    $nodeType === 'app', $projectType === 'application' => 'application',
                    default => 'library',
                },
                'tags' => array_values(array_filter(is_array($data['tags'] ?? null) ? $data['tags'] : [], 'is_string')),
                'targets' => $targets,
                'package' => is_string($data['metadata']['js']['packageName'] ?? null) ? $data['metadata']['js']['packageName'] : null,
            ];
        }

        $dependencies = [];

        foreach (is_array($graph['dependencies'] ?? null) ? $graph['dependencies'] : [] as $source => $edges) {
            if (! isset($projects[$source]) || ! is_array($edges)) {
                continue;
            }

            $targets = [];

            foreach ($edges as $edge) {
                $target = is_array($edge) ? ($edge['target'] ?? null) : null;

                if (is_string($target) && isset($projects[$target]) && $target !== $source) {
                    $targets[$target] = true;
                }
            }

            $dependencies[(string) $source] = array_keys($targets);
        }

        return [$projects, $dependencies];
    }
}
