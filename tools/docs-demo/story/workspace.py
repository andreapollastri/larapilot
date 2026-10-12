"""Everything of Fernway's .larapilot/ that is not the backlog: materials, mockups, wording, comments."""
import tempfile
from pathlib import Path

CHOICES = [
    "--project-kind=SaaS web application",
    "--business-model=SaaS, flat monthly fee per studio",
    "--success-signal=10 studios on the beta by the end of 0.2.0",
    "--kill-condition=Fewer than 3 paying studios after 0.3.0",
    "--budget-sensitivity=medium",
    "--frontend-topology=Livewire 3 monolith",
    "--admin-panel=Custom Livewire back office",
    "--data-store=PostgreSQL 17 (SQLite locally)",
    "--deadlines=0.2.0 beta by 16 October",
    "--local-dev=Laravel Herd",
    "--deploy-platform=VPS via Cipi",
]

DECISIONS = [
    ("pricing", "Flat monthly fee per studio", "Six owners refused a commission per booking in the pricing interviews.", ("09-07", "11:30")),
    ("booking concurrency", "Row lock on the session while seats are counted", "NFR-001: no overbooking with 50 members booking at once.", ("09-07", "11:35")),
    ("design system", "Nordic SaaS for the back office, a warm theme for members", "The member pages carry the studio's brand; the back office stays quiet.", ("09-07", "11:40")),
]

ECONOMICS = [
    "--country=GB", "--regime=ltd", "--currency=GBP", "--hourly-rate=72", "--margin=25", "--product-model=saas",
    "--price-monthly=49", "--churn=3", "--growth=8", "--support-per-customer=2.5", "--payment-fee=1.5",
    "--overhead-monthly=420", "--maintenance=15", "--team-size=3",
]

ROADMAP_INTRO = "Fernway gives an independent studio its own booking site: members book in two taps, a full class fills itself from the waitlist, and the studio pays a flat fee, never a cut. This is what is out, what we are building now, and what comes next."

# version, public title, summary, target
ROADMAP = [
    ("0.1.0", "Book a class in two taps", "Studios set up their timetable and members book with a credit.", None),
    ("0.2.0", "Beta for ten studios", "Waitlists that fill themselves, fair late cancellations, check-in at the front desk, and packs paid by card.", "October 2026"),
    ("0.3.0", "Public launch", "Receipts, SMS reminders, and the numbers of the month for owners.", "November 2026"),
]

ROADMAP_ITEMS = [
    ("0.1.0", "US-001", "A solid foundation", "Automated checks on every change, from day one."),
    ("0.2.0", "US-006", "A waitlist that fills itself", "When someone cancels, the first person waiting is booked and told."),
    ("0.2.0", "US-007", "Fair late cancellations", "Each studio sets its own window; members see the deadline before they book."),
    ("0.2.0", "US-010", "Pay by card", "Class packs and memberships through Stripe Checkout."),
    ("0.3.0", "US-015", "The month at a glance", "Attendance by class and revenue by plan, for owners."),
]

US005_FEEDBACK = """- app/Actions/BookClass.php:18 — the seats are counted before the transaction opens: two members booking the last seat at once both get in. Count under `lockForUpdate()` inside the transaction (NFR-001).
- tests/Feature/BookClassTest.php — add the concurrency case: 21 bookings on a 20-place class end with 20 confirmed."""

US006_COMMENTS = [
    "Inside the late window the promotion email must say the 30-minute deadline in its **subject** — in the beta interviews members read the subject on the lock screen and miss the body. Blocking until the subject carries it.",
    "Fixed on the branch: the subject reads “Confirm by 18:05 — a spot opened in Vinyasa Flow”. Priya, can you check and close?",
]

SKILL = """---
name: studio-release-notes
description: Writes the release notes studio owners read, from the specs of a shipped release. Use after /larapilot-ship, or when asked for "what's new for studios".
---

# Studio release notes

Read the release from `php artisan larapilot:release-list` and the public wording from
`php artisan larapilot:roadmap-show`. Write `docs/release-notes/{version}.md` for studio owners:

- One short paragraph per story, in the words of the public roadmap — never a spec code, a branch, or a person.
- What the owner has to do, if anything (a setting to choose, an email to send to members).
- No dates that are not on the public roadmap.

Ask Priya to read it before it is sent to the beta studios.
"""


def custom_skill_file():
    path = Path(tempfile.gettempdir()) / "fernway-studio-release-notes.md"
    path.write_text(SKILL)
    return str(path)


def write_materials(app: Path):
    lp = app / ".larapilot"
    files = {
        "brand/logo.svg": '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#2f6b55"/><path d="M22 46V18h22v6H29v6h13v6H29v10z" fill="#f3efe8"/></svg>\n',
        "brand/brand-guide.md": "# Fernway brand\n\n- Fern green `#2f6b55`, sand `#f3efe8`, ink `#1d2a24`, clay `#c9774b` for warnings.\n- Type: the system UI stack; numbers tabular.\n- Voice: plain, warm, never salesy. \"Book\", not \"Reserve your spot now!\".\n",
        "client-materials/beta-studios.md": "# Beta studios (0.2.0)\n\n| Studio | City | Rooms | Members |\n|---|---|---|---|\n| Tidal Yoga | Brighton | 2 | 210 |\n| Oak & Iron Strength | Bristol | 1 | 140 |\n| Northside Pilates | Leeds | 3 | 320 |\n| Harbour Boxing Club | Cardiff | 1 | 95 |\n",
        "client-materials/owner-interviews.md": "# Owner interviews — summary\n\nSix owners, 30 minutes each, 1–4 September.\n\n- All six pay a booking widget a cut of 1.5–5% per class; four call it \"the tax\".\n- Late no-shows cost each studio 2–4 full classes a month.\n- Waitlists are run by hand on WhatsApp in five of six studios.\n",
        "client-materials/competitor-widgets.md": "# Booking widgets the beta studios use today\n\n- A marketplace app: commission per booking, members see other studios.\n- A generic scheduling tool: no class packs, no waitlist.\n- Spreadsheets and Instagram stories.\n",
        "legacy/oak-and-iron-timetable.csv": "day,time,class,coach,capacity\nMon,07:00,HIIT,Wendy Davis,16\nMon,18:30,Strength Foundations,Tom Barker,12\nTue,07:00,Mobility Flow,Wendy Davis,16\nWed,18:30,Barbell Club,Tom Barker,10\nThu,09:30,Mobility Flow,Wendy Davis,16\nSat,10:00,Open Gym,,24\n",
    }
    for rel, text in files.items():
        p = lp / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text)


# ---------------------------------------------------------------- mockups

CSS = """
:root{--fern:#2f6b55;--fern-2:#3f8a6e;--sand:#f3efe8;--card:#fff;--ink:#1d2a24;--muted:#6b746f;--line:#e6e1d8;--clay:#c9774b}
*{box-sizing:border-box}body{margin:0;font:15px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;color:var(--ink);background:var(--sand)}
header{display:flex;align-items:center;gap:14px;padding:14px 28px;background:var(--card);border-bottom:1px solid var(--line)}
.logo{width:34px;height:34px;border-radius:9px;background:var(--fern);color:#fff;display:grid;place-items:center;font-weight:800}
header strong{font-size:19px}nav{margin-left:auto;display:flex;gap:22px}nav a{color:var(--ink);text-decoration:none}nav a.on{color:var(--fern);font-weight:700}
.avatar{margin-left:26px;width:36px;height:36px;border-radius:50%;background:#dce9e2;color:var(--fern);display:grid;place-items:center;font-weight:700;font-size:13px}
main{max-width:1080px;margin:0 auto;padding:28px}h1{font-size:26px;margin:0 0 4px}.sub{color:var(--muted);margin:0 0 18px}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}.chip{padding:5px 12px;border-radius:999px;background:var(--card);border:1px solid var(--line);font-size:13px}.chip.on{background:var(--fern);color:#fff;border-color:var(--fern)}
.week{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}.day h3{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:0 0 8px}
.cls{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:10px 12px;margin-bottom:10px}.cls b{display:block}.cls small{color:var(--muted)}
.bar{height:5px;border-radius:3px;background:#e9e4da;margin:8px 0 4px;overflow:hidden}.bar i{display:block;height:100%;background:var(--fern-2)}
.tag{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;background:#e3efe8;color:var(--fern)}.tag.full{background:#f7e3d8;color:#9a4b23}.tag.booked{background:var(--fern);color:#fff}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px}.center{max-width:520px;margin:26px auto;text-align:center}
.btn{display:block;width:100%;padding:13px;border:0;border-radius:10px;background:var(--fern);color:#fff;font-weight:700;font-size:15px;margin-top:16px}.btn.ghost{background:transparent;color:var(--fern);border:1px solid var(--line)}
.big{font-size:44px;font-weight:800;color:var(--fern);margin:6px 0}.note{background:#f7f3ec;border-radius:10px;padding:12px;color:var(--muted);font-size:13px;margin-top:14px;text-align:left}
.grid2{display:grid;grid-template-columns:1.4fr 1fr;gap:18px}.hero{height:170px;border-radius:14px;background:linear-gradient(135deg,var(--fern),#6aa88c);color:#fff;display:flex;align-items:flex-end;padding:16px;font-size:22px;font-weight:800}
dl{display:grid;grid-template-columns:110px 1fr;gap:8px;margin:16px 0}dt{color:var(--muted)}dd{margin:0}
.packs{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.pack{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px}.pack.best{border:2px solid var(--fern)}.price{font-size:28px;font-weight:800;margin:10px 0}
.check{display:inline-grid;place-items:center;width:48px;height:48px;border-radius:50%;background:#e3efe8;color:var(--fern);font-size:24px;font-weight:800}
table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden}td,th{padding:11px 14px;border-bottom:1px solid var(--line);text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}.kpi{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px}.kpi small{color:var(--muted)}.kpi b{display:block;font-size:24px}
"""


def page(title, nav_on, body, studio="Tidal Yoga · Brighton", initials="AK", items=("Timetable", "My bookings", "Packs")):
    nav = "".join(f'<a href="#" class="{"on" if n == nav_on else ""}">{n}</a>' for n in items)
    return f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{title}</title><style>{CSS}</style></head>
<body><header><div class="logo">f</div><strong>{studio}</strong><nav>{nav}</nav><div class="avatar">{initials}</div></header>
<main>{body}</main></body></html>
"""


def timetable_body():
    days = [("Mon 12 Oct", [("Sunrise Hatha", "07:00 · 60 min · Grace Holloway", 70, "7 spots"), ("Vinyasa Flow", "18:30 · 60 min · Tom Barker", 100, "Full · waitlist")]),
            ("Tue 13 Oct", [("Yin & Breath", "12:15 · 75 min · Nia Osei", 45, "11 spots"), ("Power Yoga", "18:00 · 60 min · Tom Barker", 85, "Booked")]),
            ("Wed 14 Oct", [("Sunrise Hatha", "07:00 · 60 min · Grace Holloway", 60, "8 spots"), ("Vinyasa Flow", "18:30 · 60 min · Nia Osei", 90, "2 left")]),
            ("Thu 15 Oct", [("Power Yoga", "07:30 · 60 min · Tom Barker", 75, "5 spots"), ("Yin & Breath", "19:00 · 75 min · Grace Holloway", 100, "Full · waitlist")]),
            ("Fri 16 Oct", [("Vinyasa Flow", "12:15 · 60 min · Nia Osei", 40, "12 spots"), ("Sunrise Hatha", "18:00 · 60 min · Grace Holloway", 55, "9 spots")])]
    cols = []
    for day, classes in days:
        cards = ""
        for name, meta, fill, tag in classes:
            cls = "full" if "Full" in tag else ("booked" if tag == "Booked" else "")
            cards += f'<div class="cls"><b>{name}</b><small>{meta}</small><div class="bar"><i style="width:{fill}%"></i></div><span class="tag {cls}">{tag}</span></div>'
        cols.append(f'<div class="day"><h3>{day}</h3>{cards}</div>')
    chips = "".join(f'<span class="chip{" on" if i == 0 else ""}">{c}</span>' for i, c in enumerate(["All classes", "Vinyasa Flow", "Yin & Breath", "Power Yoga", "Evenings", "Tom Barker"]))
    return f'<p class="sub">Week of 12 October</p><h1>This week at Tidal Yoga</h1><p class="sub">Book in two taps. Free cancellation until two hours before.</p><div class="chips">{chips}</div><div class="week">{"".join(cols)}</div>'


MEMBER_APP = {
    "index.html": ("Timetable", "Timetable", timetable_body()),
    "class.html": ("Vinyasa Flow", "Timetable", '<p class="sub">← Timetable</p><div class="grid2"><div class="card"><div class="hero">Vinyasa Flow</div><h2>A steady, breath-led flow for every level</h2><p class="sub">Sun salutations, standing balances, and a long cool-down. Bring water; mats are provided.</p><dl><dt>When</dt><dd>Wednesday 14 October, 18:30–19:30</dd><dt>With</dt><dd>Nia Osei</dd><dt>Where</dt><dd>Studio A, 14 Kings Road</dd><dt>Spots</dt><dd>2 of 20 left</dd></dl></div><div class="card"><b>Book this class</b><p class="sub">10-class pack · 6 credits left, valid until 9 January</p><button class="btn">Book with 1 credit</button><div class="note">Free cancellation until 16:30 on the day. After that the credit is used and your spot goes to the waitlist.</div><button class="btn ghost">Buy a pack instead</button></div></div>'),
    "booked.html": ("Booked", "My bookings", '<div class="card center"><div class="check">✓</div><h1>You\'re booked</h1><p class="sub">Vinyasa Flow · Wednesday 14 October, 18:30 with Nia Osei</p><div class="note">We sent the details to aisha.k@example.com. You\'ll get a reminder at 16:30. Free cancellation until 16:30.</div><button class="btn">Back to the timetable</button><p class="sub" style="margin-top:12px">Add to calendar · Cancel booking</p></div>'),
    "waitlist.html": ("Waitlist", "Timetable", '<p class="sub">← Timetable</p><div class="card center"><p class="sub">Monday 12 October, 18:30 · Vinyasa Flow · Tom Barker</p><h1>This class is full</h1><p class="sub">Join the waitlist and we\'ll move you in if a spot opens.</p><p class="sub">You would be</p><div class="big">#3</div><p class="sub">on the waitlist</p><button class="btn">Join the waitlist</button><div class="note">If someone cancels before 16:30 you\'re booked automatically and we email you. Later than that, you get 30 minutes to confirm.</div></div>'),
    "packs.html": ("Packs", "Packs", '<h1>Class packs &amp; memberships</h1><p class="sub">Pay once by card, book in two taps.</p><div class="packs"><div class="pack"><b>5-class pack</b><p class="sub">Valid 60 days</p><div class="price">£52</div><button class="btn ghost">Buy</button></div><div class="pack best"><b>10-class pack</b><p class="sub">Valid 90 days · best value</p><div class="price">£95</div><button class="btn">Buy with card</button></div><div class="pack"><b>Unlimited monthly</b><p class="sub">Renews every 30 days</p><div class="price">£119</div><button class="btn ghost">Subscribe</button></div></div>'),
}
MEMBER_README = """# Member app — booking flow

The member pages of a studio, in the studio's warm theme (Design system: custom, see PRD).

| Screen | File | Stories |
|---|---|---|
| Timetable (entry) | index.html | US-004 |
| Class | class.html | US-005 |
| Booked | booked.html | US-005, US-012 |
| Waitlist | waitlist.html | US-006 |
| Packs | packs.html | US-009 |
"""

FRONT_DESK = {
    "index.html": ("Front desk", "Classes", '<h1>Vinyasa Flow · 18:30</h1><p class="sub">Studio A · Tom Barker · 16 of 20 booked</p><div class="kpis"><div class="kpi"><small>Booked</small><b>16</b></div><div class="kpi"><small>Checked in</small><b>14</b></div><div class="kpi"><small>Not arrived</small><b>2</b></div><div class="kpi"><small>Waitlist</small><b>3</b></div></div><table><tr><th>Member</th><th>Plan</th><th>Status</th><th></th></tr><tr><td>Aisha Khan</td><td>10-class pack</td><td><span class="tag booked">Checked in 18:21</span></td><td></td></tr><tr><td>Lily Parker</td><td>Unlimited</td><td><span class="tag booked">Checked in 18:24</span></td><td></td></tr><tr><td>Owen Hughes</td><td>5-class pack</td><td><span class="tag">Booked</span></td><td><b style="color:var(--fern)">Check in</b></td></tr><tr><td>Priti Shah</td><td>10-class pack</td><td><span class="tag">Booked</span></td><td><b style="color:var(--fern)">Check in</b></td></tr></table>'),
    "owner.html": ("This month", "Reports", '<h1>September at Oak &amp; Iron</h1><p class="sub">Attendance and revenue of the month</p><div class="kpis"><div class="kpi"><small>Classes</small><b>118</b></div><div class="kpi"><small>Attendance</small><b>81%</b></div><div class="kpi"><small>Revenue</small><b>£6,240</b></div><div class="kpi"><small>Late cancellations</small><b>23</b></div></div><table><tr><th>Class</th><th>Slots</th><th>Attendance</th><th>Revenue</th></tr><tr><td>Strength Foundations</td><td>16</td><td>94%</td><td>£1,480</td></tr><tr><td>Mobility Flow</td><td>24</td><td>88%</td><td>£1,320</td></tr><tr><td>HIIT · Tue 07:00</td><td>4</td><td>52%</td><td>£210</td></tr></table>'),
}
FRONT_README = """# Front desk and owner screens

Back office, Nordic SaaS look.

| Screen | File | Stories |
|---|---|---|
| Front desk (entry) | index.html | US-008 |
| Owner month | owner.html | US-015 |
"""


def write_mockups(app: Path, flow: str):
    target = app / ".larapilot" / "mockups" / flow
    target.mkdir(parents=True, exist_ok=True)
    member = flow == "member-app"
    screens, readme, studio, initials = (MEMBER_APP, MEMBER_README, "Tidal Yoga · Brighton", "AK") if member else (FRONT_DESK, FRONT_README, "Oak & Iron Strength · Bristol", "TB")
    items = ("Timetable", "My bookings", "Packs") if member else ("Classes", "Members", "Reports")
    for name, (title, nav_on, body) in screens.items():
        (target / name).write_text(page(title, nav_on, body, studio, initials, items))
    (target / "README.md").write_text(readme)
