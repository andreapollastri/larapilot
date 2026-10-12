#!/usr/bin/env python3
"""Puts the mail and the dumps the app kept at the times of the story.

Larapilot stamps a kept mail or dump with PHP's own clock (date(), microtime()), which the prepend
cannot freeze, so the traffic of a weekend would all read "a minute ago". traffic.py lists, in order,
the story time of every mail and dump it caused; here the records, in the order they were kept, take
those times, and their ids (which begin with the time) are renamed to match.
"""
import json
import sys
from datetime import datetime
from pathlib import Path

TARGET = Path(sys.argv[1]).resolve()
STORE = TARGET / "fernway" / "storage" / "larapilot"
events = json.loads((TARGET / "work" / "traffic-events.json").read_text())

for kind in ("mail", "dumps"):
    times = [datetime.fromisoformat(at) for k, at in events if k == kind]
    records = sorted((STORE / kind).glob("*.json"))
    if len(records) != len(times):
        print(f"retime: {len(records)} {kind} records for {len(times)} events; the last ones are matched")
    pairs = list(zip(records[-len(times):] if times else [], times[-len(records):] if records else []))
    for n, (path, when) in enumerate(pairs):
        record = json.loads(path.read_text())
        old = record["id"]
        new = when.strftime("%Y%m%d%H%M%S") + f"{n:06d}" + old[-6:]
        record["id"] = new
        record["at"] = when.strftime("%Y-%m-%dT%H:%M:%S+00:00")
        for part in path.parent.glob(old + ".*"):
            if part.suffix != ".json":
                part.rename(part.with_name(new + part.suffix))
        path.unlink()
        (path.parent / f"{new}.json").write_text(json.dumps(record, ensure_ascii=False, separators=(",", ":")))
    print(f"retime: {len(pairs)} {kind} records")
