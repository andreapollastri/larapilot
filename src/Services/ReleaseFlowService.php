<?php

declare(strict_types=1);

namespace Larapilot\Services;

/**
 * Gitflow mechanics for release branches. Ledger updates stay in ReleaseService;
 * this service creates the branch, the feature branch, the develop sync, and the ship.
 */
class ReleaseFlowService
{
    public function __construct(
        protected ConfigService $config,
        protected ReleaseService $releases,
        protected GitService $git,
    ) {}

    public function gitflowActive(): bool
    {
        $mode = strtoupper((string) ($this->config->settings()['git_mode'] ?? ''));

        return in_array($mode, ['GITFLOW', 'GITFLOW_PUSH'], true);
    }

    /**
     * Where work should land, so a skill does not ask when the answer is already known.
     *
     * @return array{
     *     gitflow: bool,
     *     repository: bool,
     *     current_branch: string|null,
     *     clean: bool|null,
     *     active_release: string|null,
     *     needs_choice: bool,
     *     in_progress: list<string>
     * }
     */
    public function context(): array
    {
        $repository = $this->git->isRepository();
        $current = $repository ? $this->git->currentBranch() : null;
        $inProgress = [];
        $active = null;

        foreach ($this->releases->list('in_progress') as $release) {
            $version = (string) ($release['version'] ?? '');

            if ($version === '') {
                continue;
            }

            $inProgress[] = $version;

            if ($current !== null && $active === null && $this->releaseBranchName($release) === $current) {
                $active = $version;
            }
        }

        if ($active === null && count($inProgress) === 1) {
            $active = $inProgress[0];
        }

        return [
            'gitflow' => $this->gitflowActive(),
            'repository' => $repository,
            'current_branch' => $current,
            'clean' => $repository ? $this->git->workingTreeClean() : null,
            'active_release' => $active,
            'needs_choice' => $active === null && count($inProgress) > 1,
            'in_progress' => $inProgress,
        ];
    }

    /**
     * Open `release/x.y.z` from `develop` and move the ledger to `in_progress`.
     *
     * @return array<string, mixed>
     */
    public function cut(string $version, bool $checkout = true, bool $push = false): array
    {
        $release = $this->requireOpen($version);

        if (! $this->gitflowActive()) {
            if (($release['status'] ?? '') === 'planned') {
                $release = $this->releases->set((string) $release['version'], ['status' => 'in_progress']);
            }

            return [
                'ok' => true,
                'skipped' => true,
                'reason' => 'git_mode is NO_GITFLOW — ledger only, no release branch.',
                'release' => $release,
            ];
        }

        if (! $this->git->isRepository()) {
            return $this->gitFailure('Not a git repository.', $release);
        }

        $develop = $this->ensureDevelop();

        if (! $develop['ok']) {
            return $this->gitFailure((string) $develop['error'], $release);
        }

        $branch = $this->releaseBranchName($release);
        $created = ! $this->git->localBranchExists($branch);

        if ($created) {
            $cut = $this->git->run('branch', $branch, 'develop');

            if (! $cut['ok']) {
                return $this->gitFailure($cut['output'] !== '' ? $cut['output'] : "Could not create {$branch}.", $release);
            }
        }

        if (($release['status'] ?? '') === 'planned') {
            $release = $this->releases->set((string) $release['version'], ['status' => 'in_progress']);
        }

        $switch = $checkout
            ? $this->checkout($branch)
            : ['checked_out' => $this->git->currentBranch() === $branch, 'reason' => null];

        $result = [
            'ok' => true,
            'skipped' => false,
            'branch' => $branch,
            'created' => $created,
            'base' => 'develop',
            'develop_created' => (bool) ($develop['created'] ?? false),
            'checked_out' => (bool) $switch['checked_out'],
            'reason' => $switch['reason'],
            'release' => $release,
        ];

        return $this->withPush($result, $push, [$branch]);
    }

    /**
     * Create `feature/US-XXX-*` from the release branch. Version is inferred when only one release is in progress.
     *
     * @return array<string, mixed>
     */
    public function feature(?string $version, string $spec, ?string $slug = null, bool $checkout = true, bool $push = false): array
    {
        $version = $this->resolveVersion($version);
        $featureBranch = $this->featureBranchName($spec, $slug);
        $cut = $this->cut($version, false, false);

        if (($cut['ok'] ?? false) !== true) {
            return $cut;
        }

        if (($cut['skipped'] ?? false) === true) {
            return $cut;
        }

        $releaseBranch = (string) $cut['branch'];
        $created = ! $this->git->localBranchExists($featureBranch);

        if ($created) {
            $branch = $this->git->run('branch', $featureBranch, $releaseBranch);

            if (! $branch['ok']) {
                return $this->gitFailure(
                    $branch['output'] !== '' ? $branch['output'] : "Could not create {$featureBranch}.",
                    is_array($cut['release'] ?? null) ? $cut['release'] : null
                );
            }
        }

        $switch = $checkout
            ? $this->checkout($featureBranch)
            : ['checked_out' => $this->git->currentBranch() === $featureBranch, 'reason' => null];

        $result = [
            'ok' => true,
            'skipped' => false,
            'branch' => $featureBranch,
            'created' => $created,
            'base' => $releaseBranch,
            'checked_out' => (bool) $switch['checked_out'],
            'reason' => $switch['reason'],
            'release' => $cut['release'] ?? null,
        ];

        return $this->withPush($result, $push, [$releaseBranch, $featureBranch]);
    }

    /**
     * Merge `develop` into the release branch when integration has moved ahead.
     *
     * @return array<string, mixed>
     */
    public function sync(?string $version): array
    {
        $version = $this->resolveVersion($version);
        $release = $this->requireOpen($version);

        if (! $this->gitflowActive()) {
            return [
                'ok' => true,
                'skipped' => true,
                'reason' => 'git_mode is NO_GITFLOW — nothing to sync.',
                'release' => $release,
            ];
        }

        $cut = $this->cut($version, false, false);

        if (($cut['ok'] ?? false) !== true) {
            return $cut;
        }

        if (! $this->git->workingTreeClean()) {
            return $this->gitFailure('Working tree is dirty. Commit or stash before syncing the release branch.', $release);
        }

        $branch = (string) $cut['branch'];
        $merged = $this->mergeInto($branch, 'develop');

        if (! $merged['ok']) {
            return $this->gitFailure((string) $merged['error'], $release);
        }

        return [
            'ok' => true,
            'skipped' => false,
            'branch' => $branch,
            'merged' => 'develop',
            'checked_out' => true,
            'release' => $release,
        ];
    }

    /**
     * Merge the release into `main`, tag `vX.Y.Z`, back-merge into `develop`, mark shipped.
     *
     * @return array<string, mixed>
     */
    public function ship(string $version, bool $push = false): array
    {
        $release = $this->requireOpen($version);
        $normalized = (string) $release['version'];

        if (! $this->gitflowActive()) {
            return $this->shipWithoutGitflow($release, $normalized);
        }

        if (! $this->git->isRepository()) {
            return $this->gitFailure('Not a git repository.', $release);
        }

        if (! $this->git->workingTreeClean()) {
            return $this->gitFailure('Working tree is dirty. Commit or stash before shipping.', $release);
        }

        $develop = $this->ensureDevelop();

        if (! $develop['ok']) {
            return $this->gitFailure((string) $develop['error'], $release);
        }

        if (! $this->git->localBranchExists('main')) {
            return $this->gitFailure('Branch main does not exist.', $release);
        }

        $branch = $this->releaseBranchName($release);

        if (! $this->git->localBranchExists($branch)) {
            return $this->gitFailure("Release branch {$branch} does not exist. Run release-cut first.", $release);
        }

        $intoMain = $this->mergeInto('main', $branch);

        if (! $intoMain['ok']) {
            return $this->gitFailure((string) $intoMain['error'], $release);
        }

        $tag = $this->ensureTag($normalized);

        if (! $tag['ok']) {
            return $this->gitFailure((string) $tag['error'], $release, ['merged_main' => true]);
        }

        $intoDevelop = $this->mergeInto('develop', $branch);

        if (! $intoDevelop['ok']) {
            return $this->gitFailure(
                (string) $intoDevelop['error'].' main already contains this release; resolve develop and run release-ship again.',
                $release,
                ['merged_main' => true, 'tag' => $tag['tag']]
            );
        }

        $shipped = $this->releases->set($normalized, ['status' => 'shipped']);
        $result = [
            'ok' => true,
            'skipped' => false,
            'branch' => $branch,
            'tag' => $tag['tag'],
            'tag_created' => (bool) $tag['created'],
            'merged' => ['main', 'develop'],
            'release' => $shipped,
        ];

        return $this->withPush($result, $push, ['main', 'develop', (string) $tag['tag']]);
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>
     */
    protected function shipWithoutGitflow(array $release, string $version): array
    {
        if (! $this->git->isRepository()) {
            return [
                'ok' => true,
                'skipped' => true,
                'reason' => 'git_mode is NO_GITFLOW and this is not a git repository — ledger only.',
                'release' => $this->releases->set($version, ['status' => 'shipped']),
            ];
        }

        if (! $this->git->workingTreeClean()) {
            return $this->gitFailure('Working tree is dirty. Commit or stash before tagging.', $release);
        }

        $tag = $this->ensureTag($version);

        if (! $tag['ok']) {
            return $this->gitFailure((string) $tag['error'], $release);
        }

        return [
            'ok' => true,
            'skipped' => false,
            'reason' => 'git_mode is NO_GITFLOW — tagged the current branch, no merge.',
            'tag' => $tag['tag'],
            'tag_created' => (bool) $tag['created'],
            'release' => $this->releases->set($version, ['status' => 'shipped']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireOpen(string $version): array
    {
        if (! $this->config->releaseModeEnabled()) {
            throw new \InvalidArgumentException('Release mode is disabled (settings.release_mode = NO).');
        }

        $normalized = $this->releases->normalizeVersion($version);
        $release = $this->releases->find($normalized);

        if ($release === null) {
            throw new \InvalidArgumentException("Release {$normalized} not found.");
        }

        if (($release['status'] ?? '') === 'shipped') {
            throw new \InvalidArgumentException("Release {$normalized} is already shipped.");
        }

        return $release;
    }

    protected function resolveVersion(?string $version): string
    {
        if ($version !== null && trim($version) !== '') {
            return $this->releases->normalizeVersion($version);
        }

        $context = $this->context();

        if (is_string($context['active_release']) && $context['active_release'] !== '') {
            return $context['active_release'];
        }

        if ($context['needs_choice']) {
            throw new \InvalidArgumentException(
                'More than one release is in progress ('.implode(', ', $context['in_progress']).'). Pass --semver.'
            );
        }

        throw new \InvalidArgumentException('No in-progress release. Pass --semver or start one with release-cut.');
    }

    /**
     * @param  array<string, mixed>  $release
     */
    protected function releaseBranchName(array $release): string
    {
        $branch = trim((string) ($release['branch'] ?? ''));

        if ($branch === '') {
            $branch = 'release/'.(string) ($release['version'] ?? '');
        }

        $this->assertSafeRef($branch);

        return $branch;
    }

    protected function featureBranchName(string $spec, ?string $slug): string
    {
        $spec = strtoupper(trim($spec));

        if (preg_match('/^US-\d+$/', $spec) !== 1) {
            throw new \InvalidArgumentException('Spec must look like US-001.');
        }

        $name = 'feature/'.$spec;

        if ($slug !== null && trim($slug) !== '') {
            $clean = strtolower(trim($slug));
            $clean = preg_replace('/[^a-z0-9]+/', '-', $clean) ?? '';
            $clean = trim($clean, '-');
            $clean = substr($clean, 0, 40);

            if ($clean !== '') {
                $name .= '-'.$clean;
            }
        }

        $this->assertSafeRef($name);

        return $name;
    }

    protected function assertSafeRef(string $ref): void
    {
        if (preg_match('#^[A-Za-z0-9][A-Za-z0-9._/+-]*$#', $ref) !== 1 || str_contains($ref, '..')) {
            throw new \InvalidArgumentException("Unsafe git ref: {$ref}");
        }
    }

    /**
     * @return array{ok: bool, created?: bool, from?: string, error?: string}
     */
    protected function ensureDevelop(): array
    {
        if ($this->git->localBranchExists('develop')) {
            return ['ok' => true, 'created' => false];
        }

        $base = null;

        foreach (['main', 'master'] as $candidate) {
            if ($this->git->localBranchExists($candidate)) {
                $base = $candidate;
                break;
            }
        }

        if ($base === null) {
            return ['ok' => false, 'error' => 'Cannot open a release branch: no develop, main, or master branch.'];
        }

        $created = $this->git->run('branch', 'develop', $base);

        if (! $created['ok']) {
            return ['ok' => false, 'error' => $created['output'] !== '' ? $created['output'] : 'Could not create develop.'];
        }

        return ['ok' => true, 'created' => true, 'from' => $base];
    }

    /**
     * @return array{checked_out: bool, reason: string|null}
     */
    protected function checkout(string $branch): array
    {
        if ($this->git->currentBranch() === $branch) {
            return ['checked_out' => true, 'reason' => null];
        }

        if (! $this->git->workingTreeClean()) {
            return [
                'checked_out' => false,
                'reason' => "Working tree is dirty. {$branch} is ready; commit or stash, then checkout {$branch}.",
            ];
        }

        $result = $this->git->run('checkout', $branch);

        if (! $result['ok']) {
            return [
                'checked_out' => false,
                'reason' => $result['output'] !== '' ? $result['output'] : "Could not checkout {$branch}.",
            ];
        }

        return ['checked_out' => true, 'reason' => null];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    protected function mergeInto(string $target, string $source): array
    {
        $checkout = $this->git->run('checkout', $target);

        if (! $checkout['ok']) {
            return ['ok' => false, 'error' => "Could not checkout {$target}. ".$checkout['output']];
        }

        $merge = $this->git->run('merge', '--no-ff', $source, '-m', "Merge {$source} into {$target}");

        if ($merge['ok']) {
            return ['ok' => true];
        }

        $this->git->run('merge', '--abort');

        $detail = $merge['output'] !== '' ? $merge['output'] : 'merge failed';

        return ['ok' => false, 'error' => "Merge of {$source} into {$target} failed. {$detail}"];
    }

    /**
     * @return array{ok: bool, tag: string, created: bool, error?: string}
     */
    protected function ensureTag(string $version): array
    {
        $tag = 'v'.$version;
        $this->assertSafeRef($tag);
        $exists = $this->git->run('rev-parse', '-q', '--verify', 'refs/tags/'.$tag);

        if ($exists['ok']) {
            return ['ok' => true, 'tag' => $tag, 'created' => false];
        }

        $created = $this->git->run('tag', '-a', $tag, '-m', 'Release '.$version);

        if (! $created['ok']) {
            return [
                'ok' => false,
                'tag' => $tag,
                'created' => false,
                'error' => $created['output'] !== '' ? $created['output'] : "Could not create tag {$tag}.",
            ];
        }

        return ['ok' => true, 'tag' => $tag, 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<string>  $refs
     * @return array<string, mixed>
     */
    protected function withPush(array $result, bool $push, array $refs): array
    {
        if (! $push) {
            $result['pushed'] = false;

            return $result;
        }

        $pushed = [];
        $error = null;

        foreach ($refs as $ref) {
            $remote = str_starts_with($ref, 'v') && ! str_contains($ref, '/')
                ? $this->git->run('push', 'origin', $ref)
                : $this->git->run('push', '-u', 'origin', $ref);
            $pushed[$ref] = $remote['ok'];

            if (! $remote['ok'] && $error === null) {
                $error = $remote['output'] !== '' ? $remote['output'] : "Could not push {$ref}.";
            }
        }

        $result['pushed'] = ! in_array(false, $pushed, true);
        $result['push'] = $pushed;

        if ($error !== null) {
            $result['push_error'] = $error;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $release
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function gitFailure(string $error, ?array $release, array $extra = []): array
    {
        return array_merge([
            'ok' => false,
            'error' => $error,
            'release' => $release,
        ], $extra);
    }
}
