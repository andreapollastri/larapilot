from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any


@dataclass
class RunRecord:
    model: str
    task_id: str
    task_title: str
    status: str
    duration_ms: int | None = None
    wall_ms: int | None = None
    passed: bool = False
    verify_ok: bool = False
    verify_output: str = ""
    result_text: str = ""
    error: str = ""
    agent_id: str | None = None
    run_id: str | None = None
    usage: dict[str, Any] = field(default_factory=dict)
    diff: dict[str, Any] = field(default_factory=dict)
    workdir: str | None = None
    scores: dict[str, float] = field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return {
            "model": self.model,
            "task_id": self.task_id,
            "task_title": self.task_title,
            "status": self.status,
            "duration_ms": self.duration_ms,
            "wall_ms": self.wall_ms,
            "passed": self.passed,
            "verify_ok": self.verify_ok,
            "verify_output": self.verify_output[-4000:],
            "result_text": (self.result_text or "")[:4000],
            "error": self.error,
            "agent_id": self.agent_id,
            "run_id": self.run_id,
            "usage": self.usage,
            "diff": self.diff,
            "workdir": self.workdir,
            "scores": self.scores,
        }


def _safe_div(n: float, d: float) -> float:
    return n / d if d else 0.0


def score_records(records: list[RunRecord], weights: dict[str, float]) -> list[RunRecord]:
    """Assign relative scores per task, then attach to each record."""
    by_task: dict[str, list[RunRecord]] = {}
    for r in records:
        by_task.setdefault(r.task_id, []).append(r)

    w_pass = float(weights.get("pass", 50))
    w_speed = float(weights.get("speed", 20))
    w_tokens = float(weights.get("tokens", 15))
    w_clean = float(weights.get("cleanliness", 15))

    for task_id, group in by_task.items():
        finishers = [r for r in group if r.verify_ok]
        durations = [r.duration_ms or r.wall_ms or 0 for r in finishers if (r.duration_ms or r.wall_ms)]
        tokens = [
            int((r.usage or {}).get("total_tokens") or 0)
            for r in finishers
            if (r.usage or {}).get("total_tokens")
        ]
        files = [
            int((r.diff or {}).get("files_changed") or 0)
            for r in finishers
            if (r.diff or {}).get("files_changed") is not None
        ]

        max_dur = max(durations) if durations else 0
        min_dur = min(durations) if durations else 0
        max_tok = max(tokens) if tokens else 0
        min_tok = min(tokens) if tokens else 0
        max_files = max(files) if files else 0
        min_files = min(files) if files else 0

        for r in group:
            pass_score = 1.0 if r.verify_ok else 0.0

            dur = r.duration_ms or r.wall_ms or 0
            if r.verify_ok and max_dur > min_dur and dur:
                speed_score = 1.0 - _safe_div(dur - min_dur, max_dur - min_dur)
            elif r.verify_ok:
                speed_score = 1.0
            else:
                speed_score = 0.0

            tok = int((r.usage or {}).get("total_tokens") or 0)
            if r.verify_ok and max_tok > min_tok and tok:
                token_score = 1.0 - _safe_div(tok - min_tok, max_tok - min_tok)
            elif r.verify_ok:
                token_score = 1.0
            else:
                token_score = 0.0

            fc = int((r.diff or {}).get("files_changed") or 0)
            if r.verify_ok and max_files > min_files:
                clean_score = 1.0 - _safe_div(fc - min_files, max_files - min_files)
            elif r.verify_ok:
                clean_score = 1.0
            else:
                clean_score = 0.0

            total = (
                pass_score * w_pass
                + speed_score * w_speed
                + token_score * w_tokens
                + clean_score * w_clean
            )
            r.scores = {
                "pass": round(pass_score * w_pass, 2),
                "speed": round(speed_score * w_speed, 2),
                "tokens": round(token_score * w_tokens, 2),
                "cleanliness": round(clean_score * w_clean, 2),
                "total": round(total, 2),
            }
            r.passed = r.verify_ok

    return records


def leaderboard(records: list[RunRecord]) -> list[dict[str, Any]]:
    by_model: dict[str, list[RunRecord]] = {}
    for r in records:
        by_model.setdefault(r.model, []).append(r)

    rows: list[dict[str, Any]] = []
    for model, group in by_model.items():
        n = len(group)
        passed = sum(1 for r in group if r.verify_ok)
        avg_score = _safe_div(sum(r.scores.get("total", 0.0) for r in group), n)
        avg_ms = _safe_div(
            sum((r.duration_ms or r.wall_ms or 0) for r in group if r.verify_ok),
            max(passed, 1),
        )
        avg_tokens = _safe_div(
            sum(int((r.usage or {}).get("total_tokens") or 0) for r in group if r.verify_ok),
            max(passed, 1),
        )
        rows.append(
            {
                "model": model,
                "tasks": n,
                "passed": passed,
                "pass_rate": round(_safe_div(passed, n) * 100, 1),
                "avg_score": round(avg_score, 2),
                "avg_duration_ms": int(avg_ms) if passed else None,
                "avg_total_tokens": int(avg_tokens) if passed else None,
            }
        )

    rows.sort(key=lambda r: (-r["avg_score"], -r["pass_rate"], r["model"]))
    return rows


def render_markdown(records: list[RunRecord], board: list[dict[str, Any]]) -> str:
    lines = [
        "# Laravel × Cursor models — benchmark report",
        "",
        "## Leaderboard",
        "",
        "| Rank | Model | Pass rate | Avg score | Avg duration (ms) | Avg tokens |",
        "| ---: | --- | ---: | ---: | ---: | ---: |",
    ]
    for i, row in enumerate(board, start=1):
        lines.append(
            f"| {i} | `{row['model']}` | {row['pass_rate']}% "
            f"({row['passed']}/{row['tasks']}) | {row['avg_score']} | "
            f"{row['avg_duration_ms'] or '—'} | {row['avg_total_tokens'] or '—'} |"
        )

    lines += ["", "## Per task", ""]
    task_ids = sorted({r.task_id for r in records})
    for task_id in task_ids:
        group = [r for r in records if r.task_id == task_id]
        title = group[0].task_title if group else task_id
        lines += [f"### `{task_id}` — {title}", ""]
        lines += [
            "| Model | Verify | Duration (ms) | Tokens | Files Δ | Score |",
            "| --- | --- | ---: | ---: | ---: | ---: |",
        ]
        group.sort(key=lambda r: (-r.scores.get("total", 0.0), r.model))
        for r in group:
            tok = (r.usage or {}).get("total_tokens")
            files = (r.diff or {}).get("files_changed")
            dur = r.duration_ms or r.wall_ms
            lines.append(
                f"| `{r.model}` | {'✅' if r.verify_ok else '❌'} | "
                f"{dur if dur is not None else '—'} | "
                f"{tok if tok is not None else '—'} | "
                f"{files if files is not None else '—'} | "
                f"{r.scores.get('total', 0)} |"
            )
        lines.append("")

    lines += [
        "## Notes for the article",
        "",
        "- **Pass** = automated `verify` commands (usually Pest) all green.",
        "- **Speed / tokens / cleanliness** are relative among models that passed the same task.",
        "- Re-run with the same fixture + tasks for fair comparisons; pin model IDs from `list-models`.",
        "",
    ]
    return "\n".join(lines)
