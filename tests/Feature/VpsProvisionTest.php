<?php

declare(strict_types=1);

it('generates the provisioning script to the requested path', function (): void {
    $out = base_path('build/provision.sh');
    @unlink($out);

    $this->artisan('larapilot:vps-provision', ['--output' => $out])
        ->assertSuccessful();

    expect($out)->toBeFile();

    $contents = (string) file_get_contents($out);

    expect($contents)
        ->toStartWith('#!/usr/bin/env bash')
        ->toContain('prj-ai — VPS provisioning')
        // every supported git provider is wired in
        ->toContain('github/gitlab/bitbucket/azure')
        ->toContain('GitHub CLI (gh)')
        ->toContain('GitLab CLI (glab)')
        ->toContain('Azure CLI (az')
        ->toContain('api.bitbucket.org')
        // the universal PR helper is embedded
        ->toContain('prj-pr — open a Pull/Merge Request')
        // atomic zero-downtime deploys
        ->toContain('publish_release()')
        ->toContain('cmd_rollback()')
        ->toContain('prj-ai rollback <project>')
        ->toContain('mv -T "$base/current.new" "$base/current"')
        ->toContain('root $SRV/$name/current/public')
        ->toContain('fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name')
        // per-developer previews  <user>-<project>.<domain>
        ->toContain('preview_up()')
        ->toContain('preview_down()')
        ->toContain('preview_reap()')
        ->toContain('cmd_preview()')
        ->toContain('prj-ai preview <sub>')
        ->toContain('server_name $fqdn;')
        ->toContain('auth_basic_user_file $PREV_HTPASSWD')
        ->toContain('prj-preview-reap.timer')
        // per-developer git token — mandatory + repo-scoped gate
        ->toContain('prj-token — save YOUR personal git token')
        ->toContain("cat > /usr/local/bin/prj-token <<'EMBED_PRJ_TOKEN'")
        ->toContain('git ls-remote --heads "$REPO"')
        ->toContain('your git token cannot access $REPO');

    // the outer script is syntactically valid bash
    exec('bash -n '.escapeshellarg($out).' 2>&1', $stderr, $code);
    expect($code)->toBe(0, implode("\n", $stderr));

    // each embedded CLI is valid bash too
    foreach (['EMBED_PRJ_AI', 'EMBED_PRJ_WORK', 'EMBED_PRJ_PR', 'EMBED_PRJ_TOKEN'] as $marker) {
        $embedded = preg_replace(
            '/^.*?<<\'?'.$marker.'\'?\n(.*?)\n'.$marker.'\n.*$/s',
            '$1',
            $contents
        );
        expect($embedded)->not->toBe($contents);
        $tmp = tempnam(sys_get_temp_dir(), 'lp-vps-');
        file_put_contents($tmp, $embedded);
        exec('bash -n '.escapeshellarg($tmp).' 2>&1', $e, $c);
        @unlink($tmp);
        expect($c)->toBe(0, $marker.":\n".implode("\n", $e));
    }

    expect(substr(sprintf('%o', fileperms($out)), -3))->toBe('755');
});

it('does not overwrite an existing script without --force', function (): void {
    $out = base_path('build/provision.sh');
    @mkdir(dirname($out), 0o755, true);
    file_put_contents($out, 'PLACEHOLDER');

    $this->artisan('larapilot:vps-provision', ['--output' => $out])
        ->assertSuccessful();

    expect((string) file_get_contents($out))->toBe('PLACEHOLDER');

    $this->artisan('larapilot:vps-provision', ['--output' => $out, '--force' => true])
        ->assertSuccessful();

    expect((string) file_get_contents($out))->not->toBe('PLACEHOLDER')
        ->and((string) file_get_contents($out))->toStartWith('#!/usr/bin/env bash');
});

it('also writes the operator readme with --with-readme', function (): void {
    $out = base_path('build/provision.sh');
    $readme = base_path('build/VPS-README.md');
    @unlink($out);
    @unlink($readme);

    $this->artisan('larapilot:vps-provision', ['--output' => $out, '--with-readme' => true])
        ->assertSuccessful();

    expect($readme)->toBeFile()
        ->and((string) file_get_contents($readme))->toContain('shared VPS for Laravel');
});

it('prints the script to stdout with --stdout and writes nothing', function (): void {
    $out = base_path('build/provision.sh');
    @unlink($out);

    $this->artisan('larapilot:vps-provision', ['--stdout' => true, '--output' => $out])
        ->expectsOutputToContain('#!/usr/bin/env bash')
        ->assertSuccessful();

    expect(is_file($out))->toBeFalse();
});
