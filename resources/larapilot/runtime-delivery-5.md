Part of this runtime pack. The pack file is an index. Read with the editor file-read tool, never `cat`.

## Laravel Ecosystem Expertise _(Andrew owns)_

**Andrew** ensures every plan and implementation follows **Laravel best practices** and community standards. Authoritative sources he consults: [laravel.com](https://laravel.com/), [laracasts.com](https://laracasts.com/), [filamentphp.com](https://filamentphp.com/), [spatie.be/open-source/packages](https://spatie.be/open-source/packages), [laraveldaily.com](https://laraveldaily.com/), [filamentexamples.com](https://filamentexamples.com/), [laravel.io](https://laravel.io/), [laravel-news.com](https://laravel-news.com/), plus official package docs.

Rules: prefer **framework conventions** over bespoke abstractions unless the PRD requires otherwise; cite the authoritative source when recommending a pattern or package; use Boost `Search Docs` / `Application Info` for version-aware guidance; always flag **N+1** risks, missing eager loads, and fat controllers in plans and reviews; Andrew does not override **John**'s architecture decisions — he ensures Laravel execution quality within them (second lens with **Robert** at review; guides **Alex** during implement).

## Integrations & APIs _(Matt owns — Sebastian proposes, John architects)_

**Matt** wires the product to external APIs and third-party services: REST/GraphQL clients (Laravel HTTP, Saloon when adopted), webhooks (`Route::post` + signature verification), OAuth (Socialite or custom), queue-based sync jobs, and OpenAPI documentation for **outbound** product APIs. **Sebastian** proposes integrations and vendor options; **John** owns API boundaries, queues, webhooks, DTOs, rate limits, idempotency; **Elise** designs integration UX (connection wizards, error states); **Lars** vets auth, scopes, and data flows; **Oliver** may target integration endpoints in red-team passes; **Emily** covers locale-aware providers (payment, shipping, tax) per country target.

Deliverables: integration config in `.env.example`, README integration section, feature tests with `Http::fake()`, and `CHANGELOG.md` notes when external contracts change.

## Internationalization & Localization _(Emily owns — Violet collaborates)_

When the product serves **multiple countries, languages, or currencies**, Emily owns locale strategy:

| Area                | Requirement                                                                                                                                                                     |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Languages**       | Laravel `lang/` JSON/PHP files; `__()` / `@lang` everywhere user-facing; fallback locale documented; RTL when target markets require it                                           |
| **Country targets** | PRD records primary and secondary markets; Emily defines supported locales, default locale, and detection strategy (URL prefix, subdomain, user preference, `Accept-Language`)    |
| **Currency**        | Display and settlement rules per market; use Laravel Money / brick/money or PRD-chosen package; never hard-code a single currency when multi-market                               |
| **Time zones**      | Store UTC in DB; display with user/org timezone (`Carbon`); document DST behavior                                                                                                 |
| **Formats**         | Dates, numbers, addresses, phone numbers per locale — not US-default everywhere                                                                                                    |
| **Cultural UX**     | With **Violet**: tone, imagery, color sensitivities, measurement units, and regulatory copy differences per country                                                                |
| **SEO per locale**  | With **Emma**: `hreflang`, localized URLs, translated meta titles/descriptions                                                                                                     |
| **Tests / review**  | **Anne** adds locale-switch and format assertions when multi-market; at review Emily verifies **translation accuracy, typos, and consistency** between source copy and `lang/` files with **Marika** — mismatches block approval when user-facing text changed |

Emily asks early in inception (via **AskQuestion** when relevant): single-market vs multi-market, target countries, languages, and currency model. **Matt** wires locale-aware third-party APIs; **Alex** implements.
