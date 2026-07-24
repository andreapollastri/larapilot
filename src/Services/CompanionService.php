<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\Markdown;

class CompanionService
{
    public function __construct(
        protected ConfigService $config,
        protected PrdService $prd,
    ) {}

    /**
     * Bundle shared Larapilot artifacts for an external frontend repository.
     *
     * @return array<string, mixed>
     */
    public function bundle(?string $apiBaseUrl = null): array
    {
        $content = $this->prd->read();
        $topology = $this->extractFrontendTopology($content);
        $productOpenApi = $this->productOpenApiSnapshot();

        $endpoints = [
            'prd' => null,
            'companion' => null,
            'larapilot_openapi' => null,
        ];

        if (is_string($apiBaseUrl) && $apiBaseUrl !== '') {
            $base = rtrim($apiBaseUrl, '/');
            $endpoints = [
                'prd' => $base.'/prd',
                'companion' => $base.'/companion',
                'larapilot_openapi' => $base.'/openapi.json',
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'source' => 'larapilot',
            'skill' => 'larapilot-frontend-companion',
            'artifacts' => [
                'prd' => $content === null ? null : [
                    'content' => $content,
                    'headings' => Markdown::headings($content),
                    'path' => $this->prd->path(),
                ],
                'frontend_topology' => $topology,
                'product_openapi' => $productOpenApi,
            ],
            'endpoints' => $endpoints,
            'instructions' => [
                'Mirror `artifacts.prd.content` to `.larapilot/docs/PRD.md` in the frontend repo.',
                'When present, write `artifacts.product_openapi.content` to `.larapilot/openapi-product.json`.',
                'Record sync metadata in `.larapilot/companion-sync.md`.',
                'Do not invent API endpoints — extend the Laravel OpenAPI contract first.',
                'Re-run `/larapilot-frontend-companion` after PRD living-document edits on Laravel.',
            ],
        ];
    }

    /**
     * @return array{
     *     mode: string|null,
     *     in_repo_stack: string|null,
     *     external_repo: string|null,
     *     external_stack: string|null,
     *     sync_mode: string|null,
     *     raw: array<string, string>
     * }|null
     */
    public function extractFrontendTopology(?string $prd): ?array
    {
        if ($prd === null || trim($prd) === '') {
            return null;
        }

        $raw = [];

        foreach ([
            'Frontend Topology' => 'mode',
            'Frontend stack (in-repo)' => 'in_repo_stack',
            'External frontend repo' => 'external_repo',
            'External frontend stack' => 'external_stack',
            'Companion sync' => 'sync_mode',
        ] as $label => $key) {
            $value = $this->matchLabeledField($prd, $label);

            if ($value !== null) {
                $raw[$label] = $value;
            }
        }

        if ($raw === []) {
            return null;
        }

        $modeRaw = $raw['Frontend Topology'] ?? null;

        return [
            'mode' => $this->normalizeTopologyMode($modeRaw),
            'in_repo_stack' => $raw['Frontend stack (in-repo)'] ?? null,
            'external_repo' => $raw['External frontend repo'] ?? null,
            'external_stack' => $raw['External frontend stack'] ?? null,
            'sync_mode' => $raw['Companion sync'] ?? null,
            'raw' => $raw,
        ];
    }

    protected function matchLabeledField(string $prd, string $label): ?string
    {
        $quoted = preg_quote($label, '/');
        $patterns = [
            '/\*\*'.$quoted.':\*\*\s*(.+)$/mi',
            '/^[-*]\s*'.$quoted.':\s*(.+)$/mi',
            '/^'.$quoted.':\s*(.+)$/mi',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prd, $matches) === 1) {
                $value = trim($matches[1]);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function normalizeTopologyMode(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (str_contains($normalized, 'external') || str_contains($normalized, 'api +') || str_contains($normalized, 'api-only')) {
            return 'api_external_frontend';
        }

        if (str_contains($normalized, 'spa')) {
            return 'spa_in_laravel';
        }

        if (str_contains($normalized, 'coupled') || str_contains($normalized, 'blade') || str_contains($normalized, 'livewire')) {
            return 'laravel_coupled';
        }

        return $value;
    }

    /**
     * @return array{path: string, content: string}|null
     */
    protected function productOpenApiSnapshot(): ?array
    {
        $candidates = [
            base_path('storage/api-docs/api-docs.json'),
            base_path('openapi.json'),
            base_path('docs/openapi.json'),
            base_path('.larapilot/openapi-product.json'),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path)) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false || trim($content) === '') {
                continue;
            }

            return [
                'path' => $path,
                'content' => $content,
            ];
        }

        return null;
    }
}
