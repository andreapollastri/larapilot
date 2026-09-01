<?php

declare(strict_types=1);

namespace Larapilot\Services;

class AzureDevopsService
{
    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
    ) {}

    /**
     * Probe Azure DevOps (Azure Repos) readiness for the optional `settings.azure` integration.
     *
     * Auth is via the `az` CLI (`az login` + the `azure-devops` extension) or a
     * personal access token (AZURE_DEVOPS_EXT_PAT / AZURE_DEVOPS_PAT).
     *
     * @return array{
     *     enabled: bool,
     *     az_installed: bool,
     *     devops_extension: bool,
     *     authenticated: bool,
     *     auth_method: string|null,
     *     repo: string|null,
     *     origin: string|null,
     *     is_azure_remote: bool,
     *     ready: bool,
     *     hints: list<string>
     * }
     */
    public function status(): array
    {
        $enabled = $this->config->azureEnabled();
        $azInstalled = $this->azInstalled();
        $devopsExtension = $azInstalled && $this->devopsExtensionInstalled();
        $authMethod = $this->authMethod($azInstalled, $devopsExtension);
        $authenticated = $authMethod !== null;
        $origin = $this->git->originUrl();
        $repo = $this->git->originRepoSlug();
        $isAzure = $this->git->originProvider() === 'azure';

        $hints = [];

        if (! $enabled) {
            $hints[] = 'Enable with: php artisan larapilot:settings-set --azure=YES';
        }

        if (! $azInstalled) {
            $hints[] = 'Install the Azure CLI: https://learn.microsoft.com/cli/azure/install-azure-cli then run `az login` (or set AZURE_DEVOPS_EXT_PAT).';
        } elseif (! $devopsExtension) {
            $hints[] = 'Add the Azure DevOps extension: `az extension add --name azure-devops`.';
        }

        if (! $authenticated) {
            $hints[] = 'Authenticate: `az login` (with the azure-devops extension) or set AZURE_DEVOPS_EXT_PAT / AZURE_DEVOPS_PAT.';
        }

        if ($origin === null) {
            $hints[] = 'Add a git remote named origin pointing at an Azure DevOps repository (dev.azure.com or *.visualstudio.com).';
        } elseif (! $isAzure) {
            $hints[] = 'origin is not an Azure DevOps remote; Larapilot Azure integration targets dev.azure.com / visualstudio.com hosts.';
        }

        $ready = $enabled && $authenticated && $isAzure;

        return [
            'enabled' => $enabled,
            'az_installed' => $azInstalled,
            'devops_extension' => $devopsExtension,
            'authenticated' => $authenticated,
            'auth_method' => $authMethod,
            'repo' => $repo,
            'origin' => $origin,
            'is_azure_remote' => $isAzure,
            'ready' => $ready,
            'hints' => $hints,
        ];
    }

    protected function authMethod(bool $azInstalled, bool $devopsExtension): ?string
    {
        if ($this->pat() !== '') {
            return 'pat';
        }

        if ($azInstalled && $devopsExtension && $this->azAuthenticated()) {
            return 'az_cli';
        }

        return null;
    }

    protected function pat(): string
    {
        $configured = trim((string) config('larapilot.integrations.azure_devops_pat', ''));

        if ($configured !== '') {
            return $configured;
        }

        foreach (['AZURE_DEVOPS_EXT_PAT', 'AZURE_DEVOPS_PAT'] as $var) {
            $value = getenv($var);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    protected function azInstalled(): bool
    {
        $output = shell_exec('command -v az 2>/dev/null');

        return is_string($output) && trim($output) !== '';
    }

    protected function devopsExtensionInstalled(): bool
    {
        $output = shell_exec('az extension show --name azure-devops 2>/dev/null');

        return is_string($output) && trim($output) !== '';
    }

    protected function azAuthenticated(): bool
    {
        $output = shell_exec('az account show 2>/dev/null');

        return is_string($output) && trim($output) !== '';
    }
}
