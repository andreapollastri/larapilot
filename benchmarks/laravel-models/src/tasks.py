from __future__ import annotations

import glob
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import yaml


@dataclass
class Task:
    id: str
    title: str
    prompt: str
    verify: list[str]
    category: str = "general"
    difficulty: str = "medium"
    timeout_s: int = 600
    setup_files: dict[str, str] = field(default_factory=dict)
    source_path: Path | None = None

    @classmethod
    def from_dict(cls, data: dict[str, Any], source: Path | None = None) -> Task:
        setup = data.get("setup") or {}
        files = setup.get("files") or {}
        return cls(
            id=data["id"],
            title=data.get("title") or data["id"],
            prompt=data["prompt"].strip(),
            verify=list(data.get("verify") or []),
            category=data.get("category", "general"),
            difficulty=data.get("difficulty", "medium"),
            timeout_s=int(data.get("timeout_s", 600)),
            setup_files={str(k): str(v) for k, v in files.items()},
            source_path=source,
        )


def load_config(path: Path) -> dict[str, Any]:
    with path.open(encoding="utf-8") as fh:
        data = yaml.safe_load(fh) or {}
    if not isinstance(data, dict):
        raise ValueError(f"Config must be a mapping: {path}")
    return data


def load_tasks(pattern: str, base_dir: Path) -> list[Task]:
    paths = sorted(Path(p) for p in glob.glob(str(base_dir / pattern)))
    if not paths:
        raise FileNotFoundError(f"No tasks matched {base_dir / pattern}")
    tasks: list[Task] = []
    for path in paths:
        with path.open(encoding="utf-8") as fh:
            data = yaml.safe_load(fh) or {}
        tasks.append(Task.from_dict(data, source=path))
    return tasks
