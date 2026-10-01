## Angular Playbook _(Joe)_

Defaults for Angular code, used where the frontend repository's rules and the `observed` lines of `frontend-scan` are silent. Order of authority: the repository's rules, then `observed` and `generators.defaults`, then this file. Write for `framework.major`; never use an API newer than it.

### What each version adds

| Major | Available — use it where the code already does |
| --- | --- |
| ≤ 13 | NgModules only; `*ngIf` / `*ngFor`; constructor injection; RxJS for state. |
| 14 | Standalone components (preview); `inject()` in constructors and field initializers; typed reactive forms. |
| 15 | Standalone stable; functional guards, resolvers, and interceptors (`withInterceptors`); class guards deprecated. |
| 16 | Signals (`signal`, `computed`, `effect`) in preview; `takeUntilDestroyed`; required inputs; `DestroyRef`. |
| 17 | Control flow `@if` / `@for` (with `track`) / `@switch` and `@defer`; new CLI apps are standalone; `input()` from 17.1, `model()` from 17.2, `output()` from 17.3. |
| 18 | Control flow stable; experimental zoneless; `@let`; fallback content for `ng-content`. |
| 19 | Components are standalone by default — omit `standalone: true`, write `standalone: false` for a declared one; signal inputs, queries and `model()` stable; `linkedSignal`, `resource()` experimental. |
| 20 | `provideZonelessChangeDetection()` (no longer "Experimental"); `effect`, `linkedSignal`, `toSignal` stable; `*ngIf` / `*ngFor` / `*ngSwitch` deprecated; the style guide drops the `.component` / `.service` suffixes for new projects — an existing project keeps the suffix `observed` shows. |
| 21+ | New projects are zoneless and test with Vitest by default; Signal Forms experimental. |

### Components

- Change detection: `generators.defaults` decides; otherwise `OnPush`. In a zoneless app, state that drives a template is a signal (or goes through `async`), never a plain field mutated later.
- Inputs and outputs: the form `observed` counts most. In code on signal inputs, `input()` / `input.required()`, `output()`, `model()` for two-way binding, `viewChild()` for queries.
- Injection: `inject()` when `observed` shows it, constructor parameters otherwise. Never both in one class.
- Templates: built-in control flow on 17+ when the project uses it; every `@for` has a `track` on a stable id. `@defer` for heavy content below the fold. `NgOptimizedImage` (`ngSrc`) for images.
- Selectors start with the `prefix` of the project. No `::ng-deep` in new code; style through the design tokens and the theme of the UI kit (`libraries.ui`).
- Smart and presentational: a page (container) talks to services and stores; a presentational component gets inputs and emits outputs, and lives in a `ui` library in Nx.

### State and data

- Local state: signals. Streams, events, debounced input, HTTP: RxJS, bridged with `toSignal` / `toObservable`. No manual `subscribe` without `takeUntilDestroyed` or the `async` pipe; no nested subscriptions — `switchMap`, `concatMap`, `exhaustMap` by intent.
- Shared state: the library `libraries.state` names (NgRx Store, NgRx Signal Store, NGXS, Elf, a signal service). Never introduce another one.
- HTTP lives in services (a `data-access` library in Nx), never in components: `HttpClient` from `provideHttpClient(withInterceptors([...]))`, typed responses, errors mapped once in an interceptor, auth header from an interceptor. A generated client (`api_client.generated`) replaces hand-written services.
- Laravel API: Sanctum SPA auth needs `withCredentials` and the `XSRF-TOKEN` cookie (`withXsrfConfiguration`); token auth sends `Authorization: Bearer`. Map Laravel validation errors (422 `errors` object) onto the form controls.

### Routing and forms

- Lazy routes: `loadComponent` / `loadChildren` (standalone) or lazy modules (NgModule code). Guards and resolvers as functions on 15+.
- Typed reactive forms (`FormGroup<…>`, `nonNullable`); validators next to the form; every control has a label and an error message tied with `aria-describedby`. Signal Forms only where the project already uses them.

### SSR, i18n, accessibility

- `@angular/ssr` present: no direct `window`, `document`, or `localStorage` — `isPlatformBrowser`, `afterNextRender`, or an injection token.
- i18n through the library `libraries.i18n` names: `@angular/localize` (`i18n` attributes, `$localize`), Transloco, or ngx-translate keys. No hard-coded user-facing string when the project translates.
- Angular CDK `a11y` (`FocusTrap`, `LiveAnnouncer`) for dialogs and announcements; WCAG 2.2 AA per `ux-1.md`.

### Tests

- The runner of `libraries.testing`: Jest (`jest-preset-angular`), Karma + Jasmine, or Vitest (`@angular/build:unit-test`, `@analogjs/vitest-angular`). Never mix runners in one project.
- `TestBed` with the standalone component in `imports`; `fixture.componentRef.setInput()` for signal inputs; `provideHttpClientTesting()` with `HttpTestingController`; Spectator, ng-mocks, or Testing Library when the project has them.
- Test behaviour through the template (what the user sees and does), not private methods.

### Nx

- Library types and tags: `feature`, `ui`, `data-access`, `util` with `scope:` / `type:` tags; a new library copies the tags of its neighbours. `@nx/enforce-module-boundaries` failures are design errors — never silence them.
- `nx g @nx/angular:component|library|service …` with `--dry-run` first; the team's `generators.local` first.
- `commands.affected` before commit; `nx show project <name> --json` to see a target the scan could not.
