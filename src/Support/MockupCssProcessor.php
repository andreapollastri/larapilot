<?php

declare(strict_types=1);

namespace Larapilot\Support;

class MockupCssProcessor
{
    public function __construct(protected MockupAssetResolver $assets) {}

    /**
     * @param  (callable(array{path: string, url: string}): string)|null  $urlMapper
     */
    public function process(string $css, string $spec, string $mockupRoot, string $currentRelativePath, ?string $designSystem = null, ?callable $urlMapper = null): string
    {
        $currentDir = dirname(str_replace('\\', '/', $currentRelativePath));
        $currentDir = $currentDir === '.' ? '' : $currentDir;

        return (string) preg_replace_callback(
            '/url\(\s*(?<quote>["\']?)(?<url>(?!(?:https?:|\/\/|\/|data:|#))[^"\')]+)\k<quote>\s*\)/i',
            function (array $matches) use ($spec, $mockupRoot, $currentDir, $designSystem, $urlMapper): string {
                $resolved = $this->assets->resolveAssetReference(
                    $spec,
                    $mockupRoot,
                    $currentDir,
                    trim($matches['url']),
                    $designSystem
                );

                if ($resolved === null) {
                    return $matches[0];
                }

                $quote = $matches['quote'] !== '' ? $matches['quote'] : '"';
                $url = $resolved['url'];

                if ($urlMapper !== null) {
                    $mapped = $urlMapper($resolved);
                    if (is_string($mapped) && $mapped !== '') {
                        $url = $mapped;
                    }
                }

                return 'url('.$quote.$url.$quote.')';
            },
            $css
        );
    }
}
