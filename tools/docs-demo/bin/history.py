#!/usr/bin/env python3
"""Replays five weeks of Fernway through the real larapilot commands and a Gitflow history.

    python3 tools/docs-demo/bin/history.py <target-dir>

Run after setup-app.sh. Every larapilot command runs with DEMO_NOW (Carbon::setTestNow in the prepend
file) and every git commit with GIT_AUTHOR_DATE / GIT_COMMITTER_DATE, so the dashboard reads a story
that happened between Monday 7 and Tuesday 6 October 2026.

Branch rule: feature commits carry code only; `.larapilot/` is committed on the release lines. The
`.larapilot/` folder is the single source of truth for the backlog, so every branch switch saves it
and puts it back (git would otherwise swap in the snapshot of the branch).
"""
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from datetime import date, datetime, timedelta
from pathlib import Path

sys.dont_write_bytecode = True  # no __pycache__ in the repository
KIT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(KIT / "story"))
import backlog  # noqa: E402
import workspace  # noqa: E402

TARGET = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else sys.exit("usage: history.py <target-dir>")
APP = TARGET / "fernway"
STATE = TARGET / "state"
SRC = KIT / "fernway"
TB = str(KIT / "bin" / "tb")

PEOPLE = {
    "maya": ("Maya Chen", "maya@fernway.example"),
    "luca": ("Luca Moretti", "luca@fernway.example"),
    "sam": ("Sam Okafor", "sam@fernway.example"),
    "priya": ("Priya Raman", "priya@fernway.example"),
}
BY_NAME = {v[0]: k for k, v in PEOPLE.items()}
SPECS = {s["code"]: s for s in backlog.SPECS}
VERBOSE = os.environ.get("DEMO_VERBOSE") == "1"


# The story is written for the week of Monday 12 October 2026: the inception is five weeks earlier,
# on Monday 7 September, and the last commits are on Friday 9 October. The Plan, the Roadmap, and the
# Forecast read "today" from PHP's own clock (DateTimeImmutable('today')), which no prepend can freeze,
# so a build in another week moves every date of the story by whole weeks to keep the same picture.
STORY_WEEK = date(2026, 10, 12)
SHIFT = timedelta(weeks=(date.today() - timedelta(days=date.today().weekday()) - STORY_WEEK).days // 7) \
    if os.environ.get("DEMO_KEEP_DATES") != "1" else timedelta(0)


def shift_day(iso_day):
    """'2026-09-25' -> the same weekday of the build's story."""
    return (date.fromisoformat(iso_day) + SHIFT).isoformat()


def ts(day, hm):
    """'09-08', '09:40' -> ISO time in UTC, shifted to the build week."""
    return (datetime.fromisoformat(f"2026-{day}T{hm}:00+00:00") + SHIFT).isoformat()


def env_for(at, who):
    name, email = PEOPLE[who]
    env = dict(os.environ)
    env.update({
        "DEMO_NOW": at,
        "GIT_AUTHOR_NAME": name, "GIT_AUTHOR_EMAIL": email, "GIT_AUTHOR_DATE": at,
        "GIT_COMMITTER_NAME": name, "GIT_COMMITTER_EMAIL": email, "GIT_COMMITTER_DATE": at,
    })
    return env


def larapilot(at, who, *args, check=True):
    cmd = [TB, str(TARGET), *args]
    out = subprocess.run(cmd, env=env_for(at, who), capture_output=True, text=True)
    if VERBOSE or (check and out.returncode != 0):
        print("$", " ".join(args), "->", out.returncode)
        print(out.stdout[-2500:], out.stderr[-2500:])
    if check and out.returncode != 0:
        sys.exit(f"failed: {' '.join(args)}")
    return out.stdout


def git(at, who, *args, check=True):
    out = subprocess.run(["git", *args], cwd=APP, env=env_for(at, who), capture_output=True, text=True)
    if check and out.returncode != 0:
        print(out.stdout, out.stderr)
        sys.exit(f"git failed: {' '.join(args)}")
    return out.stdout.strip()


def payload(data, name):
    path = Path(tempfile.gettempdir()) / f"fernway-{name}.json"
    path.write_text(json.dumps(data, indent=2))
    return str(path)


# ---------------------------------------------------------------- branches and .larapilot state

def save_state():
    if STATE.exists():
        shutil.rmtree(STATE)
    shutil.copytree(APP / ".larapilot", STATE)


def restore_state():
    subprocess.run(["rsync", "-a", "--delete", f"{STATE}/", f"{APP}/.larapilot/"], check=True)


def switch(at, who, branch, create_from=None):
    save_state()
    if create_from:
        git(at, who, "checkout", "-f", "-b", branch, create_from)
    else:
        git(at, who, "checkout", "-f", branch)
    restore_state()


def commit_larapilot(at, who, message):
    git(at, who, "add", "-A", ".larapilot")
    if git(at, who, "status", "--porcelain", "--", ".larapilot"):
        git(at, who, "commit", "-q", "-m", message)


def code_commit(at, who, message, files):
    """Copy the files of a task from the kit and commit them. `path@variant` takes the variant the
    branch can run (routes/web.php grows feature by feature) from fernway/.versions/."""
    paths = []
    for rel in files:
        rel, _, variant = rel.partition("@")
        source = SRC / ".versions" / rel / variant if variant else SRC / rel
        dest = APP / rel
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, dest)
        paths.append(rel)
    files = paths
    if files:
        git(at, who, "add", "--", *files)
        git(at, who, "commit", "-q", "-m", message)
    else:
        git(at, who, "commit", "-q", "--allow-empty", "-m", message)
    return git(at, who, "rev-parse", "HEAD")


# ---------------------------------------------------------------- the spec lifecycle

def plan(code, at, who="priya", done_until=0):
    data = backlog.plan_payload(SPECS[code])
    for i, t in enumerate(data["tasks"], start=1):
        if i <= done_until:
            t["status"] = "DONE"
    larapilot(at, who, "larapilot:spec-plan", code, f"--file={payload(data, code + '-plan')}")


def task_done(code, n, at, who):
    s = SPECS[code]
    t = s["tasks"][n - 1]
    message = t["commit"] or f"feat({code}): {t['title'][0].lower()}{t['title'][1:]}"
    sha = code_commit(at, who, f"{message} (TASK-{n:02d})", t["files"])
    larapilot(at, who, "larapilot:task-done", code, f"TASK-{n:02d}", f"--commit={sha}")
    larapilot(at, who, "larapilot:code-log", f"--spec={code}", f"--task=TASK-{n:02d}", f"--commit={sha}", "--skill=larapilot-implement", check=False)
    return sha


def usage(at, who, code, category, skill, tokens, minutes, note=None):
    args = ["larapilot:usage-log", f"--category={category}", f"--tokens={tokens}", f"--minutes={minutes}",
            f"--skill={skill}", f"--user={PEOPLE[who][0]}", f"--ts={at}"]
    if code:
        args.append(f"--spec={code}")
    if note:
        args.append(f"--note={note}")
    larapilot(at, who, *args)


def build(code, who, day, start, steps, review_at, tokens):
    """Start a spec, commit its tasks at the given times, move it to review."""
    s = SPECS[code]
    branch_slug = {"US-001": "bootstrap", "US-002": "studio-signup", "US-003": "instructor-invitations", "US-004": "weekly-timetable",
                   "US-005": "book-a-class", "US-006": "waitlist", "US-007": "late-cancellation", "US-008": "front-desk-check-in",
                   "US-009": "class-packs", "US-010": "stripe-checkout", "US-012": "booking-confirmation-email"}[code]
    branch = f"feature/{code}-{branch_slug}"
    larapilot(ts(day, start), who, "larapilot:release-feature", f"--semver={s['release']}", f"--spec={code}", f"--slug={branch_slug}", "--no-checkout")
    switch(ts(day, start), who, branch)
    larapilot(ts(day, start), who, "larapilot:spec-start", code)
    for n, hm in enumerate(steps, start=1):
        d, t = (hm.split(" ") if " " in hm else (day, hm))
        task_done(code, n, ts(d, t), who)
        usage(ts(d, t), who, code, "implementation", "larapilot-implement", tokens[n - 1], 25 + 7 * n)
    if review_at:
        d, t = review_at.split(" ") if " " in review_at else (day, review_at)
        larapilot(ts(d, t), who, "larapilot:spec-review", code, f"--note=Ready for review: {len(s['tasks'])} tasks, Pest green.")
        usage(ts(d, t), who, code, "review", "larapilot-review", 61000 + 1000 * len(steps), 14)
    return branch


def merge_pr(code, number, branch, into, at, who="priya", approve=True):
    switch(at, who, into)
    git(at, who, "merge", "--no-ff", branch, "-m", f"Merge pull request #{number} from fernway/{branch}\n\n{code}: {SPECS[code]['title']}")
    sha = git(at, who, "rev-parse", "HEAD")
    if approve:
        larapilot(at, who, "larapilot:spec-approve", code, f"--commit={sha}")
    return sha


def push(at, who, *refs):
    git(at, who, "push", "-q", "-u", "origin", *refs)


# ---------------------------------------------------------------- the story

def main():
    origin = TARGET / "origin.git"
    if origin.exists():
        shutil.rmtree(origin)
    subprocess.run(["git", "init", "-q", "--bare", "-b", "main", str(origin)], check=True)

    shutil.copy2(SRC / ".gitignore", APP / ".gitignore")
    git(ts("09-07", "08:30"), "maya", "init", "-q", "-b", "main")
    git(ts("09-07", "08:30"), "maya", "config", "user.name", "Maya Chen")
    git(ts("09-07", "08:30"), "maya", "config", "user.email", "maya@fernway.example")
    # The remote reads as GitHub (the Security page matches the scanner's repository by it); pushes go
    # to a bare repository beside the app.
    git(ts("09-07", "08:30"), "maya", "remote", "add", "origin", "git@github.com:fernway/fernway.git")
    git(ts("09-07", "08:30"), "maya", "remote", "set-url", "--push", "origin", str(origin))

    # Monday 7 September — inception
    d = "09-07"
    larapilot(ts(d, "08:40"), "maya", "larapilot:settings-set", "--effort=STANDARD", "--backlog=STANDARD", "--git-mode=GITFLOW",
              "--testing=NORMAL", "--release-mode=YES", "--comments=YES", "--code-history=YES", "--account=COMPANY",
              "--sprints=YES", "--public-roadmap=YES", "--decision-log=YES", "--lucille=YES")
    workspace.write_materials(APP)
    git(ts(d, "08:50"), "maya", "add", "-A")
    git(ts(d, "08:50"), "maya", "commit", "-q", "-m", "chore: Laravel app with Larapilot")
    usage(ts(d, "09:30"), "priya", None, "analysis", "larapilot-inception", 412000, 74, "Discovery with Priya: personas, journeys, pricing")
    larapilot(ts(d, "11:10"), "priya", "larapilot:prd-write", f"--file={KIT / 'story' / 'PRD.md'}")
    larapilot(ts(d, "11:15"), "priya", "larapilot:choices-set", *workspace.CHOICES)
    usage(ts(d, "11:20"), "priya", None, "analysis", "larapilot-prd", 236000, 41)
    for topic, value, why, at in workspace.DECISIONS:
        larapilot(ts(*at), "priya", "larapilot:decision-log", f"--topic={topic}", f"--value={value}", f"--rationale={why}", "--skill=larapilot-inception")

    specs = []
    for s in backlog.SPECS:
        title, objective, deadline = backlog.EPICS[s["epic"]]
        specs.append({"code": s["code"], "title": s["title"], "priority": s["priority"], "points": s["points"], "status": "TODO",
                      "epic": {"code": s["epic"], "title": title, "objective": objective, "deadline": shift_day(deadline)},
                      **({"blocked_by": [s["blocked"]]} if s["blocked"] else {}),
                      "body": backlog.body(s, "TODO")})
    larapilot(ts(d, "14:20"), "priya", "larapilot:spec-add", f"--file={payload({'specs': specs}, 'specs')}")
    usage(ts(d, "14:25"), "priya", None, "analysis", "larapilot-spec", 318000, 52, "16 stories in 5 epics")
    releases = {"0.1.0": "internal demo", "0.2.0": "beta for 10 studios", "0.3.0": "public launch"}
    for version, title in releases.items():
        codes = ",".join(s["code"] for s in backlog.SPECS if s["release"] == version)
        larapilot(ts(d, "15:00"), "priya", "larapilot:release-add", f"--semver={version}", f"--title={title}", f"--specs={codes}")
    for label, day, version in [("0.1.0 internal demo", "2026-09-25", "0.1.0"), ("0.2.0 beta for 10 studios", "2026-10-16", "0.2.0"), ("0.3.0 public launch", "2026-11-06", "0.3.0")]:
        larapilot(ts(d, "15:05"), "priya", "larapilot:schedule-set", f"--deadline={shift_day(day)}", f"--label={label}", f"--release={version}")
    for s in backlog.SPECS:
        if s["sprint"]:
            larapilot(ts(d, "15:10"), "priya", "larapilot:spec-sprint", s["code"], str(s["sprint"]))
    larapilot(ts(d, "15:30"), "priya", "larapilot:economics-set", *workspace.ECONOMICS)
    larapilot(ts(d, "15:40"), "priya", "larapilot:roadmap-set", f"--intro={workspace.ROADMAP_INTRO}")
    commit_larapilot(ts(d, "16:00"), "priya", "docs(prd): Fernway PRD, backlog and releases")
    push(ts(d, "16:01"), "priya", "main")

    # Tuesday 8 — design, release 0.1.0 cut, US-001
    d = "09-08"
    workspace.write_mockups(APP, "member-app")
    usage(ts(d, "08:10"), "maya", None, "analysis", "larapilot-design", 286000, 48, "Member booking flow, 5 screens")
    larapilot(ts(d, "08:20"), "priya", "larapilot:release-cut", "--semver=0.1.0", "--no-checkout")
    switch(ts(d, "08:20"), "priya", "release/0.1.0")
    commit_larapilot(ts(d, "08:25"), "maya", "docs(design): member booking flow")
    push(ts(d, "08:26"), "priya", "develop", "release/0.1.0")
    for code, who, at in [("US-001", "maya", "08:30"), ("US-002", "luca", "08:35"), ("US-003", "luca", "08:40")]:
        plan(code, ts(d, at), who)
        usage(ts(d, at), who, code, "planning", "larapilot-plan", 72000 + 3000 * int(code[-1]), 11)
    b = build("US-001", "maya", d, "09:00", ["09:40", "10:15", "10:50"], "10:55", [148000, 96000, 121000])
    push(ts(d, "10:56"), "maya", b)
    merge_pr("US-001", 12, b, "release/0.1.0", ts(d, "15:30"))

    # Wednesday 9 — US-002
    b = build("US-002", "luca", "09-09", "09:00", ["09:50", "10:40", "11:20", "11:55"], "11:55", [182000, 214000, 133000, 104000])
    push(ts("09-09", "11:56"), "luca", b)
    merge_pr("US-002", 15, b, "release/0.1.0", ts("09-09", "16:20"))

    # Thursday 10 — US-003; Friday 11 — plans for the next week
    b = build("US-003", "luca", "09-10", "09:30", ["10:20", "11:10", "12:00", "12:44"], "12:44", [171000, 196000, 158000, 97000])
    push(ts("09-10", "12:45"), "luca", b)
    merge_pr("US-003", 19, b, "release/0.1.0", ts("09-11", "17:10"))
    for code, who in [("US-004", "maya"), ("US-005", "sam")]:
        plan(code, ts("09-11", "10:00"), who)
        usage(ts("09-11", "10:00"), who, code, "planning", "larapilot-plan", 88000, 13)

    # Monday 14 — US-004; Tuesday 15 — US-005, sent back once
    b = build("US-004", "maya", "09-14", "13:00", ["14:10", "15:20", "16:05", "16:50", "17:29"], "17:29", [221000, 247000, 189000, 142000, 128000])
    push(ts("09-14", "17:30"), "maya", b)
    merge_pr("US-004", 24, b, "release/0.1.0", ts("09-15", "14:37"))
    b5 = build("US-005", "sam", "09-15", "09:00", ["09:45", "10:30", "11:15", "11:40", "12:10"], "12:10", [203000, 236000, 151000, 117000, 109000])
    push(ts("09-15", "12:11"), "sam", b5)
    larapilot(ts("09-16", "10:00"), "maya", "larapilot:spec-request-changes", "US-005",
              f"--file={payload({'markdown': workspace.US005_FEEDBACK}, 'us005-feedback')}")
    plan("US-005", ts("09-16", "10:30"), "sam", done_until=0)
    switch(ts("09-16", "11:00"), "sam", b5)
    larapilot(ts("09-16", "11:00"), "sam", "larapilot:spec-start", "US-005")
    fix = code_commit(ts("09-16", "11:50"), "sam", "fix(US-005): count the seats inside the transaction (TASK-02)", [])
    for n, at in [(1, "11:52"), (2, "11:54"), (3, "12:00"), (4, "12:05"), (5, "12:16")]:
        larapilot(ts("09-16", at), "sam", "larapilot:task-done", "US-005", f"TASK-{n:02d}", f"--commit={fix}")
    larapilot(ts("09-16", "12:16"), "sam", "larapilot:spec-review", "US-005", "--note=Seats are counted under the row lock now; concurrency test added.")
    usage(ts("09-16", "12:16"), "sam", "US-005", "implementation", "larapilot-implement", 154000, 58, "Rework after review")
    push(ts("09-16", "12:17"), "sam", b5)
    merge_pr("US-005", 31, b5, "release/0.1.0", ts("09-17", "15:07"))

    # Friday 18 — ship 0.1.0
    commit_larapilot(ts("09-18", "16:40"), "priya", "chore(release): 0.1.0 backlog and plans")
    save_state()
    larapilot(ts("09-18", "16:45"), "priya", "larapilot:release-ship", "--semver=0.1.0")
    save_state()  # the ship marked the release shipped: that is the backlog from now on
    usage(ts("09-18", "16:45"), "priya", None, "ship", "larapilot-ship", 94000, 18, "Release 0.1.0")
    schedule = (APP / ".larapilot" / "usage" / "schedule.yaml").read_text()
    milestone = re.search(r"id: (\w+)\n\s+label: '0\.1\.0 internal demo'", schedule).group(1)
    done = {"deadlines": [{"id": milestone, "status": "done", "note": "Shipped a week early"}]}
    larapilot(ts("09-18", "16:50"), "priya", "larapilot:schedule-apply", f"--file={payload(done, 'milestone-done')}")
    push(ts("09-18", "16:46"), "priya", "main", "develop", "v0.1.0")

    # Monday 21 — release 0.2.0 cut; US-009 and US-012 planned
    larapilot(ts("09-21", "09:00"), "priya", "larapilot:release-cut", "--semver=0.2.0", "--no-checkout")
    switch(ts("09-21", "09:00"), "priya", "release/0.2.0")
    push(ts("09-21", "09:01"), "priya", "release/0.2.0")
    for code, who in [("US-009", "luca"), ("US-012", "maya"), ("US-010", "luca")]:
        plan(code, ts("09-21", "11:00"), who)
        usage(ts("09-21", "11:00"), who, code, "planning", "larapilot-plan", 91000, 14)
    larapilot(ts("09-21", "11:30"), "priya", "larapilot:custom-skill-add", "--name=studio-release-notes", f"--file={workspace.custom_skill_file()}")

    b = build("US-009", "luca", "09-22", "09:00", ["09:55", "10:50", "11:45", "12:32"], "12:32", [231000, 252000, 169000, 113000])
    push(ts("09-22", "12:33"), "luca", b)
    merge_pr("US-009", 44, b, "release/0.2.0", ts("09-23", "09:47"))
    b = build("US-012", "maya", "09-23", "10:00", ["10:35", "11:15", "11:38"], "11:38", [118000, 134000, 92000])
    push(ts("09-23", "11:39"), "maya", b)
    merge_pr("US-012", 46, b, "release/0.2.0", ts("09-25", "15:20"))
    plan("US-006", ts("09-25", "16:00"), "sam")
    usage(ts("09-25", "16:00"), "sam", "US-006", "planning", "larapilot-plan", 97000, 15)

    # Monday 28 — US-006 to review; Wednesday 30 — US-010
    b6 = build("US-006", "sam", "09-28", "09:00", ["10:05", "11:20", "12:30", "13:40", "14:31"], "14:35", [214000, 263000, 187000, 141000, 126000])
    push(ts("09-28", "14:36"), "sam", b6)
    b = build("US-010", "luca", "09-30", "09:00", ["09:50", "10:45", "11:40", "12:35", "13:29"], "13:29", [196000, 244000, 228000, 133000, 151000])
    push(ts("09-30", "13:30"), "luca", b)
    merge_pr("US-010", 48, b, "release/0.2.0", ts("10-01", "13:52"))
    larapilot(ts("10-01", "16:10"), "priya", "larapilot:spec-comment", "US-006", "--author=Priya Raman (PO)", f"--message={workspace.US006_COMMENTS[0]}", "--blocks-merge")
    larapilot(ts("10-02", "09:25"), "sam", "larapilot:spec-comment", "US-006", "--author=Sam Okafor (Dev)", f"--message={workspace.US006_COMMENTS[1]}")

    # Monday 5 October — front-desk design, plans of the week
    switch(ts("10-05", "09:00"), "maya", "release/0.2.0")
    workspace.write_mockups(APP, "studio-desk")
    usage(ts("10-05", "09:10"), "maya", None, "analysis", "larapilot-design", 174000, 33, "Front desk and owner screens")
    commit_larapilot(ts("10-05", "09:20"), "maya", "docs(design): front desk and check-in screens")
    push(ts("10-05", "09:21"), "maya", "release/0.2.0")
    for code, who in [("US-007", "maya"), ("US-008", "sam"), ("US-013", "maya"), ("US-011", "luca")]:
        plan(code, ts("10-05", "10:30"), who)
        usage(ts("10-05", "10:30"), who, code, "planning", "larapilot-plan", 84000, 12)
    larapilot(ts("10-05", "11:00"), "maya", "larapilot:settings-set", "--security-provider=hackly")

    # Thursday 8 — US-007 under way, its first task pushed; Friday 9 — US-008 started on Sam's machine
    b7 = build("US-007", "maya", "10-08", "09:00", ["10:00"], None, [139000])
    push(ts("10-08", "10:01"), "maya", b7)
    task_done("US-007", 2, ts("10-08", "11:30"), "maya")
    usage(ts("10-08", "11:30"), "maya", "US-007", "implementation", "larapilot-implement", 162000, 39)
    build("US-008", "sam", "10-09", "14:00", ["16:30"], None, [171000])

    for version, item_title, summary, target in workspace.ROADMAP:
        args = ["larapilot:roadmap-set", f"--semver={version}", f"--title={item_title}", f"--summary={summary}"]
        if target:
            args.append(f"--target={target}")
        larapilot(ts("10-09", "17:00"), "priya", *args)
    for version, code, title, summary in workspace.ROADMAP_ITEMS:
        larapilot(ts("10-09", "17:05"), "priya", "larapilot:roadmap-set", f"--semver={version}", f"--item={code}", f"--title={title}", f"--summary={summary}")
    save_state()
    (TARGET / "work" / "demo-shift").write_text(str(SHIFT.days))
    print("history replayed, dates shifted by", SHIFT.days, "days; HEAD:", git(ts("10-09", "17:10"), "sam", "rev-parse", "--abbrev-ref", "HEAD"))


if __name__ == "__main__":
    main()
