## React Playbook _(Joe)_

Defaults for React code — Vite SPAs, Next.js, React Router / Remix, React Native — used where the frontend repository's rules and the `observed` lines of `frontend-scan` are silent. Order of authority: the repository's rules, then `observed`, then this file. Write for `framework.version`; never use an API newer than it.

### What each version adds

| Version | Available — use it where the code already does |
| --- | --- |
| React 16.8–17 | Hooks; function components. Class components only where `observed` counts them. |
| React 18 | Automatic batching, `useId`, `useTransition` / `useDeferredValue`, streaming SSR with Suspense; `createRoot`. |
| React 19 | Actions, `useActionState`, `useOptimistic`, `use()`, `ref` as a prop (no `forwardRef` in new code), `<title>` / `<meta>` in components, `<Context>` as provider. |
| React Compiler | When `babel-plugin-react-compiler` is configured, do not add `useMemo` / `useCallback` / `memo` by hand. |
| Next.js 13.4–14 | App Router: Server Components by default, `'use client'` at the boundary, Server Actions (14). Pages Router projects stay on it. |
| Next.js 15 | `params`, `searchParams`, `cookies()`, `headers()` are async; `fetch` and GET route handlers are not cached by default. |
| Next.js 16 | Turbopack is the default bundler; `middleware.ts` becomes `proxy.ts`; caching is opt-in through Cache Components (`'use cache'`). |
| React Router 7 / Remix | Remix merged into React Router 7 framework mode: `loader` / `action` per route, `useLoaderData`, typed routes. |

### Components

- Function components; the export style `observed` counts most (named or default). One component per file, file named as `observed` shows (PascalCase or kebab-case).
- Props typed with a `type` / `interface` in TypeScript; no `any`, no `React.FC` unless the code uses it.
- Derive values during render instead of copying them into state. Effects only synchronise with something outside React (subscriptions, the DOM, timers) — never to fetch data when a data library exists, never to compute state.
- Stable `key`s from ids, never array indexes for lists that change.
- Next.js App Router: keep components on the server until they need state, effects, or browser APIs; push `'use client'` to the smallest leaf. Server-only code imports `server-only`.

### State and data

- Server state belongs to the data library of `libraries.data` (TanStack Query, RTK Query, SWR, Apollo) — never copied into a global store. Query keys follow the project's factory.
- Client state: local `useState` / `useReducer` first, then the store `libraries.state` names (Redux Toolkit, Zustand, Jotai, …). Never introduce another one.
- A generated client (`api_client.generated`) is the only way to call the API. Hand-written calls go through the project's one HTTP instance (axios / fetch wrapper) with the base URL from env, typed from the OpenAPI schemas.
- Laravel API: Sanctum SPA auth needs `credentials: 'include'` (axios `withCredentials`, `withXSRFToken`) and a call to `/sanctum/csrf-cookie` first; token auth sends `Authorization: Bearer`. Map 422 `errors` onto the fields of the form.

### Forms, routing, i18n

- Forms with the library of `libraries.forms` (React Hook Form, TanStack Form, Formik) and its schema validator (Zod, Yup, Valibot); React 19 Actions where the project uses them. Every field has a label and an error tied with `aria-describedby`.
- Routing with the router the project has (React Router, TanStack Router, the Next.js file router); lazy routes for heavy pages.
- i18n through `libraries.i18n` (i18next, react-intl, next-intl); no hard-coded user-facing string when the project translates.

### Styling and accessibility

- The styling `observed` and `libraries.styling` show: Tailwind classes (with `cn` / `clsx` / `cva` when present), CSS modules, styled-components, Emotion, vanilla-extract. No new approach.
- UI kit components (`libraries.ui`: shadcn, MUI, Chakra, Mantine, Radix, …) before custom ones; shadcn components are added with its CLI, not copied by hand.
- Semantic elements before ARIA; keyboard support for every interactive element; `eslint-plugin-jsx-a11y` errors are bugs. WCAG 2.2 AA per `ux-1.md`.

### Tests

- The runner of `libraries.testing` (Vitest or Jest) with Testing Library: query by role and label, act with `user-event`, assert what the user sees. MSW for network when the project has it.
- E2E with the project's Playwright or Cypress, only for the journeys the task names.

### React Native / Expo

- Expo Router or React Navigation as the project has it; platform files (`.ios.tsx`, `.android.tsx`) only where they already exist; no web-only API in shared code.
