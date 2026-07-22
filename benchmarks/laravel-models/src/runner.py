from __future__ import annotations

import os
import time
import traceback
from pathlib import Path
from typing import Any

from cursor_sdk import Agent, AgentOptions, Cursor, CursorAgentError, LocalAgentOptions, ModelSelection

from .fixture import apply_setup_files, copy_fixture, git_snapshot_stats, run as shell_run
from .scoring import RunRecord
from .tasks import Task


def list_available_models(api_key: str | None = None) -> list[Any]:
    key = api_key or os.environ.get("CURSOR_API_KEY")
    if not key:
        raise RuntimeError("Set CURSOR_API_KEY or pass api_key")
    return list(Cursor.models.list(api_key=key))


def _usage_to_dict(usage: Any) -> dict[str, Any]:
    if usage is None:
        return {}
    return {
        "input_tokens": getattr(usage, "input_tokens", None),
        "output_tokens": getattr(usage, "output_tokens", None),
        "cache_read_tokens": getattr(usage, "cache_read_tokens", None),
        "cache_write_tokens": getattr(usage, "cache_write_tokens", None),
        "total_tokens": getattr(usage, "total_tokens", None),
        "reasoning_tokens": getattr(usage, "reasoning_tokens", None),
    }


def _model_selection(model_id: str, model_params: dict[str, list[dict[str, str]]] | None) -> str | ModelSelection:
    params = (model_params or {}).get(model_id) or []
    if not params:
        return model_id
    from cursor_sdk import ModelParameterValue

    return ModelSelection(
        id=model_id,
        params=[ModelParameterValue(id=p["id"], value=str(p["value"])) for p in params],
    )


def _run_verify(workdir: Path, commands: list[str], timeout_s: int) -> tuple[bool, str]:
    chunks: list[str] = []
    ok = True
    for cmd in commands:
        # Ensure a clean sqlite DB for each verify suite
        migrate = shell_run(
            ["php", "artisan", "migrate:fresh", "--force", "--no-interaction"],
            cwd=workdir,
            timeout=min(120, timeout_s),
        )
        chunks.append(f"$ php artisan migrate:fresh\n{migrate.stdout}\n{migrate.stderr}")
        if migrate.returncode != 0:
            ok = False
            break

        proc = shell_run(["bash", "-lc", cmd], cwd=workdir, timeout=timeout_s)
        chunks.append(f"$ {cmd}\nexit={proc.returncode}\n{proc.stdout}\n{proc.stderr}")
        if proc.returncode != 0:
            ok = False
            break
    return ok, "\n".join(chunks)


def run_one(
    *,
    model: str,
    task: Task,
    fixture_path: Path,
    work_root: Path,
    api_key: str,
    model_params: dict[str, list[dict[str, str]]] | None = None,
    stream: bool = False,
) -> RunRecord:
    workdir = work_root / f"{task.id}__{model.replace('/', '_')}"
    copy_fixture(fixture_path, workdir)
    apply_setup_files(workdir, task.setup_files)
    # Baseline commit after setup so diff measures agent work only
    git_snapshot_stats(workdir)

    record = RunRecord(
        model=model,
        task_id=task.id,
        task_title=task.title,
        status="pending",
        workdir=str(workdir),
    )

    prompt = (
        f"{task.prompt.strip()}\n\n"
        "Constraints:\n"
        "- Work only inside this project directory.\n"
        "- Prefer Laravel conventions and Pest tests.\n"
        "- Do not modify unrelated files.\n"
        "- Do not push, force-push, or change git remotes.\n"
    )

    started = time.perf_counter()
    try:
        selection = _model_selection(model, model_params)
        options = AgentOptions(
            api_key=api_key,
            model=selection,
            local=LocalAgentOptions(cwd=str(workdir)),
        )

        if stream:
            with Agent.create(
                model=selection,
                api_key=api_key,
                local=LocalAgentOptions(cwd=str(workdir)),
            ) as agent:
                record.agent_id = agent.agent_id
                run = agent.send(prompt)
                record.run_id = run.id
                for message in run.messages():
                    if getattr(message, "type", None) == "assistant":
                        content = getattr(getattr(message, "message", None), "content", None) or []
                        for block in content:
                            if getattr(block, "type", None) == "text":
                                print(block.text, end="", flush=True)
                    elif getattr(message, "type", None) == "tool_call":
                        print(f"\n[tool] {message.name}: {message.status}", flush=True)
                result = run.wait()
        else:
            result = Agent.prompt(prompt, options)

        record.agent_id = getattr(result, "agent_id", None) or record.agent_id
        record.run_id = getattr(result, "id", None) or record.run_id
        status = getattr(result, "status", "unknown") or "unknown"
        record.status = getattr(status, "value", None) or str(status)
        record.duration_ms = getattr(result, "duration_ms", None)
        record.result_text = getattr(result, "result", "") or ""
        record.usage = _usage_to_dict(getattr(result, "usage", None))

        if record.status == "error":
            record.error = record.result_text or "run status=error"

    except CursorAgentError as err:
        record.status = "startup_error"
        record.error = f"{err} (retryable={getattr(err, 'is_retryable', None)})"
    except Exception as err:  # noqa: BLE001 — benchmark harness must continue
        record.status = "exception"
        record.error = f"{err}\n{traceback.format_exc()}"
    finally:
        record.wall_ms = int((time.perf_counter() - started) * 1000)

    if record.status == "finished":
        verify_ok, verify_out = _run_verify(workdir, task.verify, task.timeout_s)
        record.verify_ok = verify_ok
        record.verify_output = verify_out
    else:
        record.verify_ok = False
        record.verify_output = record.error

    record.diff = git_snapshot_stats(workdir)
    record.passed = record.verify_ok
    return record
