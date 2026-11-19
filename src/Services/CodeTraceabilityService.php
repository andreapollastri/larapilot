<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\AtomicFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Adds spec/task and technical-documentation references to PHP methods.
 *
 * The tokenizer keeps this safe for existing code: method bodies are never
 * parsed or rewritten, and existing PHPDoc (including @deprecated) is kept.
 */
class CodeTraceabilityService
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $references
     * @return array{files: list<array{path: string, methods: int}>, scanned_files: int, changed_files: int, changed_methods: int}
     */
    public function apply(array $paths, string $trace, array $references, bool $write = true): array
    {
        $files = $this->phpFiles($paths);
        $changed = [];
        $changedMethods = 0;

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            [$updated, $methods] = $this->annotate($source, $trace, $references);

            if ($methods === 0) {
                continue;
            }

            if ($write) {
                AtomicFile::write($file, $updated);
            }

            $changed[] = [
                'path' => $this->relativePath($file),
                'methods' => $methods,
            ];
            $changedMethods += $methods;
        }

        return [
            'files' => $changed,
            'scanned_files' => count($files),
            'changed_files' => count($changed),
            'changed_methods' => $changedMethods,
        ];
    }

    /**
     * @param  list<string>  $references
     * @return array{0: string, 1: int}
     */
    public function annotate(string $source, string $trace, array $references): array
    {
        $trace = $this->oneLine($trace);
        $references = array_values(array_unique(array_filter(
            array_map(fn (string $reference): string => $this->oneLine($reference), $references)
        )));

        if ($trace === '' || $references === []) {
            return [$source, 0];
        }

        $tokens = token_get_all($source);
        $offsets = [];
        $offset = 0;
        $braceDepth = 0;
        $typeDepths = [];
        $pendingType = false;
        $edits = [];

        foreach ($tokens as $index => $token) {
            $text = is_array($token) ? $token[1] : $token;
            $offsets[$index] = $offset;

            if (is_array($token)
                && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
                && ! ($token[0] === T_CLASS && $this->previousSignificantTokenId($tokens, $index) === T_DOUBLE_COLON)
            ) {
                $pendingType = true;
            }

            if ($text === '{') {
                $braceDepth++;

                if ($pendingType) {
                    $typeDepths[] = $braceDepth;
                    $pendingType = false;
                }
            } elseif ($text === '}') {
                if ($typeDepths !== [] && end($typeDepths) === $braceDepth) {
                    array_pop($typeDepths);
                }

                $braceDepth--;
            }

            if (is_array($token) && $token[0] === T_FUNCTION && $typeDepths !== [] && $this->namedFunction($tokens, $index)) {
                $docIndex = $this->docCommentBefore($tokens, $index);

                if ($docIndex !== null) {
                    $doc = is_array($tokens[$docIndex]) ? $tokens[$docIndex][1] : '';
                    $updated = $this->mergeDocblock($doc, $trace, $references);

                    if ($updated !== $doc) {
                        $edits[] = [$offsets[$docIndex], strlen($doc), $updated];
                    }
                } else {
                    $at = $this->declarationStart($source, $offset);
                    $indent = $this->indentAt($source, $at);
                    $edits[] = [$at, 0, $this->newDocblock($indent, $trace, $references)];
                }
            }

            $offset += strlen($text);
        }

        if ($edits === []) {
            return [$source, 0];
        }

        foreach (array_reverse($edits) as [$at, $length, $replacement]) {
            $source = substr_replace($source, $replacement, $at, $length);
        }

        return [$source, count($edits)];
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    protected function namedFunction(array $tokens, int $functionIndex): bool
    {
        for ($index = $functionIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($token === '&') {
                continue;
            }

            return is_array($token) && $token[0] === T_STRING;
        }

        return false;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    protected function previousSignificantTokenId(array $tokens, int $index): ?int
    {
        for ($index--; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : null;
        }

        return null;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    protected function docCommentBefore(array $tokens, int $functionIndex): ?int
    {
        for ($index = $functionIndex - 1; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                return $index;
            }

            if (is_array($token) && $token[0] === T_COMMENT) {
                return null;
            }

            if (in_array($token, [';', '{', '}'], true)) {
                return null;
            }
        }

        return null;
    }

    /**
     * Put a new block before same-line modifiers. Attributes on preceding
     * lines remain immediately above the declaration and below its PHPDoc.
     */
    protected function declarationStart(string $source, int $functionOffset): int
    {
        $lineStart = strrpos(substr($source, 0, $functionOffset), "\n");

        return $lineStart === false ? 0 : $lineStart + 1;
    }

    protected function indentAt(string $source, int $offset): string
    {
        preg_match('/^[ \t]*/', substr($source, $offset), $match);

        return $match[0] ?? '';
    }

    /**
     * @param  list<string>  $references
     */
    protected function newDocblock(string $indent, string $trace, array $references): string
    {
        $lines = [
            $indent.'/**',
            $indent.' * '.$trace,
            $indent.' *',
        ];

        foreach ($references as $reference) {
            $lines[] = $indent.' * @see '.$reference;
        }

        $lines[] = $indent.' */';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $references
     */
    protected function mergeDocblock(string $doc, string $trace, array $references): string
    {
        $missingReferences = array_values(array_filter(
            $references,
            static fn (string $reference): bool => ! preg_match('/^\s*\*\s*@see\s+'.preg_quote($reference, '/').'\s*$/m', $doc)
        ));

        if (str_contains($doc, '* '.$trace) && $missingReferences === []) {
            return $doc;
        }

        $lines = preg_split('/\R/', $doc) ?: [$doc];
        $closing = array_pop($lines);
        $indent = '';

        if (preg_match('/^([ \t]*)\*\//', (string) $closing, $match) === 1) {
            $indent = $match[1];
        }

        $insertAt = count($lines);
        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*\*\s*@/', $line) === 1) {
                $insertAt = $index;
                break;
            }
        }

        $addition = [];
        if (! str_contains($doc, '* '.$trace)) {
            $addition[] = $indent.' * '.$trace;
        }

        if ($missingReferences !== []) {
            if ($addition !== []) {
                $addition[] = $indent.' *';
            }

            foreach ($missingReferences as $reference) {
                $addition[] = $indent.' * @see '.$reference;
            }
        }

        if ($insertAt > 0 && trim($lines[$insertAt - 1]) !== '*') {
            array_unshift($addition, $indent.' *');
        }

        array_splice($lines, $insertAt, 0, $addition);
        $lines[] = $closing;

        return implode("\n", $lines);
    }

    protected function oneLine(string $value): string
    {
        return trim(str_replace('*/', '', (string) preg_replace('/\s+/', ' ', $value)));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function phpFiles(array $paths): array
    {
        $root = realpath(base_path());

        if ($root === false) {
            return [];
        }

        $files = [];

        foreach ($paths as $path) {
            $candidate = str_starts_with($path, '/') ? $path : $root.'/'.ltrim($path, '/');
            $real = realpath($candidate);

            if ($real === false || ! ($real === $root || str_starts_with($real, $root.DIRECTORY_SEPARATOR))) {
                continue;
            }

            if (is_file($real) && str_ends_with(strtolower($real), '.php')) {
                $files[$real] = $real;

                continue;
            }

            if (! is_dir($real)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($real, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $item */
            foreach ($iterator as $item) {
                if ($item->isFile() && ! $item->isLink() && strtolower($item->getExtension()) === 'php') {
                    $files[$item->getRealPath()] = $item->getRealPath();
                }
            }
        }

        ksort($files);

        return array_values($files);
    }

    protected function relativePath(string $path): string
    {
        $root = rtrim((string) realpath(base_path()), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($path, $root) ? str_replace('\\', '/', substr($path, strlen($root))) : $path;
    }
}
