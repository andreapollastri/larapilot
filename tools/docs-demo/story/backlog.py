"""The Fernway backlog: 16 stories in 5 epics, their plans, and the files each task commits.

Numbers the documentation cites come from here: 76 story points, 47 of them done in 8 stories;
3 TODO (10 SP), 2 PLANNED (6 SP), 2 IN PROGRESS (8 SP), 1 REVIEW (5 SP); 55 planned tasks.
"""

EPICS = {
    "EP-001": ("Studios and accounts", "A studio owner signs up, sets up the studio, and invites instructors.", "2026-09-25"),
    "EP-002": ("Timetable and bookings", "Members book, cancel, and get in from the waitlist without a message to the front desk.", "2026-10-16"),
    "EP-003": ("Memberships and payments", "Class packs and memberships sold by card, with receipts.", "2026-11-06"),
    "EP-004": ("Notifications", "Members are told what they need to know, when they need it.", "2026-11-06"),
    "EP-005": ("Reporting", "The owner reads attendance and revenue, and hands the month to the accountant.", "2026-11-06"),
}

# who: Maya Chen, Luca Moretti, Sam Okafor (developers); Priya Raman (product owner)
M, L, S = "Maya Chen", "Luca Moretti", "Sam Okafor"


def spec(code, title, epic, priority, points, release, persona, want, so, demo, criteria, traces, blocked=None, sprint=None, tasks=None):
    return dict(code=code, title=title, epic=epic, priority=priority, points=points, release=release, persona=persona,
                want=want, so=so, demo=demo, criteria=criteria, traces=traces, blocked=blocked, sprint=sprint, tasks=tasks or [])


def task(title, kind, who, hours, files=(), commit=None, body=None):
    return dict(title=title, type=kind, assignee=who, estimate_hours=hours, files=list(files), commit=commit, body=body)


MIG = "database/migrations/"

SPECS = [
    spec("US-001", "Project bootstrap, CI and coding standards", "EP-001", "HIGH", 3, "0.1.0", "a developer of Fernway",
         "a Laravel 13 project with CI, Pint, and Larastan from the first commit",
         "every pull request is checked the same way",
         "A pull request with a style error fails CI; the same pull request fixed passes.",
         ["Laravel 13 skeleton with Pint and Larastan level 6", "GitHub Actions runs Pint, Larastan, and Pest on every pull request", "Users, cache, and jobs tables migrate on SQLite and PostgreSQL"],
         "FR-001 (Must)", sprint=1, tasks=[
             task("Laravel 13 skeleton with Pint and Larastan", "Impl", M, 2, ["composer.json", "composer.lock", "pint.json", "README.md"], "chore(US-001): Laravel 13 skeleton, Pint and Larastan"),
             task("CI workflow on GitHub Actions", "Impl", M, 1.5, [".github/workflows/ci.yml"], "ci(US-001): Pint, Larastan and Pest on every pull request"),
             task("Users and framework tables", "Impl", M, 2, [MIG + "0001_01_01_000000_create_users_table.php", MIG + "0001_01_01_000001_create_cache_table.php", MIG + "0001_01_01_000002_create_jobs_table.php", "app/Models/User.php"], "feat(US-001): users, cache and jobs tables"),
         ]),
    spec("US-002", "Studio owner sign-up and studio profile", "EP-001", "CRITICAL", 5, "0.1.0", "a studio owner",
         "to sign up and create my studio with its city, timezone, and colour",
         "my members land on a booking page that looks like my studio",
         "Nadia signs up, creates Oak & Iron Strength in Bristol, and sees its booking page in her brand colour.",
         ["Sign up with email and password; the email is verified", "Create the studio: name, slug, city, timezone, brand colour", "The late-cancellation window defaults to 120 minutes and can be changed", "A slug already taken is refused with a suggestion"],
         "J-001 · FR-001 (Must)", sprint=1, tasks=[
             task("Studios schema", "Impl", L, 2.5, [MIG + "2026_09_09_101500_create_studios_and_instructors_tables.php", "app/Models/Studio.php"], "feat(US-002): studios schema"),
             task("Owner sign-up and studio creation", "Impl", L, 4, [], "feat(US-002): owner sign-up and studio creation"),
             task("Studio profile settings", "Impl", L, 2.5, [], "feat(US-002): studio profile and late-cancel window"),
             task("Tests for sign-up and profile", "Test", L, 2, [], "test(US-002): sign-up and studio profile"),
         ]),
    spec("US-003", "Instructor invitations and roles", "EP-001", "HIGH", 5, "0.1.0", "a studio owner",
         "to invite my instructors by email and give them a role",
         "they see their own classes and nothing of the money",
         "Nadia invites Tom; Tom accepts, signs in, and sees his classes but not the revenue.",
         ["An invitation by email that expires after 7 days", "Roles: owner, manager, instructor", "An instructor sees their classes and the members booked", "A removed instructor loses access at the next request"],
         "FR-001 (Must)", sprint=1, tasks=[
             task("Instructors model and invitations", "Impl", L, 2.5, ["app/Models/Instructor.php"], "feat(US-003): instructors and invitations"),
             task("Invitation email and acceptance", "Impl", L, 3.5, [], "feat(US-003): invitation email and acceptance"),
             task("Role policies", "Impl", L, 2.5, [], "feat(US-003): owner, manager and instructor policies"),
             task("Tests for invitations and roles", "Test", L, 2, [], "test(US-003): invitations and roles"),
         ]),
    spec("US-004", "Weekly class timetable", "EP-002", "HIGH", 8, "0.1.0", "a member",
         "to see the week of my studio with the spots left in each class",
         "I can pick a class without asking anyone",
         "Aisha opens the Tidal Yoga link and sees this week, each class with its instructor and the spots left.",
         ["The week from Monday, grouped by day, in the studio's timezone", "Each class shows the instructor, the room, and the spots left", "Filters by class type and by instructor", "A cancelled class is shown struck through, not hidden"],
         "J-001 · FR-002 (Must)", sprint=1, tasks=[
             task("Class types and sessions schema", "Impl", M, 4, [MIG + "2026_09_15_143000_create_class_types_and_sessions_tables.php", "app/Models/ClassType.php", "app/Models/ClassSession.php"], "feat(US-004): class types and sessions schema"),
             task("Timetable query and controller", "Impl", M, 5, ["app/Http/Controllers/TimetableController.php", "routes/web.php@timetable"], "feat(US-004): timetable query and controller"),
             task("Timetable page", "Impl", M, 4, ["resources/views/timetable.blade.php"], "feat(US-004): timetable page"),
             task("Recurring sessions and scheduled tasks", "Impl", M, 3, ["app/Providers/FernwayServiceProvider.php", "app/Console/Commands/SendReminders.php", "app/Console/Commands/PruneWaitlists.php", "app/Jobs/GenerateMonthlyInvoices.php"], "feat(US-004): recurring sessions and scheduled tasks"),
             task("Tests for the timetable", "Test", M, 2, ["tests/Feature/TimetableTest.php"], "test(US-004): timetable"),
         ]),
    spec("US-005", "Member books a class", "EP-002", "CRITICAL", 8, "0.1.0", "a member",
         "to book a class with one credit in two taps",
         "my place is held the moment I tap",
         "Aisha books Wednesday 18:30 with one credit; the 20th booking of a 20-place class is refused.",
         ["Book with a credit of a pack or an unlimited membership", "Capacity is never exceeded, also with 50 members booking at once", "A member cannot book the same class twice", "Cancel before the late window gives the credit back"],
         "J-001 · FR-003 (Must) · NFR-001", sprint=1, tasks=[
             task("Bookings schema", "Impl", S, 2.5, [MIG + "2026_09_17_110000_create_bookings_table.php", "app/Models/Booking.php"], "feat(US-005): bookings schema"),
             task("BookClass action with a row lock", "Impl", S, 4, ["app/Actions/BookClass.php", "app/Exceptions/ClassFullException.php", "app/Jobs/SendClassReminder.php"], "feat(US-005): BookClass action with a row lock"),
             task("Booking endpoints", "Impl", S, 3, ["app/Http/Controllers/BookingController.php", "routes/web.php@bookings"], "feat(US-005): booking endpoints"),
             task("Cancel a booking", "Impl", S, 2, ["app/Actions/CancelBooking.php"], "feat(US-005): cancel a booking"),
             task("Tests for booking under concurrency", "Test", S, 2, ["tests/Feature/BookClassTest.php"], "test(US-005): booking and capacity under concurrency"),
         ]),
    spec("US-006", "Waitlist with automatic promotion", "EP-002", "HIGH", 5, "0.2.0", "a member",
         "to join the waitlist of a full class and be moved in automatically",
         "I do not have to refresh the page hoping for a spot",
         "The 18:30 class is full; Aisha joins at position 3; two members cancel; Aisha is booked and told by email.",
         ["Join and leave the waitlist; position is shown", "A cancellation promotes the first member in a queued job", "Promotion inside the late-cancellation window asks the member to confirm within 30 minutes", "The promoted member gets an email; the studio sees the move in the activity log"],
         "J-002 · FR-006 (Must)", blocked="US-005", sprint=2, tasks=[
             task("Waitlist schema and join/leave", "Impl", S, 3, [MIG + "2026_09_28_160000_create_waitlist_entries_table.php", "app/Models/WaitlistEntry.php", "app/Http/Controllers/WaitlistController.php", "routes/web.php@waitlist"], "feat(US-006): waitlist schema and join/leave"),
             task("PromoteWaitlist job on cancellation", "Impl", S, 4, ["app/Jobs/PromoteWaitlist.php"], "feat(US-006): PromoteWaitlist job on cancellation"),
             task("Confirm window inside the late window", "Impl", S, 3, [], "feat(US-006): confirm window inside the late window"),
             task("Promotion email", "Impl", S, 2.5, ["app/Mail/WaitlistPromoted.php"], "feat(US-006): promotion email"),
             task("Tests for promotion and the confirm window", "Test", S, 2, ["tests/Feature/CancelBookingTest.php"], "test(US-006): promotion and confirm window"),
         ]),
    spec("US-007", "Late cancellation rules", "EP-002", "HIGH", 3, "0.2.0", "a studio owner",
         "a cancellation inside the late window to use the class credit",
         "last-minute no-shows stop costing me a full class",
         "Aisha cancels 45 minutes before a class with a 120-minute window: the credit is used and the email says why.",
         ["The window is a studio setting, in minutes", "Inside the window the credit is used; outside it is given back", "The deadline is shown before booking and in every email", "Owners can waive a late cancellation by hand"],
         "FR-004 (Must)", sprint=3, tasks=[
             task("Late-cancel fields on bookings", "Impl", M, 1.5, [MIG + "2026_10_06_100000_add_late_cancel_fee_to_bookings_table.php"], "feat(US-007): late-cancel fields on bookings"),
             task("Cancellation policy service", "Impl", M, 2.5, [], "feat(US-007): cancellation policy service"),
             task("Credit refund rules", "Impl", M, 2, []),
             task("Deadline in the emails", "Impl", M, 1.5, []),
             task("Tests for the late window", "Test", M, 1.5, []),
         ]),
    spec("US-008", "Check-in at the front desk", "EP-002", "MEDIUM", 5, "0.2.0", "an instructor",
         "to check members in by name or QR on a tablet",
         "I know who came and who did not",
         "Tom opens the 18:30 class on the tablet, checks in 14 members, and sees the 2 who did not arrive.",
         ["The class list with photo initials and booking status", "Check in by tap or by scanning the member's QR", "No-shows are marked when the class starts", "Attendance is recorded per booking"],
         "J-003 · FR-007 (Should)", sprint=3, tasks=[
             task("Check-in action and check-ins schema", "Impl", S, 2, [MIG + "2026_10_06_163000_create_check_ins_table.php", "app/Actions/CheckInMember.php", "app/Http/Controllers/CheckInController.php", "routes/web.php@checkin"], "feat(US-008): check-in action and check-ins schema"),
             task("Front-desk class list", "Impl", S, 3, []),
             task("QR codes for members", "Impl", S, 2, []),
             task("No-show marking", "Impl", S, 1.5, []),
             task("Tests for check-in", "Test", S, 1.5, []),
         ]),
    spec("US-009", "Class packs and memberships", "EP-003", "HIGH", 8, "0.2.0", "a member",
         "to buy a 5- or 10-class pack or an unlimited month",
         "I pay once and book in two taps",
         "Aisha buys a 10-class pack; her credits show 10 and drop to 9 when she books.",
         ["Plans: packs of N credits valid for D days, and unlimited monthly", "Credits are consumed by a booking and given back by an early cancellation", "An expired pack cannot book", "The studio sets its own plans and prices"],
         "FR-005 (Must)", sprint=2, tasks=[
             task("Membership plans and memberships schema", "Impl", L, 3.5, [MIG + "2026_09_22_093000_create_membership_plans_and_memberships_tables.php", "app/Models/MembershipPlan.php", "app/Models/Membership.php"], "feat(US-009): membership plans and memberships schema"),
             task("Credits consumed and given back", "Impl", L, 4, [], "feat(US-009): credits consumed and given back"),
             task("Plans settings for the studio", "Impl", L, 4, [], "feat(US-009): plans settings for the studio"),
             task("Tests for credits and expiry", "Test", L, 2.5, [], "test(US-009): credits and expiry"),
         ]),
    spec("US-010", "Card payments with Stripe Checkout", "EP-003", "CRITICAL", 8, "0.2.0", "a member",
         "to pay for a pack by card",
         "my credits are there the moment I pay",
         "Aisha pays £95 for a 10-class pack in Stripe Checkout and lands back with 10 credits.",
         ["A Stripe Checkout session per purchase, in GBP", "The membership is active when the webhook says paid", "Webhooks are idempotent: a replayed event changes nothing", "A receipt email after payment"],
         "FR-005 (Must) · NFR-003 · NFR-004", blocked="US-009", sprint=2, tasks=[
             task("Payments schema and Cashier setup", "Impl", L, 3, [MIG + "2026_09_30_150000_create_payments_table.php", "app/Models/Payment.php"], "feat(US-010): payments schema and Cashier setup"),
             task("Checkout session per purchase", "Impl", L, 3.5, ["app/Jobs/SyncStripeCustomer.php", "app/Exceptions/PaymentWebhookException.php"], "feat(US-010): checkout session per purchase"),
             task("Idempotent webhook handler", "Impl", L, 3.5, ["app/Http/Controllers/StripeWebhookController.php", "routes/web.php@stripe"], "feat(US-010): idempotent webhook handler"),
             task("Receipt email", "Impl", L, 2, [], "feat(US-010): receipt email"),
             task("Webhooks and replays", "Test", L, 2.5, ["tests/Feature/StripeWebhookTest.php"], "test(US-010): webhooks and replays"),
         ]),
    spec("US-011", "Invoices and receipts", "EP-003", "MEDIUM", 3, "0.3.0", "a studio owner",
         "a PDF receipt for every payment, with VAT when I am registered",
         "my accountant has a document for every pound",
         "A paid 10-class pack produces a PDF receipt numbered in sequence, with VAT shown for a VAT-registered studio.",
         ["One PDF receipt per payment, numbered in sequence per studio", "VAT shown when the studio is VAT-registered", "Members download their receipts from their account", "A monthly invoice run for the owner"],
         "FR-010 (Could)", sprint=3, tasks=[
             task("Receipt numbering per studio", "Impl", L, 2.5, []),
             task("PDF receipt template", "Impl", L, 3, []),
             task("Receipts in the member account", "Impl", L, 2, []),
             task("Tests for numbering and VAT", "Test", L, 1.5, []),
         ]),
    spec("US-012", "Booking confirmation email", "EP-004", "HIGH", 2, "0.2.0", "a member",
         "an email when I book, with the class, the place, and the cancellation deadline",
         "I know exactly what I booked and until when I can cancel for free",
         "Lily books Mobility Flow and receives the email with the time, the instructor, the room, and the deadline.",
         ["Sent when a booking is confirmed", "Shows class, time, instructor, room, and the free-cancellation deadline", "Readable on a phone; plain-text part included"],
         "J-001 · FR-003 (Must)", blocked="US-005", sprint=2, tasks=[
             task("BookingConfirmed mailable", "Impl", M, 1.5, ["app/Mail/BookingConfirmed.php"], "feat(US-012): BookingConfirmed mailable"),
             task("Email template", "Impl", M, 2, ["resources/views/mail/booking-confirmed.blade.php"], "feat(US-012): email template"),
             task("Tests for the confirmation email", "Test", M, 1, [], "test(US-012): confirmation email"),
         ]),
    spec("US-013", "Class reminder two hours before", "EP-004", "HIGH", 3, "0.2.0", "a member",
         "a reminder two hours before my class",
         "I do not forget and lose a credit",
         "Aisha gets a reminder at 16:30 for her 18:30 class; a cancelled booking gets none.",
         ["A queued reminder two hours before the class", "No reminder for a cancelled booking or a cancelled class", "Members can turn reminders off"],
         "FR-008 (Should)", sprint=3, tasks=[
             task("Reminder mailable", "Impl", M, 1.5, []),
             task("Scheduling and skipping cancelled bookings", "Impl", M, 2, []),
             task("Tests for reminders", "Test", M, 1, []),
         ]),
    spec("US-014", "SMS reminders (opt-in)", "EP-004", "LOW", 3, "0.3.0", "a member",
         "to receive reminders by SMS if I opt in",
         "I see them even when I do not open my email",
         "Aisha opts in to SMS; her next reminder arrives as a text.",
         ["Opt-in per member, off by default", "Sent through the studio's SMS credits", "STOP replies opt the member out"],
         "FR-008 (Should)"),
    spec("US-015", "Owner dashboard: attendance and revenue", "EP-005", "MEDIUM", 5, "0.3.0", "a studio owner",
         "attendance by class and revenue by plan for the month",
         "I know which classes earn their slot",
         "Nadia sees that Tuesday 07:00 HIIT is half empty and that packs bring 70% of revenue.",
         ["Attendance by class type, day, and hour", "Revenue by plan, net of Stripe fees", "Compare with the previous month"],
         "J-004 · FR-009 (Should)"),
    spec("US-016", "CSV export for accountants", "EP-005", "LOW", 2, "0.3.0", "a studio owner",
         "to export the payments of a month as CSV",
         "my accountant can import them without retyping",
         "Nadia exports September and her accountant imports the CSV into Xero.",
         ["One row per payment with date, member, plan, amount, fee, net", "Columns named as Xero expects"],
         "J-004 · FR-009 (Should)"),
]


def body(s, status):
    lines = [
        f"**Epic:** {s['epic']} | **Priority:** {s['priority']} | **Points:** {s['points']} | **Status:** {status}",
        f"**Blocked by:** {s['blocked'] or '-'}",
        f"**Release:** {s['release']}",
        f"**Traces to:** {s['traces']}",
        "",
        "**User Story**",
        f"As {s['persona']},",
        f"I want {s['want']},",
        f"so that {s['so']}.",
        "",
        "**Demonstrates**",
        s["demo"],
        "",
        "**Acceptance Criteria**",
    ]
    lines += [f"- [ ] {c}" for c in s["criteria"]]
    return "\n".join(lines) + "\n"


def plan_payload(s):
    tasks = []
    for i, t in enumerate(s["tasks"], start=1):
        files = "\n".join(f"- {f}" for f in t["files"]) or "- (see description)"
        tasks.append({
            "id": f"TASK-{i:02d}",
            "title": t["title"],
            "type": t["type"],
            "status": "TODO",
            "assignee": t["assignee"],
            "estimate_hours": t["estimate_hours"],
            "dependencies": [] if i == 1 else [f"TASK-{i - 1:02d}"],
            "body": t["body"] or (
                f"## Description\n{t['title']} for {s['code']}.\n\n## Files Involved\n{files}\n\n"
                f"## Git Deliverables\n- Commit: {t['commit'] or s['code'].lower() + ': ' + t['title'].lower()}\n\n"
                f"## Completion Criteria\n- [ ] Pint and Larastan clean\n- [ ] Pest green"
            ),
        })
    plan_body = (
        f"## Technical Solution\n{s['title']}: {s['want']}.\n\n"
        "## Git & Branching\n- Mode: GITFLOW, release mode on\n- `feature/" + s["code"] + "-*` from the release branch; pull request into it\n\n"
        "## Test Data Strategy\n- Factories for every model touched\n- Demo volumes: 4 studios, 60 members, three weeks of classes\n\n"
        "## Test Strategy\n- Bar: NORMAL — Pest feature tests for the happy path and the failure modes"
    )
    return {"plan_body": plan_body, "tasks": tasks}
