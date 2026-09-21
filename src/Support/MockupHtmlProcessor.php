<?php

declare(strict_types=1);

namespace Larapilot\Support;

class MockupHtmlProcessor
{
    public function __construct(protected MockupAssetResolver $assets) {}

    /**
     * @param  (callable(array{path: string, url: string}): string)|null  $urlMapper
     */
    public function process(string $html, string $spec, string $mockupRoot, string $currentRelativePath, ?callable $urlMapper = null): string
    {
        $currentDir = dirname(str_replace('\\', '/', $currentRelativePath));
        $currentDir = $currentDir === '.' ? '' : $currentDir;
        $designSystem = $this->detectDesignSystemFromHtml($html);

        $html = $this->rewriteAttributes($html, $spec, $mockupRoot, $currentDir, $designSystem, $urlMapper);
        $html = $this->rewriteStyleUrls($html, $spec, $mockupRoot, $currentDir, $designSystem, $urlMapper);

        return $html;
    }

    /**
     * @param  (callable(array{path: string, url: string}): string)|null  $urlMapper
     */
    protected function rewriteAttributes(string $html, string $spec, string $mockupRoot, string $currentDir, ?string $designSystem, ?callable $urlMapper = null): string
    {
        $html = (string) preg_replace_callback(
            '/(?<attr>href|src|poster)\s*=\s*(?<quote>["\'])(?<url>(?!(?:https?:|\/\/|\/|#|data:|mailto:))[^"\']+)\k<quote>/i',
            function (array $matches) use ($spec, $mockupRoot, $currentDir, $designSystem, $urlMapper): string {
                $resolved = $this->assets->resolveAssetReference(
                    $spec,
                    $mockupRoot,
                    $currentDir,
                    $matches['url'],
                    $designSystem
                );

                if ($resolved === null) {
                    return $matches[0];
                }

                return $matches['attr'].'='.$matches['quote'].$this->mappedUrl($resolved, $urlMapper).$matches['quote'];
            },
            $html
        );

        return (string) preg_replace_callback(
            '/\bsrcset\s*=\s*(?<quote>["\'])(?<value>[^"\']+)\k<quote>/i',
            function (array $matches) use ($spec, $mockupRoot, $currentDir, $designSystem, $urlMapper): string {
                $rewritten = preg_replace_callback(
                    '/(?<url>[^\s,]+)(?<descriptor>\s+(?:\d+(?:\.\d+)?[wx]|[\d.]+x))?/i',
                    function (array $part) use ($spec, $mockupRoot, $currentDir, $designSystem, $urlMapper): string {
                        $url = trim($part['url']);

                        if ($this->assets->isAbsoluteReference($url)) {
                            return $part[0];
                        }

                        $resolved = $this->assets->resolveAssetReference(
                            $spec,
                            $mockupRoot,
                            $currentDir,
                            $url,
                            $designSystem
                        );

                        if ($resolved === null) {
                            return $part[0];
                        }

                        return $this->mappedUrl($resolved, $urlMapper).($part['descriptor'] ?? '');
                    },
                    $matches['value']
                );

                return 'srcset='.$matches['quote'].$rewritten.$matches['quote'];
            },
            $html
        );
    }

    /**
     * @param  (callable(array{path: string, url: string}): string)|null  $urlMapper
     */
    protected function rewriteStyleUrls(string $html, string $spec, string $mockupRoot, string $currentDir, ?string $designSystem, ?callable $urlMapper = null): string
    {
        return (string) preg_replace_callback(
            '/\bstyle\s*=\s*(?<quote>["\'])(?<value>.*?)\k<quote>/is',
            function (array $matches) use ($spec, $mockupRoot, $currentDir, $designSystem, $urlMapper): string {
                $style = (string) preg_replace_callback(
                    '/url\(\s*(?<innerquote>["\']?)(?<url>(?!(?:https?:|\/\/|\/|data:|#))[^"\')]+)\k<innerquote>\s*\)/i',
                    function (array $urlMatch) use ($spec, $mockupRoot, $currentDir, $designSystem, $urlMapper): string {
                        $resolved = $this->assets->resolveAssetReference(
                            $spec,
                            $mockupRoot,
                            $currentDir,
                            trim($urlMatch['url']),
                            $designSystem
                        );

                        if ($resolved === null) {
                            return $urlMatch[0];
                        }

                        $quote = $urlMatch['innerquote'] !== '' ? $urlMatch['innerquote'] : '"';

                        return 'url('.$quote.$this->mappedUrl($resolved, $urlMapper).$quote.')';
                    },
                    $matches['value']
                );

                return 'style='.$matches['quote'].$style.$matches['quote'];
            },
            $html
        );
    }

    /**
     * @param  array{path: string, url: string}  $resolved
     * @param  (callable(array{path: string, url: string}): string)|null  $urlMapper
     */
    protected function mappedUrl(array $resolved, ?callable $urlMapper): string
    {
        if ($urlMapper === null) {
            return $resolved['url'];
        }

        $mapped = $urlMapper($resolved);

        return is_string($mapped) && $mapped !== '' ? $mapped : $resolved['url'];
    }

    protected function detectDesignSystemFromHtml(string $html): ?string
    {
        if (preg_match('/\bfilament-tokens\.css\b/i', $html) || str_contains($html, 'fi-mockup')) {
            return 'filament';
        }

        if (preg_match('/\bstarter-kit-tokens\.css\b/i', $html) || str_contains($html, 'sk-mockup')) {
            return 'starter-kit';
        }

        if (preg_match('/\bbootstrap-tokens\.css\b/i', $html) || str_contains($html, 'bs-mockup')) {
            return 'bootstrap-5';
        }

        if (preg_match('/\badminlte-tokens\.css\b/i', $html) || str_contains($html, 'adminlte') || str_contains($html, 'app-wrapper')) {
            return 'adminlte';
        }

        if (str_contains($html, 'cdn.tailwindcss.com') || str_contains($html, 'tw-gallery')) {
            return 'tailwind';
        }

        return null;
    }
}
