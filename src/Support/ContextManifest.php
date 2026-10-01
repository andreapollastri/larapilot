<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * What each packaged skill reads at activation.
 *
 * A pack is one runtime file, named after its source without the `runtime-`
 * prefix (`runtime-delivery-1.md` → `delivery-1`). An entry is a pack, or
 * `pack?condition` when the pack is read only while the condition holds
 * against the facts of the project (settings, and what is on disk).
 *
 * `read` is loaded before the skill starts. `on_demand` is named to the agent
 * with the moment it applies, and read only then. `deep` names the on-demand
 * packs that `effort: MAX` reads up front.
 */
final class ContextManifest
{
    /**
     * Read by every skill, packaged or custom.
     *
     * @var list<string>
     */
    public const CORE = ['core-cli', 'core-settings', 'core-settings-2', 'core-economy'];

    /**
     * Packs that also exist with every value of every setting (`{pack}.full`),
     * for the one skill that changes them.
     *
     * @var list<string>
     */
    public const FULL = ['core-settings', 'core-settings-2'];

    /**
     * Facts a condition may name besides the settings.
     *
     * @var array<string, list<string>>
     */
    public const FACTS = [
        // `full` is the pack with every value of every setting, `project`
        // the one compiled for the settings the project has.
        'view' => ['full', 'project'],
        'forge' => ['YES', 'NO'],
        'frontend' => ['external', 'none'],
        'dev_docs' => ['documented', 'undocumented'],
        'client_materials' => ['populated', 'empty'],
        'legacy' => ['populated', 'empty'],
        'research' => ['populated', 'empty'],
        'prd' => ['present', 'absent'],
    ];

    /**
     * Facts a `<!-- when: … -->` marker inside a pack may name. They change
     * only when a setting does, so a compiled pack stays the same for the
     * whole of a session.
     *
     * @var list<string>
     */
    public const MARKER_FACTS = ['view', 'forge', 'frontend'];

    /**
     * @return array<string, array{
     *     category: string,
     *     read?: list<string>,
     *     on_demand?: array<string, string>,
     *     deep?: list<string>,
     *     include?: array<string, string>,
     *     paths?: list<string>,
     *     slices?: list<string>
     * }>
     */
    public static function skills(): array
    {
        return [
            'inception' => [
                'category' => 'analysis',
                'read' => [
                    'core-language',
                    'core-personas',
                    'discovery-1',
                    'discovery-2',
                    'discovery-4',
                    'discovery-8',
                    'discovery-3?client_materials=populated or legacy=populated',
                    'release?release_mode=YES',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'discovery-3' => 'The user brings client documents, a legacy system, or reference products.',
                    'discovery-6' => 'From step 7: Prior Art, then journeys and the domain model.',
                    'discovery-7' => 'From step 10: the shape of an FR, NFRs, risks, Definition of Ready and readback.',
                    'discovery-5' => 'Step 12, when the product has a UI: Frontend Topology.',
                    'delivery-3' => 'Step 12: tenancy, data store, admin panel, local dev stack.',
                    'delivery-4' => 'Step 12: CLI tooling, CI/CD, optional integrations.',
                    'ship-1' => 'Step 12: deploy platform, edge, cloud, observability.',
                    'ux-2' => 'Step 13, public-facing surfaces: brand assets, mobile and device scope.',
                    'ux-3' => 'Step 13, public-facing surfaces: SEO structure, copy, marketing.',
                    'ship-2' => 'Step 14, when the product handles personal data: Privacy & Legal Compliance.',
                    'release?release_mode=NO' => 'Step 2, when the user turns release mode on.',
                    'ops-3' => 'Step 0, when a PRD already exists and the user chooses a pivot: Identifier stability.',
                ],
                'paths' => ['prd', 'client_materials', 'legacy', 'research', 'choices', 'schedule'],
                'slices' => ['frontend'],
            ],

            'adopt' => [
                'category' => 'analysis',
                'read' => [
                    'core-language',
                    'core-personas',
                    'core-subagents?effort!=ECO',
                    'discovery-2',
                    'discovery-4',
                    'discovery-8',
                    'dev-docs',
                    'dev-docs-catchup',
                    'release?release_mode=YES',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'discovery-6' => 'Step 5, before the PRD: journeys and the domain model.',
                    'discovery-7' => 'Step 5, before the PRD: the shape of an FR, NFRs, risks.',
                    'discovery-5' => 'The repository has no coupled UI, or names an external frontend.',
                    'discovery-3' => 'Client documents exist, or the user asks for competitor context.',
                    'delivery-3' => 'The schema is non-trivial: trees, NoSQL, search.',
                    'ship-1' => 'The repository records no deploy platform, edge, or cloud.',
                    'release?release_mode=NO' => 'The user turns release mode on.',
                ],
                'paths' => ['prd', 'research', 'client_materials', 'legacy', 'dev_docs', 'choices', 'schedule'],
                'slices' => ['dev_docs', 'frontend'],
            ],

            'feature' => [
                'category' => 'feature',
                'read' => [
                    'core-language',
                    'core-personas',
                    'ops-1',
                    'discovery-4',
                    'discovery-7',
                    'release?release_mode=YES',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'discovery-6' => 'The feature is a whole subsystem (prior-art search), or adds a journey or an entity.',
                    'discovery-3' => 'The feature touches legacy scope or cites client documents.',
                    'delivery-3' => 'Package or built-in: Vendor & Package Policy.',
                ],
                'paths' => ['prd', 'client_materials', 'legacy', 'research'],
            ],

            'bug' => [
                'category' => 'support',
                'read' => [
                    'core-language',
                    'ops-1',
                    'ops-2',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'discovery-7' => 'Round 4, on a requirement gap: Requirement Quality and NFRs.',
                    'delivery-2' => 'A Critical production hotfix: the Gitflow branch rules.',
                ],
                'paths' => ['prd', 'support', 'research'],
            ],

            'triage' => [
                'category' => 'support',
                'paths' => ['prd'],
            ],

            'aikido' => [
                'category' => 'support',
                'paths' => ['security'],
            ],

            'error' => [
                'category' => 'support',
                'paths' => ['support'],
            ],

            'vendor-check' => [
                'category' => 'support',
                'on_demand' => [
                    'frontend?frontend=external' => 'A vulnerable package is in the frontend companion.',
                ],
                'paths' => ['security'],
            ],

            'laravel-upgrade' => [
                'category' => 'implementation',
                'read' => ['upgrade-1', 'upgrade-2'],
                'on_demand' => [
                    'upgrade-3' => 'The target Laravel needs a newer PHP: the PHP step runs first.',
                    'dev-docs' => 'The upgrade changes an architectural choice the domain docs record.',
                    'delivery-4' => 'A step touches CI/CD, Docker, or deploy scripts beyond a version pin.',
                    'frontend?frontend=external' => 'The upgrade changes what the API answers to the frontend companion.',
                ],
                'paths' => ['upgrades', 'dev_docs'],
            ],

            'php-upgrade' => [
                'category' => 'implementation',
                'read' => ['upgrade-1', 'upgrade-3'],
                'on_demand' => [
                    'upgrade-2' => 'A package must move a major, or the target PHP needs a newer Laravel.',
                    'delivery-4' => 'A step touches CI/CD, Docker, or deploy scripts beyond a version pin.',
                ],
                'paths' => ['upgrades'],
            ],

            'db-upgrade' => [
                'category' => 'implementation',
                'read' => ['upgrade-1', 'upgrade-3'],
                'on_demand' => [
                    'dev-docs' => 'Close: the developer docs record the new engine and what it changes.',
                    'delivery-3' => 'The schema uses trees, full-text search, tenancy, or a NoSQL store.',
                    'ship-1' => 'The deploy platform or the managed database changes with the engine.',
                ],
                'paths' => ['upgrades', 'dev_docs'],
            ],

            'prd' => [
                'category' => 'analysis',
                'read' => [
                    'core-language',
                    'ops-1',
                    'ops-3',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'discovery-7' => 'Sharpen, Upgrade, Re-model: FR shape, NFRs, risks, Definition of Ready.',
                    'discovery-6' => 'Re-model; any kind that touches journeys or the domain model; Re-decide on prior art.',
                    'discovery-4' => 'Re-scope: MoSCoW, Delivery Target. Re-decide on budget sensitivity.',
                    'discovery-8' => 'Re-decide on the business model or on operations and support.',
                    'discovery-2' => 'Re-decide on Project Kind or a core round.',
                    'discovery-5' => 'Re-decide on Frontend Topology.',
                ],
                'paths' => ['prd'],
            ],

            'spec' => [
                'category' => 'planning',
                'read' => [
                    'core-language',
                    'core-personas',
                    'discovery-4',
                    'discovery-7',
                    'discovery-3?client_materials=populated or legacy=populated or research=populated',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'discovery-6' => 'The PRD has no journeys or no domain model, or the Prior Art verdict is not Build anyway.',
                    'delivery-3' => 'The PRD leaves the admin panel or the local dev stack open, and a spec depends on it.',
                    'ship-1' => 'The PRD leaves the deploy platform, the edge, or the cloud open, and a spec depends on it.',
                ],
                'paths' => ['prd', 'client_materials', 'legacy', 'research', 'support'],
            ],

            'plan' => [
                'category' => 'planning',
                'read' => [
                    'core-language',
                    'core-subagents?effort!=ECO',
                    'delivery-1',
                    'delivery-2',
                    'dev-docs',
                    'task-templates',
                    'release?release_mode=YES',
                    'frontend?frontend=external',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'delivery-3' => 'The spec adds a package, an admin panel, a tenant boundary, or a data model with trees, search, or NoSQL.',
                    'delivery-4' => 'The spec touches CI/CD, a CLI tool, shell or deploy scripts, versioning, security.txt, or a third-party service choice.',
                    'delivery-5' => 'The spec integrates a third-party API or webhook, or is multi-locale.',
                    'ux-1' => 'The spec has UI.',
                    'ux-2' => 'The spec has UI: design-system scaffold, brand assets, mobile or device features.',
                    'ux-3' => 'The spec adds or changes public pages: SEO structure, copy, marketing.',
                    'ship-1' => 'The spec needs deploy, edge, or cloud tasks and the PRD leaves the choice open.',
                    'ship-2' => 'The spec processes personal data: Privacy & Legal Compliance.',
                    'discovery-3' => 'The spec is a legacy rewrite or port, or cites client documents or reference products.',
                    'frontend-angular?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Angular.',
                    'frontend-react?frontend=external' => 'frontend-scan lists it under playbooks: a target project is React, Next.js, React Router, or React Native.',
                    'frontend-vue?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Vue or Nuxt.',
                    'frontend-svelte?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Svelte or SvelteKit.',
                    'spec-worker' => 'The handoff says you are the autopilot spec worker.',
                ],
                'deep' => ['delivery-3', 'delivery-4', 'delivery-5'],
                'paths' => ['prd', 'planning', 'mockups', 'client_materials', 'legacy', 'research', 'dev_docs'],
                'slices' => ['dev_docs', 'frontend'],
            ],

            'implement' => [
                'category' => 'implementation',
                'read' => [
                    'core-subagents',
                    'delivery-1',
                    'delivery-2',
                    'dev-docs',
                    'dev-docs-catchup?dev_docs=undocumented',
                    'release?release_mode=YES',
                    'project-docs?project_docs=YES',
                    'frontend?frontend=external',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'delivery-3' => 'The task adds a package, a tenant boundary, or a schema with trees or search.',
                    'delivery-4' => 'The task touches CI/CD, a CLI tool, shell or deploy scripts, security.txt, or the changelog.',
                    'delivery-5' => 'The task integrates a third-party API or webhook, or adds locales.',
                    'ux-1' => 'The task builds UI.',
                    'ux-2' => 'The task builds UI: design system, brand assets, mobile or device features.',
                    'task-templates' => 'A task body has no Git Deliverables, Test Data, or Domain Docs section.',
                    'dev-docs-catchup?dev_docs=documented' => 'Domain docs exist but domains are plainly missing.',
                    'frontend-angular?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Angular.',
                    'frontend-react?frontend=external' => 'frontend-scan lists it under playbooks: a target project is React, Next.js, React Router, or React Native.',
                    'frontend-vue?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Vue or Nuxt.',
                    'frontend-svelte?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Svelte or SvelteKit.',
                    'spec-worker' => 'The handoff says you are the autopilot spec worker.',
                ],
                'paths' => ['prd', 'planning', 'mockups', 'review', 'dev_docs', 'client_materials', 'legacy', 'research'],
                'slices' => ['dev_docs', 'frontend'],
            ],

            'review' => [
                'category' => 'review',
                'read' => [
                    'delivery-2',
                    'dev-docs',
                    'project-docs?project_docs=YES',
                    'frontend?frontend=external',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'frontend-angular?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Angular.',
                    'frontend-react?frontend=external' => 'frontend-scan lists it under playbooks: a target project is React, Next.js, React Router, or React Native.',
                    'frontend-vue?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Vue or Nuxt.',
                    'frontend-svelte?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Svelte or SvelteKit.',
                ],
                'paths' => ['prd', 'review', 'research', 'dev_docs'],
                'slices' => ['dev_docs', 'frontend'],
            ],

            'autopilot' => [
                'category' => 'implementation',
                'read' => [
                    'core-subagents?effort!=ECO',
                    'spec-worker?effort!=ECO',
                    'hooks?hooks=YES',
                ],
                'include' => [
                    'plan' => 'effort=ECO',
                    'implement' => 'effort=ECO',
                ],
                'paths' => ['choices', 'planning', 'review', 'dev_docs'],
                'slices' => ['dev_docs'],
            ],

            'design' => [
                'category' => 'planning',
                'read' => [
                    'core-language',
                    'ux-1',
                    'ux-2',
                ],
                'on_demand' => [
                    'ux-3' => 'The mockups are public pages: SEO structure, copy, marketing.',
                ],
                'paths' => ['prd', 'mockups', 'client_materials', 'research', 'design_systems'],
                'slices' => ['frontend'],
            ],

            'ship' => [
                'category' => 'ship',
                'read' => [
                    'ship-2',
                    'ship-3',
                    'ops-2',
                    'dev-docs',
                    'release?release_mode=YES',
                    'project-docs?project_docs=YES',
                    'hooks?hooks=YES',
                ],
                'on_demand' => [
                    'ship-1' => 'The PRD does not record the deploy platform, the edge, the cloud, or the observability stack.',
                    'ux-3' => 'Phase 5, public sites: SEO structure behind the launch checks.',
                ],
                'paths' => ['prd', 'security', 'launch', 'support', 'dev_docs', 'releases'],
                'slices' => ['dev_docs'],
            ],

            'settings' => [
                'category' => 'other',
                'read' => [
                    'economics-1?account!=NONE',
                ],
                'on_demand' => [
                    'core-settings.full' => 'The user asks what a value of effort, backlog, git mode, testing, account, auto-approve, Lucille, the journal, prior art, or code history changes.',
                    'core-settings-2.full' => 'The user asks what an opt-in toggle changes: release mode, project docs, comments, auth, security scan, Aikido, errors, forges, notifications.',
                    'economics-1?account=NONE' => 'The user turns `account` on.',
                    'hooks' => 'The user asks about workflow hooks: what they run, how to write one, or turning `hooks` on.',
                ],
                'paths' => ['choices'],
            ],

            'usage' => [
                'category' => 'other',
                'read' => ['ops-4'],
                'paths' => ['usage', 'schedule'],
            ],

            'schedule' => [
                'category' => 'planning',
                'on_demand' => [
                    'ops-4' => 'A deadline, a milestone, or the ledger behind the forecast is in question: Usage Ledger & Schedule.',
                ],
                'paths' => ['schedule', 'releases'],
            ],

            'tracker' => [
                'category' => 'other',
                'read' => ['ops-6'],
            ],

            'backstage' => [
                'category' => 'other',
                'read' => ['ops-5'],
                'paths' => ['prd'],
            ],

            'release' => [
                'category' => 'other',
                'read' => ['release', 'hooks?hooks=YES'],
                'on_demand' => [
                    'ship-2' => 'The release is shipped in this session: deploy runbooks and the security gate.',
                ],
                'paths' => ['releases', 'prd'],
            ],

            'project-docs' => [
                'category' => 'other',
                'read' => ['project-docs'],
                'paths' => ['project_docs', 'prd', 'planning'],
            ],

            'custom-skill' => [
                'category' => 'other',
                'read' => ['core-personas', 'custom-skills'],
                'on_demand' => [
                    'hooks' => 'The skill is meant to run as a workflow hook of an event.',
                ],
                'paths' => ['custom_skills'],
            ],

            'economics' => [
                'category' => 'other',
                'read' => ['economics-1', 'economics-2'],
                'paths' => ['prd', 'economics', 'economics_snapshot', 'economics_quote', 'economics_market', 'choices'],
            ],

            'frontend-companion' => [
                'category' => 'other',
                'read' => ['discovery-5', 'frontend?frontend=external'],
                'on_demand' => [
                    'frontend?frontend=none' => 'Once frontend-set has linked the repository.',
                    'frontend-angular?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Angular.',
                    'frontend-react?frontend=external' => 'frontend-scan lists it under playbooks: a target project is React, Next.js, React Router, or React Native.',
                    'frontend-vue?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Vue or Nuxt.',
                    'frontend-svelte?frontend=external' => 'frontend-scan lists it under playbooks: a target project is Svelte or SvelteKit.',
                ],
                'paths' => ['prd', 'mockups'],
                'slices' => ['frontend'],
            ],
        ];
    }

    /**
     * The skill name as the manifest keys it: lower case, without the
     * `larapilot-` prefix or a leading slash.
     */
    public static function key(string $skill): string
    {
        $key = strtolower(trim($skill));
        $key = ltrim($key, '/');

        return str_starts_with($key, 'larapilot-') ? substr($key, strlen('larapilot-')) : $key;
    }

    /**
     * @return array{0: string, 1: string|null} pack, condition
     */
    public static function entry(string $entry): array
    {
        $parts = explode('?', $entry, 2);

        return [trim($parts[0]), isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null];
    }
}
