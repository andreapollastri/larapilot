#!/usr/bin/env python3
"""A weekend of Fernway traffic through the real routes of the app, so the Logs, Mail, Queue, and Dumps
of the dashboard are what the app itself wrote.

    python3 traffic.py <target-dir> <port>

The app must be served on <port> (seed.sh does it). Each request carries the time of the story in
X-Demo-Now (the prepend sets Carbon's clock from it) and its member in X-Demo-Member (the kit's
DemoServiceProvider signs that member in for the request). Queue workers run with DEMO_NOW.
"""
import json
import os
import sqlite3
import subprocess
import sys
import urllib.error
import urllib.request
from datetime import datetime, timedelta
from pathlib import Path

KIT = Path(__file__).resolve().parent.parent
TARGET = Path(sys.argv[1]).resolve()
PORT = sys.argv[2]
SHIFT = timedelta(days=int((TARGET / "work" / "demo-shift").read_text().strip() or 0))
DB = sqlite3.connect(TARGET / "fernway" / "database" / "database.sqlite")
DB.row_factory = sqlite3.Row
stats = {}
events = []  # (kind, story time) of every mail and dump, in the order the app kept them


def at(day, hm):
    return (datetime.fromisoformat(f"2026-{day}T{hm}:00+00:00") + SHIFT)


def request(method, path, when, member=None):
    req = urllib.request.Request(f"http://127.0.0.1:{PORT}{path}", method=method, data=b"" if method != "GET" else None)
    req.add_header("X-Demo-Now", when.isoformat())
    req.add_header("Accept", "application/json" if method != "GET" else "text/html")
    req.add_header("User-Agent", "Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) Safari/604.1")
    if member:
        req.add_header("X-Demo-Member", str(member))
    try:
        with urllib.request.urlopen(req) as r:
            code = r.status
    except urllib.error.HTTPError as e:
        code = e.code
    stats[f"{method} {code}"] = stats.get(f"{method} {code}", 0) + 1
    if method == "POST" and path.endswith("/bookings") and code == 201:
        events.append(("mail", when))
    if method == "GET" and path.endswith("/timetable") and code == 200:
        events.extend([("dumps", when), ("dumps", when)])
    return code


def artisan(when, *args):
    env = dict(os.environ, DEMO_NOW=when.isoformat(), MAIL_MAILER="array")
    subprocess.run([str(KIT / "bin" / "tb"), str(TARGET), *args], env=env, check=True, capture_output=True)


def q(sql, *params):
    return DB.execute(sql, params).fetchall()


def sessions_from(when, studio=None):
    sql = """select s.id, s.capacity, s.starts_at, t.studio_id,
             (select count(*) from bookings b where b.class_session_id = s.id and b.status = 'confirmed') booked
             from class_sessions s join class_types t on t.id = s.class_type_id
             where s.starts_at > ? and s.cancelled = 0 order by s.starts_at"""
    return [dict(r) for r in q(sql, when.strftime("%Y-%m-%d %H:%M:%S"))]


def free_member(session_id, skip=()):
    rows = q("select id from users where email like '%@members.example' and id not in (select user_id from bookings where class_session_id = ?) "
             "and id not in (select user_id from waitlist_entries where class_session_id = ?) order by (id * 37 + ?) % 61", session_id, session_id, session_id)
    return next(r["id"] for r in rows if r["id"] not in skip)


def main():
    t = at("10-09", "07:55")
    request("GET", "/studios/tidal-yoga/timetable", t)

    # Friday to Sunday: bookings into next week's classes, spread over the days.
    upcoming = [s for s in sessions_from(at("10-12", "06:00")) if s["capacity"] - s["booked"] >= 4]
    lily = q("select id from users where email = 'lily.parker@members.example'")[0]["id"]
    times = [at("10-09", "08:12")] + [at("10-09", "08:20") + timedelta(minutes=97 * i) for i in range(21)]
    for i, when in enumerate(times):
        session = upcoming[(i * 5) % len(upcoming)]
        member = lily if i == 0 else free_member(session["id"])
        request("POST", f"/sessions/{session['id']}/bookings", when, member)

    # A class with three spots left: the booking that leaves two warns the studio.
    three = next(s for s in sessions_from(at("10-12", "06:00")) if s["capacity"] - s["booked"] == 3)
    request("POST", f"/sessions/{three['id']}/bookings", at("10-10", "15:03"), free_member(three["id"]))

    # A full class: seven members try anyway on Saturday morning.
    full = next(s for s in sessions_from(at("10-12", "06:00")) if s["booked"] >= s["capacity"] and q("select 1 from waitlist_entries where class_session_id = ?", s["id"]))
    for i in range(7):
        request("POST", f"/sessions/{full['id']}/bookings", at("10-10", "08:41") + timedelta(minutes=23 * i + i * i), free_member(full["id"]))

    # A double tap on the same class.
    twice = upcoming[3]
    member = free_member(twice["id"])
    request("POST", f"/sessions/{twice['id']}/bookings", at("10-10", "18:58"), member)
    request("POST", f"/sessions/{twice['id']}/bookings", at("10-10", "18:58").replace(second=1), member)

    # Saturday 12:15 classes: check-ins at the door, and two late cancellations of the 18:30 ones.
    saturday = [dict(r) for r in q("select b.id, b.user_id, s.starts_at from bookings b join class_sessions s on s.id = b.class_session_id "
                                   "where s.starts_at = ? and b.status = 'confirmed' order by b.id", at("10-10", "12:15").strftime("%Y-%m-%d %H:%M:%S"))]
    for i, b in enumerate(saturday[:6]):
        request("POST", f"/bookings/{b['id']}/check-in", at("10-10", "12:02") + timedelta(minutes=2 * i), b["user_id"])
    monday = [dict(r) for r in q("select b.id, b.user_id from bookings b join class_sessions s on s.id = b.class_session_id "
                                 "where s.starts_at = ? and b.status = 'confirmed' order by b.id", at("10-12", "07:00").strftime("%Y-%m-%d %H:%M:%S"))]
    for i, b in enumerate(monday[:2]):
        request("DELETE", f"/bookings/{b['id']}", at("10-12", "06:10") + timedelta(minutes=17 * i), b["user_id"])

    # Cancellations in good time; one of a full class promotes its waitlist.
    later = [dict(r) for r in q("select b.id, b.user_id, b.class_session_id from bookings b join class_sessions s on s.id = b.class_session_id "
                                "where s.starts_at > ? and b.status = 'confirmed' and s.id != ? order by s.starts_at desc limit 4", at("10-14", "00:00").strftime("%Y-%m-%d %H:%M:%S"), full["id"])]
    for i, b in enumerate(later[:3]):
        request("DELETE", f"/bookings/{b['id']}", at("10-10", "15:10") + timedelta(hours=2 * i, minutes=7 * i), b["user_id"])
    for i in range(2):
        request("POST", f"/sessions/{full['id']}/waitlist", at("10-10", "21:05") + timedelta(minutes=9 * i), free_member(full["id"]))
    victim = q("select id, user_id from bookings where class_session_id = ? and status = 'confirmed' order by id limit 1", full["id"])[0]
    request("DELETE", f"/bookings/{victim['id']}", at("10-11", "07:40"), victim["user_id"])
    artisan(at("10-11", "07:41"), "queue:work", "--queue=waitlist", "--once", "--stop-when-empty")
    events.extend([("dumps", at("10-11", "07:41")), ("mail", at("10-11", "07:41"))])

    # Another full class loses a member on Sunday evening: its promotion waits for a worker.
    other = next(s for s in sessions_from(at("10-12", "06:00")) if s["id"] != full["id"] and s["booked"] >= s["capacity"] and q("select 1 from waitlist_entries where class_session_id = ?", s["id"]))
    victim = q("select id, user_id from bookings where class_session_id = ? and status = 'confirmed' order by id limit 1", other["id"])[0]
    request("DELETE", f"/bookings/{victim['id']}", at("10-11", "19:45"), victim["user_id"])

    # The Stripe customer sync of two new members fails: the key is not live yet.
    for i, member in enumerate([lily, free_member(full["id"])]):
        artisan(at("10-11", "17:42") + timedelta(minutes=3 * i), "fernway:demo-dispatch", str(member))
        artisan(at("10-11", "17:42") + timedelta(minutes=3 * i, seconds=4), "queue:work", "--once", "--stop-when-empty", "--tries=1")

    request("GET", "/studios/tidal-yoga/timetable", at("10-11", "20:28"))
    (TARGET / "work" / "traffic-events.json").write_text(json.dumps([(k, w.isoformat()) for k, w in events]))
    print("traffic:", json.dumps(stats))


if __name__ == "__main__":
    main()
