# Larapilot Runtime — Upgrades, part 2

Read by **`larapilot-laravel-upgrade`**, and by **`larapilot-php-upgrade`** when a package must move a major. Index: `.larapilot/runtime-upgrade.md`.

## Laravel Upgrades

### The ladder

Laravel ships a major every year (Q1); each gets bug fixes for 18 months and security fixes for 2 years. `larapilot:stack` → `laravel.support` says where the project stands; `upgrade-check --laravel=N` lists the steps with the PHP range of each and the guide of each.

| Major | PHP | Bug fixes until | Security fixes until |
| --- | --- | --- | --- |
| 10 | 8.1 – 8.3 | 2024-08-06 | 2025-02-04 |
| 11 | 8.2 – 8.4 | 2025-09-03 | 2026-03-12 |
| 12 | 8.2 – 8.5 | 2026-08-13 | 2027-02-24 |
| 13 | 8.3 – 8.5 | Q3 2027 | 2028-03-17 |

The guide of each major is the source of truth: Boost **`Search Docs`** (it answers for the installed version — run it again after the Composer step), or `https://laravel.com/docs/{N}.x/upgrade`. Walk it item by item and record in the report which items applied here and which did not. The notes below are what most often bites; they do not replace the guide.

### 10 → 11

- **The slim skeleton is optional.** An existing application keeps its `app/Http/Kernel.php`, `app/Console/Kernel.php`, and providers: do not restructure it to the new layout.
- **Column changes without Doctrine DBAL** — `->change()` now keeps only the attributes it restates: a column made `nullable()` or given a `default()` or `comment()` loses them unless the call says them again. Review every migration that calls `->change()`; they run again on a fresh install and in the test suite.
- **Packages stop loading their own migrations** — Sanctum 4, Passport 12, Cashier 15, Telescope 5: publish them (`php artisan vendor:publish --tag=sanctum-migrations`, …) so a fresh database still gets the tables.
- **Rate limits in seconds** — limiter decays and the throttling middleware constructors take seconds where they took minutes.
- **Carbon 3 is allowed** (2 still works): `diffIn*()` returns floats and may be negative.
- **Password rehash on login** — a custom user provider implements `rehashPasswordIfRequired()`.
- **SQLite 3.35+**; a dedicated `mariadb` driver exists (`DB_CONNECTION=mariadb`).

### 11 → 12

- **Carbon 3 is required.**
- **`HasUuids` writes UUIDv7** (ordered). Models that must keep v4 use `HasVersion4Uuids`.
- **`image` validation excludes SVG** unless the rule says `image:allow_svg`.
- **The `local` disk**, when `config/filesystems.php` does not define it, roots at `storage/app/private`.
- Breeze and Jetstream get no new features and the new starter kits are for new applications: an existing application keeps its scaffolding.
- PHPUnit 11 and Pest 3 come with it.

### 12 → 13

- **PHP 8.3+.** Run `/larapilot-php-upgrade` first when the project is on 8.2.
- The release keeps breaking changes to a minimum. Request forgery protection becomes `PreventRequestForgery`, origin-aware and compatible with the CSRF token: an application that customised `VerifyCsrfToken` checks the guide.
- Read the 13.x guide in full through Boost `Search Docs` before the Composer step: what is not listed here is there.

### Tools

- **`composer why-not laravel/framework N.0`** and the dry run name what blocks; **`composer outdated --direct`** shows how far each direct dependency is behind.
- **Rector** with `driftingly/rector-laravel` applies the mechanical part of a major (the Laravel set of the target). Dry run, read the diff, commit it alone as `refactor:`.
- **Laravel Shift** (laravelshift.com) opens a pull request per major for a fee, on the user's account: propose it for a large or old application; never run it for the user.
- Boost **`Application Info`** (installed versions), **`Last Error`** and **`Browser Logs`** (after the gates), **`Tinker`** (a quick check of a changed API).

## Package Playbooks

A dependency that moves a major is a small upgrade of its own: read its guide, apply it inside the same step, and name it in the commit. The verdicts of `upgrade-check` say which ones move: `bump` (a new constraint — often a major), `update` (fits the constraint), `blocker`, `abandoned`, `private`.

### Admin panels

- **Filament** — every major ships an upgrade package and script: `composer require filament/upgrade:"^N" --with-all-dependencies --dev`, run `vendor/bin/filament-vN` (it rewrites code and prints the Composer commands to run), then `php artisan filament:upgrade`, then remove `filament/upgrade`. Follow `https://filamentphp.com/docs/{N}.x/upgrade-guide`. A custom theme is rebuilt with Vite; v4 moved themes to Tailwind CSS 4. **Third-party Filament plugins** are the usual blockers: check each one's release for the target major before starting, and treat one with no release as a `blocker`.
- **Nova** — a commercial package served from `nova.laravel.com`: `upgrade-check` marks it `private`. The license credentials must be in `auth.json` on every machine and in CI. Check Nova's release notes for the target Laravel; a Nova major has its own upgrade guide; run `php artisan nova:publish` after the update. **Third-party Nova packages** (fields, tools, cards) block more often than Nova itself.
- **Backpack, Orchid, MoonShine** — each has an upgrade guide per major; their paid add-ons sit in private repositories like Nova.

### Frontend stacks

- **Livewire** — 2 → 3: `php artisan livewire:upgrade` does the mechanical part; then `wire:model` is deferred by default (`.live` for the old behaviour), `emit` becomes `dispatch`, Alpine ships inside Livewire (remove a second copy). 3 → 4: follow its guide. Volt and Flux move with Livewire.
- **Inertia** — `inertiajs/inertia-laravel` and the client adapter (`@inertiajs/vue3`, `@inertiajs/react`, `@inertiajs/svelte`) move **together, same major**; follow the Inertia upgrade guide. Ziggy, when present, follows.
- **Vite** — `laravel-vite-plugin` follows the Vite major; run the build after. A Tailwind CSS major (`npx @tailwindcss/upgrade`) is a change of its own: not part of a Laravel upgrade unless the user asks.
- **The frontend companion** — when the upgrade changes what the API answers (resource shape, pagination, auth), the external frontend must know: under handoff mode write a brief (`larapilot:frontend-brief`), otherwise plan the frontend task (**Frontend Companion**, `frontend.md`).

### First-party packages

Sanctum, Passport, Fortify, Horizon, Telescope, Pulse, Cashier, Scout, Socialite, Octane, Reverb, Pennant: each repository has an `UPGRADE.md`; read the section for the major the dry run moves to. Publish what it says (migrations, config, `horizon:publish`, `telescope:publish`). Breeze and Jetstream scaffolding is code of the application: only their packages move.

### Spatie and the ecosystem

Spatie packages keep an `UPGRADING.md` (laravel-permission, laravel-medialibrary, laravel-backup, laravel-activitylog, laravel-data, …): a major often renames a config key, adds a migration, or changes a method signature. Tenancy packages (`stancl/tenancy`, `spatie/laravel-multitenancy`) touch every request: run the tenant-aware tests twice.

### Tests and quality

- Pest and PHPUnit majors follow the Laravel major: let the dry run say which.
- `nunomaduro/larastan` is abandoned: move to `larastan/larastan`. Larastan 3 runs on PHPStan 2: regenerate the baseline only for errors the upgrade introduced, and say so in the report.
- Pint, Collision, Dusk, and Pest plugins follow; Dusk needs the matching ChromeDriver (`php artisan dusk:chrome-driver --detect`).

### Abandoned and private packages

- **Abandoned** — the lock records it (`upgrade-check` → `abandoned`, `larapilot:sbom` → `abandoned`). Move to the suggested replacement inside the upgrade when it is a drop-in; otherwise it is a follow-up spec.
- **Private** — not on Packagist (Nova, Spark, a company Satis): the check cannot see their releases. Ask the user, or read the vendor's release notes; never guess compatibility.
