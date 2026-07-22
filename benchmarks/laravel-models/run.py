#!/usr/bin/env python3
"""CLI: compare Cursor models on Laravel coding tasks."""

from __future__ import annotations

import argparse
import json
import os
import shutil
import sys
from datetime import datetime, timezone
from pathlib import Path

from rich.console import Console
from rich.table import Table

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

from src.fixture import prepare_laravel_fixture  # noqa: E402
from src.runner import list_available_models, run_one  # noqa: E402
from src.scoring import leaderboard, render_markdown, score_records  # noqa: E402
from src.tasks import load_config, load_tasks  # noqa: E402

console = Console()


def _resolve_config(path: Path | None) -> Path:
    if path:
        return path
    candidate = ROOT / "config.yaml"
    if candidate.exists():
        return candidate
    example = ROOT / "config.example.yaml"
    if example.exists():
        console.print("[yellow]Using config.example.yaml — copy it to config.yaml to customize.[/yellow]")
        return example
    raise SystemExit("No config.yaml or config.example.yaml found")


def cmd_list_models(_: argparse.Namespace) -> int:
    models = list_available_models()
    table = Table(title="Cursor models available for this API key")
    table.add_column("ID")
    table.add_column("Params")
    for m in models:
        mid = getattr(m, "id", str(m))
        params = getattr(m, "parameters", None) or getattr(m, "params", None) or []
        param_ids = []
        for p in params:
            pid = getattr(p, "id", None) or (p.get("id") if isinstance(p, dict) else str(p))
            param_ids.append(str(pid))
        table.add_row(mid, ", ".join(param_ids) or "—")
    console.print(table)
    return 0


def cmd_prepare_fixture(args: argparse.Namespace) -> int:
    cfg = load_config(_resolve_config(args.config))
    fixture_cfg = cfg.get("fixture") or {}
    path = ROOT / fixture_cfg.get("path", "fixtures/laravel-app")
    version = fixture_cfg.get("laravel_version", "^12.0")
    console.print(f"Preparing Laravel fixture at [bold]{path}[/bold] (version {version})…")
    prepare_laravel_fixture(path, laravel_version=version, force=args.force)
    console.print("[green]Fixture ready.[/green]")
    return 0


def cmd_run(args: argparse.Namespace) -> int:
    cfg_path = _resolve_config(args.config)
    cfg = load_config(cfg_path)

    api_key_env = cfg.get("api_key_env", "CURSOR_API_KEY")
    api_key = os.environ.get(api_key_env, "").strip()
    if not api_key:
        raise SystemExit(f"Missing API key: export {api_key_env}=...")

    models = args.model or list(cfg.get("models") or [])
    if not models:
        raise SystemExit("No models configured. Edit config.yaml or pass --model")

    tasks = load_tasks(cfg.get("tasks_glob", "tasks/*.yaml"), ROOT)
    if args.task:
        wanted = set(args.task)
        tasks = [t for t in tasks if t.id in wanted]
        missing = wanted - {t.id for t in tasks}
        if missing:
            raise SystemExit(f"Unknown task ids: {', '.join(sorted(missing))}")

    fixture_cfg = cfg.get("fixture") or {}
    fixture_path = ROOT / fixture_cfg.get("path", "fixtures/laravel-app")
    if not (fixture_path / "artisan").exists():
        console.print("[yellow]Fixture missing — creating it now…[/yellow]")
        prepare_laravel_fixture(
            fixture_path,
            laravel_version=fixture_cfg.get("laravel_version", "^12.0"),
        )

    work_root = ROOT / cfg.get("work_root", ".work")
    if args.clean_work and work_root.exists():
        shutil.rmtree(work_root)
    work_root.mkdir(parents=True, exist_ok=True)

    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    out_dir = ROOT / "results" / stamp
    out_dir.mkdir(parents=True, exist_ok=True)

    model_params = cfg.get("model_params") or {}
    records = []

    console.print(
        f"Running [bold]{len(tasks)}[/bold] tasks × [bold]{len(models)}[/bold] models "
        f"→ {out_dir}"
    )

    for task in tasks:
        for model in models:
            console.rule(f"{task.id} · {model}")
            record = run_one(
                model=model,
                task=task,
                fixture_path=fixture_path,
                work_root=work_root,
                api_key=api_key,
                model_params=model_params,
                stream=args.stream,
            )
            records.append(record)
            mark = "✅" if record.verify_ok else "❌"
            console.print(
                f"{mark} status={record.status} duration_ms={record.duration_ms or record.wall_ms} "
                f"tokens={(record.usage or {}).get('total_tokens')}"
            )
            if record.error and not record.verify_ok:
                console.print(f"[red]{record.error[:500]}[/red]")

            # Incremental dump
            (out_dir / "results.json").write_text(
                json.dumps([r.to_dict() for r in records], indent=2),
                encoding="utf-8",
            )

    weights = (cfg.get("scoring") or {}).get("weights") or {}
    score_records(records, weights)
    board = leaderboard(records)

    payload = {
        "created_at": stamp,
        "config": str(cfg_path),
        "models": models,
        "tasks": [t.id for t in tasks],
        "leaderboard": board,
        "results": [r.to_dict() for r in records],
    }
    (out_dir / "results.json").write_text(json.dumps(payload, indent=2), encoding="utf-8")
    md = render_markdown(records, board)
    (out_dir / "REPORT.md").write_text(md, encoding="utf-8")

    table = Table(title="Leaderboard")
    table.add_column("Rank", justify="right")
    table.add_column("Model")
    table.add_column("Pass %", justify="right")
    table.add_column("Avg score", justify="right")
    table.add_column("Avg ms", justify="right")
    table.add_column("Avg tokens", justify="right")
    for i, row in enumerate(board, start=1):
        table.add_row(
            str(i),
            row["model"],
            f"{row['pass_rate']}%",
            str(row["avg_score"]),
            str(row["avg_duration_ms"] or "—"),
            str(row["avg_total_tokens"] or "—"),
        )
    console.print(table)
    console.print(f"[green]Wrote[/green] {out_dir / 'REPORT.md'}")

    if not cfg.get("keep_workdirs", True) and work_root.exists():
        shutil.rmtree(work_root)

    return 0 if all(r.verify_ok for r in records) else 2


def cmd_report(args: argparse.Namespace) -> int:
    path = Path(args.results)
    data = json.loads(path.read_text(encoding="utf-8"))
    from src.scoring import RunRecord

    records = [RunRecord(**{k: v for k, v in r.items() if k in RunRecord.__dataclass_fields__}) for r in data["results"]]
    board = data.get("leaderboard") or leaderboard(records)
    md = render_markdown(records, board)
    out = Path(args.out) if args.out else path.with_name("REPORT.md")
    out.write_text(md, encoding="utf-8")
    console.print(md)
    console.print(f"[green]Wrote[/green] {out}")
    return 0


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description="Benchmark Cursor models on Laravel tasks")
    p.add_argument("--config", type=Path, default=None, help="Path to config.yaml")
    sub = p.add_subparsers(dest="command", required=True)

    s = sub.add_parser("list-models", help="List model IDs for this API key")
    s.set_defaults(func=cmd_list_models)

    s = sub.add_parser("prepare-fixture", help="Create the reusable Laravel fixture app")
    s.add_argument("--force", action="store_true", help="Recreate even if it exists")
    s.set_defaults(func=cmd_prepare_fixture)

    s = sub.add_parser("run", help="Run the benchmark matrix")
    s.add_argument("--model", action="append", help="Model id (repeatable). Overrides config.")
    s.add_argument("--task", action="append", help="Task id (repeatable). Default: all.")
    s.add_argument("--stream", action="store_true", help="Stream agent output to the terminal")
    s.add_argument("--clean-work", action="store_true", help="Wipe .work before starting")
    s.set_defaults(func=cmd_run)

    s = sub.add_parser("report", help="Rebuild markdown from a results.json")
    s.add_argument("results", help="Path to results.json")
    s.add_argument("--out", help="Output markdown path")
    s.set_defaults(func=cmd_report)

    return p


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    return int(args.func(args))


if __name__ == "__main__":
    raise SystemExit(main())
