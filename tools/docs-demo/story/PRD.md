# Fernway

> Class bookings for independent fitness studios: a timetable members can book from in two taps, a waitlist that fills itself, and class packs that pay on the spot.

## Elevator Pitch

Independent gyms, yoga and Pilates studios run their timetable on spreadsheets, Instagram stories, and a booking widget that charges a percentage of every class. Fernway gives a studio of 1–5 rooms its own booking site: members see the week, book or join the waitlist, and pay for a class pack with a card. The studio pays a flat monthly fee, never a cut of a booking.

## Vision

A studio owner opens Fernway once a week to set the timetable and once a month to read the numbers. Everything in between — bookings, cancellations, waitlists, reminders, receipts — runs by itself, and a member never has to message the front desk to get into a class.

## User Personas

| Persona | Who | What they need | What they fear |
|---|---|---|---|
| **Nadia — studio owner** | Runs a strength gym in Bristol with three coaches and 140 members. | A full timetable without chasing members on WhatsApp; to know which classes earn their slot. | Paying a marketplace a cut of every booking; members drifting to a chain gym. |
| **Tom — instructor** | Teaches 9 classes a week across two studios. | To see who is booked before he walks in, and to stop the late no-shows. | Teaching to an empty room because people cancelled at the last minute. |
| **Aisha — member** | Books two or three yoga classes a week on her phone, often the evening before. | To book in two taps and to be told when a spot opens in a full class. | Refreshing the page hoping for a spot; losing a paid credit without knowing why. |

## User Journeys

- **J-001 — Book a class.** Aisha opens the studio link, picks Wednesday 18:30, books with one credit of her pack, and gets a confirmation email with the cancellation deadline.
- **J-002 — Join the waitlist.** The 18:30 class is full; Aisha joins at position 3. Two members cancel; she is booked and told by email.
- **J-003 — Run the front desk.** Tom opens the class on a tablet, checks members in by name or QR, and sees who has not arrived.
- **J-004 — Read the month.** Nadia reads attendance by class and revenue by plan, and exports the payments for her accountant.

## Functional Requirements

| Id | Requirement | MoSCoW | Done means |
|---|---|---|---|
| FR-001 | A studio owner signs up, creates the studio, and invites instructors. | Must | An invited instructor can sign in and see their classes. |
| FR-002 | The owner publishes a weekly timetable of classes with a capacity. | Must | Members see the week with the spots left. |
| FR-003 | A member books a class with a credit or an unlimited membership. | Must | The booking is confirmed by email; capacity is never exceeded. |
| FR-004 | A member cancels; inside the late window the credit is used. | Must | The cancellation deadline is shown before booking and in every email. |
| FR-005 | Class packs and unlimited memberships are sold by card. | Must | Stripe Checkout; the membership is active on payment. |
| FR-006 | A full class has a waitlist that promotes members automatically. | Must | A cancellation books the first member on the list and tells them. |
| FR-007 | Front-desk check-in by name or QR. | Should | Attendance is recorded per booking. |
| FR-008 | Reminders before a class, by email; SMS on opt-in. | Should | No reminder for a cancelled booking. |
| FR-009 | Owner dashboard: attendance and revenue; CSV export. | Should | Figures match the payments of the month. |
| FR-010 | Invoices and receipts for every payment. | Could | A PDF receipt per payment, VAT shown when registered. |

## Non-Functional Requirements

- **NFR-001** — Booking answers in under 300 ms at p95 with 50 members booking the same class at once; no overbooking under concurrency.
- **NFR-002** — WCAG 2.2 AA on the member pages; usable one-handed on a phone.
- **NFR-003** — UK GDPR: members can export and delete their data; card data never touches Fernway (Stripe Checkout).
- **NFR-004** — Every money movement is traceable to a Stripe event.

## Domain Model

Studio → Instructors, Class types → Class sessions → Bookings, Waitlist entries. Membership plans (pack or unlimited) → Memberships → Payments. A booking consumes one credit of a pack membership, or none on an unlimited one.

## MVP Scope

- **0.1.0 — internal demo (25 Sep):** sign-up, instructors, timetable, booking.
- **0.2.0 — beta for 10 studios (16 Oct):** waitlist, late cancellation, check-in, packs and card payments, confirmation and reminder emails.
- **0.3.0 — public launch (6 Nov):** invoices, SMS reminders, owner dashboard, CSV export.

Out of scope for now: a marketplace of studios, native apps, payroll for instructors.

## Technical Architecture

- **Stack:** Laravel 13, Livewire 3 monolith; PostgreSQL 17 in production, SQLite locally.
- **Payments:** Laravel Cashier on Stripe Checkout; webhooks are idempotent.
- **Queues:** database queue; the waitlist runs on its own `waitlist` queue.
- **Hosting:** a VPS managed with Cipi; Laravel Herd locally.
- **Design system:** Nordic SaaS for the back office; a warm custom theme per studio for the member pages.

## Risks & Assumptions

- Studios will move off their current widget only if the import of members is painless — **assumed**, to test with the 10 beta studios.
- Late-cancellation rules differ per studio: the window is a setting, not a constant.
- Stripe fees are passed to the studio; Fernway's price stays flat.
