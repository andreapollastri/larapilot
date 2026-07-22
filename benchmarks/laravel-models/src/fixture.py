from __future__ import annotations

import shutil
import subprocess
from pathlib import Path


IGNORE_NAMES = {
    ".git",
    "node_modules",
    "vendor",
    ".work",
    "__pycache__",
    ".phpunit.result.cache",
    ".phpunit.cache",
}


def run(cmd: list[str], *, cwd: Path, timeout: int = 1800) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        cmd,
        cwd=cwd,
        text=True,
        capture_output=True,
        timeout=timeout,
        check=False,
    )


def prepare_laravel_fixture(
    fixture_path: Path,
    *,
    laravel_version: str = "^12.0",
    force: bool = False,
) -> Path:
    """Create a stock Laravel app once, then reuse it as the benchmark base."""
    if fixture_path.exists() and not force:
        if (fixture_path / "artisan").exists():
            return fixture_path
        raise RuntimeError(f"Fixture path exists but is not a Laravel app: {fixture_path}")

    if fixture_path.exists() and force:
        shutil.rmtree(fixture_path)

    fixture_path.parent.mkdir(parents=True, exist_ok=True)
    tmp = fixture_path.parent / f".tmp-{fixture_path.name}"
    if tmp.exists():
        shutil.rmtree(tmp)

    create = run(
        [
            "composer",
            "create-project",
            f"laravel/laravel:{laravel_version}",
            tmp.name,
            "--prefer-dist",
            "--no-interaction",
        ],
        cwd=fixture_path.parent,
        timeout=1800,
    )
    if create.returncode != 0:
        raise RuntimeError(
            "composer create-project failed:\n"
            f"{create.stdout}\n{create.stderr}"
        )

    # Pest is the default assertion style in our tasks.
    pest = run(
        ["composer", "require", "pestphp/pest", "--dev", "--no-interaction", "--with-all-dependencies"],
        cwd=tmp,
        timeout=900,
    )
    if pest.returncode != 0:
        # Laravel 11+ often ships with Pest already — soft-fail.
        pass

    init = run(["php", "artisan", "pest:install", "--no-interaction"], cwd=tmp, timeout=120)
    if init.returncode != 0:
        # Older / already-installed Pest layouts are fine.
        pass

    # SQLite in-memory / file for hermetic verifies
    env_path = tmp / ".env"
    if env_path.exists():
        text = env_path.read_text(encoding="utf-8")
        replacements = {
            "DB_CONNECTION=sqlite": "DB_CONNECTION=sqlite",
            "DB_CONNECTION=mysql": "DB_CONNECTION=sqlite",
            "DB_CONNECTION=pgsql": "DB_CONNECTION=sqlite",
        }
        for old, new in replacements.items():
            if old in text and old != "DB_CONNECTION=sqlite":
                text = text.replace(old, new)
        if "DB_CONNECTION=sqlite" not in text:
            text += "\nDB_CONNECTION=sqlite\n"
        # Comment out host DB settings that confuse sqlite
        lines = []
        for line in text.splitlines():
            if line.startswith(("DB_HOST=", "DB_PORT=", "DB_DATABASE=", "DB_USERNAME=", "DB_PASSWORD=")):
                lines.append(f"# {line}")
            else:
                lines.append(line)
        if "DB_DATABASE=" not in text or all(
            l.startswith("#") or not l.startswith("DB_DATABASE=") for l in lines
        ):
            lines.append("DB_DATABASE=database/database.sqlite")
        env_path.write_text("\n".join(lines) + "\n", encoding="utf-8")

    db_file = tmp / "database" / "database.sqlite"
    db_file.parent.mkdir(parents=True, exist_ok=True)
    db_file.touch()

    migrate = run(["php", "artisan", "migrate", "--force", "--no-interaction"], cwd=tmp, timeout=180)
    if migrate.returncode != 0:
        raise RuntimeError(f"migrate failed:\n{migrate.stdout}\n{migrate.stderr}")

    tmp.rename(fixture_path)
    return fixture_path


def copy_fixture(fixture_path: Path, dest: Path) -> Path:
    if dest.exists():
        shutil.rmtree(dest)
    dest.parent.mkdir(parents=True, exist_ok=True)

    def _ignore(directory: str, names: list[str]) -> set[str]:
        ignored = set()
        for name in names:
            if name in IGNORE_NAMES:
                ignored.add(name)
        return ignored

    shutil.copytree(fixture_path, dest, ignore=_ignore)

    # Re-link vendor from fixture to save disk + time (optional hard copy if missing)
    fixture_vendor = fixture_path / "vendor"
    dest_vendor = dest / "vendor"
    if fixture_vendor.exists() and not dest_vendor.exists():
        try:
            dest_vendor.symlink_to(fixture_vendor.resolve(), target_is_directory=True)
        except OSError:
            shutil.copytree(fixture_vendor, dest_vendor)

    db = dest / "database" / "database.sqlite"
    db.parent.mkdir(parents=True, exist_ok=True)
    if not db.exists():
        db.touch()

    return dest


def apply_setup_files(workdir: Path, files: dict[str, str]) -> None:
    for rel, content in files.items():
        path = workdir / rel
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8")


def git_snapshot_stats(workdir: Path) -> dict[str, int | str]:
    """Best-effort diff stats vs clean fixture copy start (tracked via git init)."""
    if not (workdir / ".git").exists():
        run(["git", "init"], cwd=workdir, timeout=30)
        run(["git", "add", "-A"], cwd=workdir, timeout=60)
        run(
            ["git", "-c", "user.email=bench@local", "-c", "user.name=bench", "commit", "-m", "baseline", "--quiet"],
            cwd=workdir,
            timeout=60,
        )

    status = run(["git", "status", "--porcelain"], cwd=workdir, timeout=30)
    diff = run(["git", "diff", "--numstat", "HEAD"], cwd=workdir, timeout=30)
    untracked = run(["git", "ls-files", "--others", "--exclude-standard"], cwd=workdir, timeout=30)

    changed_paths: set[str] = set()
    insertions = 0
    deletions = 0

    for line in (diff.stdout or "").splitlines():
        parts = line.split("\t")
        if len(parts) >= 3:
            ins, dele, path = parts[0], parts[1], parts[2]
            changed_paths.add(path)
            if ins.isdigit():
                insertions += int(ins)
            if dele.isdigit():
                deletions += int(dele)

    for line in (status.stdout or "").splitlines():
        path = line[3:].strip()
        if " -> " in path:
            path = path.split(" -> ", 1)[1]
        if path:
            changed_paths.add(path)

    for line in (untracked.stdout or "").splitlines():
        if line.strip():
            changed_paths.add(line.strip())

    return {
        "files_changed": len(changed_paths),
        "insertions": insertions,
        "deletions": deletions,
        "status_porcelain": (status.stdout or "").strip(),
    }
