<?php

declare(strict_types=1);

namespace Larapilot\Services\Auth;

use Illuminate\Support\Str;
use Larapilot\Services\ConfigService;

/**
 * Whether the dashboard asks for a sign-in, and who may see what once in.
 *
 * The mode follows `APP_ENV`, and nothing else:
 *   - `local` (and `testing`, the environment of a test run): open — no
 *     sign-in, whoever reaches the dashboard acts as an admin;
 *   - `production`: hidden — the routes are not registered — unless
 *     `LARAPILOT_FORCE_IN_PROD=true`; then a sign-in and a second factor
 *     are mandatory for every user;
 *   - anything else (`staging`, `preview`, `qa`, …): sign-in mandatory,
 *     second factor optional, each user chooses.
 *
 * Four roles. `admin` does everything. `dev` sees everything but the
 * users, the API token, the anonymization map, and the file manager,
 * reads no secret, and in
 * production sees the database anonymized only. `pm` sees the Workflow,
 * the Insights, and the Reference, and the logs outside production.
 * `ext` sees the Workflow alone.
 */
final class AccessPolicy
{
    public const MODE_OPEN = 'open';

    public const MODE_AUTH = 'auth';

    public const MODE_PRODUCTION = 'production';

    public const MODE_HIDDEN = 'hidden';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_DEV = 'dev';

    public const ROLE_PM = 'pm';

    public const ROLE_EXT = 'ext';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_DEV, self::ROLE_PM, self::ROLE_EXT];

    public function __construct(protected ConfigService $config) {}

    public function mode(): string
    {
        if (app()->environment('production')) {
            return $this->config->dashboardForcedInProduction() ? self::MODE_PRODUCTION : self::MODE_HIDDEN;
        }

        if (app()->environment(['local', 'testing'])) {
            return self::MODE_OPEN;
        }

        return self::MODE_AUTH;
    }

    public function isOpen(): bool
    {
        return $this->mode() === self::MODE_OPEN;
    }

    public function requiresSignIn(): bool
    {
        return in_array($this->mode(), [self::MODE_AUTH, self::MODE_PRODUCTION], true);
    }

    /**
     * A second factor for every user: production only.
     */
    public function requiresTotp(): bool
    {
        return $this->mode() === self::MODE_PRODUCTION;
    }

    public function inProduction(): bool
    {
        return app()->environment('production');
    }

    /**
     * What the mode means, in a sentence, for the pages that explain it.
     */
    public function modeLabel(): string
    {
        return match ($this->mode()) {
            self::MODE_OPEN => 'APP_ENV is '.app()->environment().': no sign-in is asked, and whoever reaches the dashboard acts as an admin.',
            self::MODE_PRODUCTION => 'APP_ENV is production and LARAPILOT_FORCE_IN_PROD is true: every user signs in with a password and an authenticator.',
            self::MODE_HIDDEN => 'APP_ENV is production and LARAPILOT_FORCE_IN_PROD is not true: the dashboard is not served.',
            default => 'APP_ENV is '.app()->environment().': every page asks for a user; the authenticator is optional.',
        };
    }

    /**
     * The areas of the dashboard, the roles that see each, and the routes
     * that make it up. An area with `production_roles` narrows down in
     * production. A route that matches no area is for admins only.
     *
     * @return array<string, array{label: string, roles: list<string>, production_roles?: list<string>, routes: list<string>}>
     */
    public static function areas(): array
    {
        $everyone = self::ROLES;
        $team = [self::ROLE_ADMIN, self::ROLE_DEV, self::ROLE_PM];
        $builders = [self::ROLE_ADMIN, self::ROLE_DEV];
        $admin = [self::ROLE_ADMIN];

        return [
            // Workflow — what the product is: for every role, the external stakeholder included.
            'board' => ['label' => 'Board', 'roles' => $everyone, 'routes' => ['larapilot.dashboard.index', 'larapilot.dashboard.board.*', 'larapilot.dashboard.spec', 'larapilot.dashboard.spec.*']],
            'prd' => ['label' => 'PRD', 'roles' => $everyone, 'routes' => ['larapilot.dashboard.prd', 'larapilot.dashboard.prd.*']],
            'inception' => ['label' => 'Inception', 'roles' => $everyone, 'routes' => ['larapilot.dashboard.inception']],
            'plan' => ['label' => 'Plan', 'roles' => $everyone, 'routes' => ['larapilot.dashboard.plan', 'larapilot.dashboard.plan.*']],
            'roadmap' => ['label' => 'Roadmap', 'roles' => $everyone, 'routes' => ['larapilot.dashboard.roadmap', 'larapilot.dashboard.roadmap.*', 'larapilot.roadmap.public']],
            'design' => ['label' => 'Design', 'roles' => $everyone, 'routes' => ['larapilot.dashboard.design', 'larapilot.dashboard.design.*', 'larapilot.mockups.*', 'larapilot.mockup-assets.*']],
            // Workspace — the project and the machine it runs on: the people who build it.
            'about' => ['label' => 'About', 'roles' => $builders, 'routes' => ['larapilot.dashboard.about']],
            'settings' => ['label' => 'Settings', 'roles' => $builders, 'routes' => ['larapilot.dashboard.settings']],
            'skills' => ['label' => 'Skills', 'roles' => $builders, 'routes' => ['larapilot.dashboard.skills', 'larapilot.dashboard.skills.*', 'larapilot.dashboard.skill', 'larapilot.dashboard.skill.*']],
            'files' => ['label' => 'File manager', 'roles' => $admin, 'routes' => ['larapilot.dashboard.files', 'larapilot.dashboard.files.*']],
            'database' => ['label' => 'Database', 'roles' => $builders, 'routes' => ['larapilot.dashboard.database', 'larapilot.dashboard.database.*']],
            'anonymization' => ['label' => 'Anonymization', 'roles' => $admin, 'routes' => ['larapilot.dashboard.anonymization', 'larapilot.dashboard.anonymization.*']],
            'logs' => ['label' => 'Logs', 'roles' => $team, 'production_roles' => $builders, 'routes' => ['larapilot.dashboard.logs', 'larapilot.dashboard.logs.*']],
            'laravel' => ['label' => 'Laravel', 'roles' => $builders, 'routes' => ['larapilot.dashboard.laravel', 'larapilot.dashboard.laravel.*']],
            'git' => ['label' => 'Git', 'roles' => $builders, 'routes' => ['larapilot.dashboard.git']],
            // Insights — how the work goes: the team, not the outside.
            'forecast' => ['label' => 'Forecast', 'roles' => $team, 'routes' => ['larapilot.dashboard.forecast', 'larapilot.dashboard.forecast.*']],
            'usage' => ['label' => 'Usage', 'roles' => $team, 'routes' => ['larapilot.dashboard.usage', 'larapilot.dashboard.usage.*']],
            'economics' => ['label' => 'Economics', 'roles' => $team, 'routes' => ['larapilot.dashboard.economics', 'larapilot.dashboard.economics.*']],
            'security' => ['label' => 'Security', 'roles' => $team, 'routes' => ['larapilot.dashboard.security', 'larapilot.dashboard.security.*']],
            'sbom' => ['label' => 'SBOM', 'roles' => $team, 'routes' => ['larapilot.dashboard.sbom', 'larapilot.dashboard.sbom.*']],
            'errors' => ['label' => 'Errors', 'roles' => $team, 'routes' => ['larapilot.dashboard.errors', 'larapilot.dashboard.errors.*']],
            // Reference.
            'api' => ['label' => 'API', 'roles' => $team, 'routes' => ['larapilot.api.*']],
            'docs' => ['label' => 'Docs', 'roles' => $team, 'routes' => ['larapilot.dashboard.docs']],
            // Access — the users and the token: admins. The account is one's own.
            'account' => ['label' => 'Account', 'roles' => $everyone, 'routes' => ['larapilot.dashboard.account', 'larapilot.dashboard.account.*']],
            'users' => ['label' => 'Users', 'roles' => $admin, 'routes' => ['larapilot.dashboard.users', 'larapilot.dashboard.users.*']],
            'api-token' => ['label' => 'API token', 'roles' => $admin, 'routes' => ['larapilot.dashboard.api-token', 'larapilot.dashboard.api-token.*']],
        ];
    }

    public function allows(string $role, string $area): bool
    {
        $areas = self::areas();

        if (! isset($areas[$area])) {
            return $role === self::ROLE_ADMIN;
        }

        $roles = $this->inProduction() && isset($areas[$area]['production_roles'])
            ? $areas[$area]['production_roles']
            : $areas[$area]['roles'];

        return in_array($role, $roles, true);
    }

    /**
     * The area a named route belongs to; null for a route no area claims.
     */
    public function areaOfRoute(?string $route): ?string
    {
        if ($route === null || $route === '') {
            return null;
        }

        foreach (self::areas() as $key => $area) {
            foreach ($area['routes'] as $pattern) {
                if (Str::is($pattern, $route)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * The areas a role sees, in the current mode.
     *
     * @return list<string>
     */
    public function areasFor(string $role): array
    {
        return array_values(array_filter(array_keys(self::areas()), fn (string $area): bool => $this->allows($role, $area)));
    }

    public static function isRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_DEV => 'Developer',
            self::ROLE_PM => 'Product manager',
            self::ROLE_EXT => 'External',
            default => ucfirst($role),
        };
    }

    public static function roleDescription(string $role): string
    {
        return match ($role) {
            self::ROLE_ADMIN => 'Everything: every page, the users, the API token, the anonymization map, passwords and secrets where the environment allows them.',
            self::ROLE_DEV => 'Every page but the users, the API token, the anonymization map, and the file manager; no secret of the environment; in production, the database anonymized only.',
            self::ROLE_PM => 'The Workflow, the Insights, and the Reference — not the Workspace; the logs outside production.',
            self::ROLE_EXT => 'The Workflow alone: the board, the PRD, the inception, the plan, the roadmap, the design and its mockups.',
            default => '',
        };
    }
}
