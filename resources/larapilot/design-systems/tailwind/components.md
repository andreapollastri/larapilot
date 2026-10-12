# Tailwind CSS: components

Patterns for Tailwind mockups, written as **real Tailwind v4 utilities**. There is no component CSS: each
pattern is a class list you copy into the mockup and, later, into a Blade component (`<x-button>`,
`<x-badge>` …). Every class below is defined in `tokens.css` with Tailwind's own value, and every pattern is
drawn in `html/components.html` in its variants and states.

Conventions used everywhere:

- **Neutral** `gray`, **primary** `indigo` (600 in light, 500/400 in dark), status colours `green`,
  `yellow`, `red`, `sky`. Change the brand by swapping `indigo-*` for another family (README → "Recolour").
- **Dark mode** is always written next to the light class: `bg-white dark:bg-gray-900`,
  `text-gray-900 dark:text-white`, borders `dark:border-white/10`.
- **Focus**: interactive elements show `focus-visible:outline-2 focus-visible:outline-offset-2
  focus-visible:outline-indigo-600`; inputs switch to `focus:outline-2 focus:-outline-offset-2`.
- **Icons**: Heroicons v2, inline, `aria-hidden="true"`: 24 px outline in navigation, 20 px solid
  ("mini") inside buttons and inputs, 16 px ("micro") inside badges.

---

## 1. App shell (sidebar layout)

**Purpose** the frame of every signed-in screen. **Anatomy** a fixed `lg:w-72` sidebar (logo, primary nav,
saved segments, settings, profile), a sticky `h-16` top bar (menu button on phones, search, notifications,
profile), `main` with `py-10` and `px-4 sm:px-6 lg:px-8`. **States** active item `bg-gray-50 text-indigo-600`
+ `aria-current="page"`; hover `hover:bg-gray-50 hover:text-indigo-600`. Below `lg` the sidebar is hidden
and the top bar shows the menu button and the logo (no JS in mockups; Alpine opens a drawer in the app).

```html
<div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
  <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 dark:border-white/10 dark:bg-gray-900">
    <div class="flex h-16 shrink-0 items-center"><!-- logo --></div>
    <nav aria-label="Main" class="flex flex-1 flex-col">
      <ul role="list" class="-mx-2 space-y-1">
        <li><a href="#" aria-current="page" class="group flex gap-x-3 rounded-md bg-gray-50 p-2 text-sm/6 font-semibold text-indigo-600 dark:bg-white/5 dark:text-white"><!-- icon size-6 -->Overview</a></li>
        <li><a href="#" class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white">Customers</a></li>
      </ul>
    </nav>
  </div>
</div>
<div class="lg:pl-72">
  <header class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-xs sm:gap-x-6 sm:px-6 lg:px-8 dark:border-white/10 dark:bg-gray-900 dark:shadow-none">
    <button type="button" class="-m-2.5 p-2.5 text-gray-700 lg:hidden dark:text-gray-400" aria-label="Open sidebar"><!-- bars-3 --></button>
  </header>
  <main id="main" class="py-10"><div class="px-4 sm:px-6 lg:px-8"><!-- page --></div></main>
</div>
```

**Do** keep one primary nav list and put settings at the bottom (`mt-auto`). **Don't** nest a second
sidebar; use tabs (§ 13) for sub-sections.

## 2. Page heading

**Purpose** title, context and the page's actions. **Anatomy** optional breadcrumb, `h1`
`text-2xl/7 font-bold sm:text-3xl sm:tracking-tight`, a meta row of `text-sm text-gray-500` items with mini
icons, actions on the right (`md:flex md:items-center md:justify-between`). **Variants** with breadcrumb,
with meta, with a bottom border (`border-b border-gray-200 pb-5`).

```html
<div class="md:flex md:items-center md:justify-between">
  <div class="min-w-0 flex-1">
    <h1 class="text-2xl/7 font-bold text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight dark:text-white">Revenue overview</h1>
    <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400"><span aria-hidden="true" class="mr-2 size-2 rounded-full bg-green-500"></span>Stripe synced 4 minutes ago</p>
  </div>
  <div class="mt-5 flex gap-x-3 md:mt-0 md:ml-4">
    <button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">Export</button>
    <button type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">New report</button>
  </div>
</div>
```

**Do** one primary button per heading. **Don't** put more than three actions; move the rest into a dropdown (§ 15).

## 3. Buttons

**Purpose** actions. **Anatomy** `rounded-md px-3 py-2 text-sm font-semibold shadow-xs`. **Variants**
primary `bg-indigo-600 text-white hover:bg-indigo-500`; secondary (white)
`bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50`; soft
`bg-indigo-50 text-indigo-600 hover:bg-indigo-100`; danger `bg-red-600 text-white hover:bg-red-500`; text
link `text-sm/6 font-semibold`. **Sizes** xs `rounded-sm px-2 py-1 text-xs`, sm `rounded-sm px-2 py-1`,
md `px-2.5 py-1.5`, lg `px-3 py-2`, xl `px-3.5 py-2.5`. **States** hover, `focus-visible:outline-2`,
`disabled:cursor-not-allowed disabled:opacity-50`. Icon buttons need `aria-label`.

```html
<button type="button" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
  <svg class="-ml-0.5 size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><!-- heroicons mini plus --></svg>
  New report
</button>
<button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">Cancel</button>
<button type="button" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 dark:bg-red-500 dark:shadow-none dark:hover:bg-red-400">Delete customer</button>
```

**Do** use `<a … role="button">` only when it navigates. **Don't** use red for anything but destructive actions.

## 4. Badges

**Purpose** status and counts. **Anatomy** `inline-flex items-center rounded-md px-2 py-1 text-xs
font-medium inset-ring` + a colour trio. **Variants** flat, with dot (`gap-x-1.5` + a 6 px SVG circle
`fill-green-500`), pill count (`rounded-full px-2.5 py-0.5`), trend (`rounded-full` + micro arrow). Colour
map in Tidewater: Active green · Past due yellow · Churned red · Trialing sky · Upgrade indigo · Draft gray.

```html
<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 inset-ring inset-ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:inset-ring-green-500/20">Active</span>
<span class="inline-flex items-center gap-x-1.5 rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 inset-ring inset-ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-500 dark:inset-ring-yellow-400/20">
  <svg viewBox="0 0 6 6" aria-hidden="true" class="size-1.5 fill-yellow-500 dark:fill-yellow-400"><circle r="3" cx="3" cy="3"/></svg>Past due
</span>
<span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">1,392</span>
```

**Do** keep one meaning per colour across the product. **Don't** rely on colour alone: the word is the status.

## 5. Text input

**Purpose** single-line entry. **Anatomy** `label` `block text-sm/6 font-medium`, `mt-2` input
`block w-full rounded-md bg-white px-3 py-1.5 text-base outline-1 -outline-offset-1 outline-gray-300
sm:text-sm/6`, help `mt-2 text-sm/6 text-gray-600`. **Variants** leading icon (grid overlay, `pl-10`),
add-on prefix/suffix (`has-[input:focus-within]:outline-2` on the wrapper). **States** focus
`focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600`; error `text-red-900 outline-red-300` +
icon + `aria-invalid="true"` + message `text-sm text-red-600`; disabled
`disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 disabled:outline-gray-200`.
Required: a red `*` with `aria-hidden="true"` and the `required` attribute.

```html
<label for="billing-email" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Billing email <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
<div class="mt-2 grid grid-cols-1">
  <input id="billing-email" type="email" required value="accounts@brightline" aria-invalid="true" aria-describedby="billing-email-error" class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-base text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:focus:outline-red-400">
  <svg class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><!-- exclamation-circle --></svg>
</div>
<p id="billing-email-error" class="mt-2 text-sm text-red-600 dark:text-red-400">Enter a complete address, for example accounts@brightline.design.</p>
```

**Do** use real `<input>` in app forms. **Don't** use an `<input>` for sign-in credentials in a mockup (§ 20).

## 6. Select

**Purpose** one choice from a short list. **Anatomy** native `<select>` with `appearance-none` and a mini
chevron laid over it in the same grid cell. **States** as the text input; `dark:*:bg-gray-800` colours the
open list in dark mode.

```html
<label for="plan" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Plan</label>
<div class="mt-2 grid grid-cols-1">
  <select id="plan" name="plan" class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:*:bg-gray-800 dark:focus:outline-indigo-500">
    <option>Starter</option><option selected>Growth</option><option>Scale</option>
  </select>
  <svg class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><!-- chevron-down --></svg>
</div>
```

**Do** keep the native control (accessible, mobile pickers). **Don't** draw a fake listbox unless the PRD asks for search inside it.

## 7. Textarea

**Purpose** multi-line notes. **Anatomy** the text-input classes on `<textarea rows="3">`; preflight sets
`resize: vertical`. Help text below with `aria-describedby`.

```html
<label for="notes" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Internal notes</label>
<div class="mt-2"><textarea id="notes" rows="3" aria-describedby="notes-help" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-400 dark:focus:outline-indigo-500">Renewal call booked for November 18.</textarea></div>
<p id="notes-help" class="mt-2 text-sm/6 text-gray-600 dark:text-gray-400">Visible to your workspace only.</p>
```

## 8. Checkbox and radio

**Purpose** independent options (checkbox) or one of a set (radio). **Anatomy** checkbox: `appearance-none`
input + an SVG check stacked in a `group grid size-4` cell, revealed with `group-has-checked:opacity-100`;
radio: `appearance-none rounded-full` with a `before:` dot. **States** checked, unchecked, indeterminate
(the dash path, `group-has-indeterminate:`), disabled (`disabled:bg-gray-100`, label `text-gray-400`), focus.
CSS only: no JavaScript.

```html
<div class="flex gap-3">
  <div class="flex h-6 shrink-0 items-center">
    <div class="group grid size-4 grid-cols-1">
      <input id="alert-payment" type="checkbox" checked class="col-start-1 row-start-1 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 forced-colors:appearance-auto">
      <svg viewBox="0 0 14 14" fill="none" aria-hidden="true" class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white"><path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-checked:opacity-100"/></svg>
    </div>
  </div>
  <div class="text-sm/6"><label for="alert-payment" class="font-medium text-gray-900 dark:text-white">A payment fails</label><p class="text-gray-500 dark:text-gray-400">After Stripe's first automatic retry.</p></div>
</div>
<div class="flex items-center gap-x-3">
  <input id="digest-both" name="digest" type="radio" checked class="relative size-4 appearance-none rounded-full border border-gray-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white not-checked:before:hidden checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 forced-colors:appearance-auto forced-colors:before:hidden">
  <label for="digest-both" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Email and Slack</label>
</div>
```

## 9. Toggle switch

**Purpose** an on/off setting that applies immediately. **Anatomy** a `group` track `w-11 rounded-full
bg-gray-200 p-0.5` with a `size-5` knob and an invisible full-size checkbox on top; `has-checked:bg-indigo-600`
colours the track and `group-has-checked:translate-x-5` moves the knob. Label and description through
`aria-labelledby` / `aria-describedby`.

```html
<div class="flex items-center justify-between gap-x-6">
  <div class="text-sm/6"><p id="digest-label" class="font-medium text-gray-900 dark:text-white">Include in the Monday digest</p><p id="digest-desc" class="text-gray-500 dark:text-gray-400">Shows this account's changes in the weekly summary.</p></div>
  <div class="group relative inline-flex w-11 shrink-0 rounded-full bg-gray-200 p-0.5 inset-ring inset-ring-gray-900/5 outline-offset-2 outline-indigo-600 transition-colors duration-200 ease-in-out has-checked:bg-indigo-600 has-focus-visible:outline-2 dark:bg-white/5 dark:inset-ring-white/10 dark:outline-indigo-500 dark:has-checked:bg-indigo-500">
    <span class="size-5 rounded-full bg-white shadow-xs ring-1 ring-gray-900/5 transition-transform duration-200 ease-in-out group-has-checked:translate-x-5"></span>
    <input id="digest" type="checkbox" checked aria-labelledby="digest-label" aria-describedby="digest-desc" class="absolute inset-0 size-full cursor-pointer appearance-none focus:outline-hidden">
  </div>
</div>
```

**Do** use a toggle for settings saved on change. **Don't** use it inside a form that needs a Save button: use a checkbox there.

## 10. File upload drop zone

**Purpose** attach a file. **Anatomy** dashed box `rounded-lg border border-dashed border-gray-900/25 px-6
py-10`, a 48 px outline icon, an "Upload a file" label wrapping an `sr-only` file input, the type/size hint,
and an uploaded-files list below. **States** default, with file listed, focus on the label
(`focus-within:outline-2`).

```html
<div class="mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 px-6 py-10 dark:border-white/25">
  <div class="text-center">
    <svg class="mx-auto size-12 text-gray-300 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><!-- cloud-arrow-up --></svg>
    <div class="mt-4 flex flex-wrap justify-center text-sm/6 text-gray-600 dark:text-gray-400">
      <label for="contract" class="relative cursor-pointer rounded-md font-semibold text-indigo-600 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-indigo-600 hover:text-indigo-500 dark:text-indigo-400"><span>Upload a file</span><input id="contract" type="file" class="sr-only"></label>
      <p class="pl-1">or drag and drop</p>
    </div>
    <p class="text-xs/5 text-gray-600 dark:text-gray-400">PDF up to 10 MB</p>
  </div>
</div>
```

## 11. Form layout (two-column sections)

**Purpose** long create/edit forms. **Anatomy** `divide-y divide-gray-900/10` stack of sections; each
`grid md:grid-cols-3`: heading + description on the left, a white card `md:col-span-2` with a
`grid sm:grid-cols-6 gap-x-6 gap-y-8` of fields (`sm:col-span-3`, `sm:col-span-4`, `col-span-full`). Actions
in a sticky footer `sticky bottom-0 … backdrop-blur-sm`, destructive action on the far left.

```html
<section aria-labelledby="sec-billing" class="grid grid-cols-1 gap-x-8 gap-y-6 py-10 md:grid-cols-3">
  <div>
    <h2 id="sec-billing" class="text-base/7 font-semibold text-gray-900 dark:text-white">Billing</h2>
    <p class="mt-1 text-sm/6 text-gray-600 dark:text-gray-400">Synced from Stripe.</p>
  </div>
  <div class="rounded-xl bg-white shadow-xs outline-1 outline-gray-900/5 md:col-span-2 dark:bg-gray-900 dark:shadow-none dark:outline-white/10">
    <div class="grid grid-cols-1 gap-x-6 gap-y-8 px-4 py-6 sm:grid-cols-6 sm:p-8">
      <div class="sm:col-span-3"><!-- field --></div>
      <div class="sm:col-span-3"><!-- field --></div>
    </div>
  </div>
</section>
```

## 12. Alerts

**Purpose** page-level feedback. **Anatomy** `rounded-md p-4` tinted panel + `outline-1` hairline, a mini
icon `size-5 text-{c}-400`, title `text-sm font-medium text-{c}-800`, body `mt-1 text-sm text-{c}-700`.
**Variants** success green, warning yellow, error red (`role="alert"`), info sky with a trailing link.

```html
<div class="rounded-md bg-yellow-50 p-4 outline-1 outline-yellow-600/10 dark:bg-yellow-500/10 dark:outline-yellow-500/20">
  <div class="flex">
    <svg class="size-5 shrink-0 text-yellow-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><!-- exclamation-triangle --></svg>
    <div class="ml-3">
      <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-100">22 customers are past due</h3>
      <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-200">$4,180 MRR is at risk this week.</p>
    </div>
  </div>
</div>
```

## 13. Tabs

**Purpose** sections of one page. **Variants** underline (`border-b-2 border-indigo-600 text-indigo-600`
on the current tab, `border-transparent text-gray-500 hover:border-gray-300` on the rest), with count pill,
pills (`rounded-md bg-indigo-100 text-indigo-700`). The tab row scrolls sideways on phones
(`overflow-x-auto`, `shrink-0 whitespace-nowrap`).

```html
<div class="border-b border-gray-200 dark:border-white/10">
  <nav aria-label="Customer status" class="-mb-px flex gap-x-8 overflow-x-auto">
    <a href="#" aria-current="page" class="flex shrink-0 items-center gap-x-2 border-b-2 border-indigo-600 px-1 pb-3 text-sm font-medium whitespace-nowrap text-indigo-600 dark:border-indigo-400 dark:text-indigo-400">All<span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">1,392</span></a>
    <a href="#" class="flex shrink-0 items-center gap-x-2 border-b-2 border-transparent px-1 pb-3 text-sm font-medium whitespace-nowrap text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Past due</a>
  </nav>
</div>
```

## 14. Table with selection, sorting and bulk actions

**Purpose** index screens. **Anatomy** card `overflow-hidden rounded-lg bg-white shadow-xs outline-1`, a
`relative overflow-x-auto` scroller (the `relative` keeps `sr-only` text from widening the page), `table
min-w-full divide-y`, checkbox column `relative px-7 sm:w-12 sm:px-6`, sortable header with a chevron
(`aria-sort`), status badges, right-aligned numbers `tabular-nums`, row actions (Edit link + kebab icon
button). **States** selected row `bg-gray-50` + `w-0.5 bg-indigo-600` bar + indigo name; bulk bar
`bg-indigo-50` above the header while rows are selected; hidden columns on phones
(`hidden md:table-cell`).

```html
<div class="relative overflow-x-auto">
  <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
    <thead><tr>
      <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-6 dark:text-white">Customer</th>
      <th scope="col" aria-sort="descending" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-white"><a href="#" class="group inline-flex">MRR<span class="ml-2 flex-none rounded-sm bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white"><!-- chevron-down size-5 --></span></a></th>
    </tr></thead>
    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
      <tr class="bg-gray-50 dark:bg-white/2.5">
        <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-indigo-600 sm:pl-6 dark:text-indigo-400">Verdant Health</td>
        <td class="px-3 py-4 text-right text-sm font-medium whitespace-nowrap text-gray-900 tabular-nums dark:text-white">$5,400</td>
      </tr>
    </tbody>
  </table>
</div>
```

**Do** keep text columns left and numbers right. **Don't** let the table scroll the page: the wrapper scrolls.

## 15. Dropdown menu

**Purpose** secondary actions and filters. **Anatomy** trigger with `aria-expanded`, panel `absolute z-10
mt-2 w-56 rounded-md bg-white shadow-lg ring-1 ring-black/5` (dark: `dark:bg-gray-800 dark:outline-white/10`),
groups split by `divide-y divide-gray-100`, items `px-4 py-2 text-sm` with a mini icon. **States** active
item `bg-gray-100 text-gray-900`, destructive item `text-red-600`. Drawn open; Alpine toggles it in the app.

```html
<div class="relative inline-block">
  <button type="button" aria-expanded="true" aria-haspopup="true" class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:inset-ring-white/5">Options</button>
  <div role="menu" class="absolute left-0 z-10 mt-2 w-56 origin-top-left divide-y divide-gray-100 rounded-md bg-white shadow-lg ring-1 ring-black/5 dark:divide-white/10 dark:bg-gray-800 dark:outline-1 dark:-outline-offset-1 dark:outline-white/10 dark:ring-0">
    <div class="py-1"><a href="#" role="menuitem" class="flex items-center gap-x-3 bg-gray-100 px-4 py-2 text-sm text-gray-900 dark:bg-white/5 dark:text-white">Edit</a></div>
    <div class="py-1"><a href="#" role="menuitem" class="flex items-center gap-x-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-400/10">Delete</a></div>
  </div>
</div>
```

## 16. Modal dialog

**Purpose** confirm a consequential action. **Anatomy** fixed backdrop `bg-gray-500/75` (dark
`bg-gray-950/75`), panel `rounded-lg bg-white px-4 pt-5 pb-4 shadow-xl sm:p-6 sm:max-w-lg`, icon circle
`size-12 rounded-full bg-red-100`, title `text-base font-semibold`, actions `sm:flex sm:flex-row-reverse`
(primary on the right on desktop, full-width stacked on phones). `role="dialog" aria-modal="true"`.

```html
<div class="relative z-50" role="dialog" aria-modal="true" aria-labelledby="exclude-title">
  <div aria-hidden="true" class="fixed inset-0 bg-gray-500/75 dark:bg-gray-950/75"></div>
  <div class="fixed inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-lg bg-white px-4 pt-5 pb-4 shadow-xl sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10">
      <h3 id="exclude-title" class="text-base font-semibold text-gray-900 dark:text-white">Exclude 3 customers?</h3>
      <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">$11,950 MRR leaves every chart. You can include them again later.</p>
      <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse sm:gap-x-3">
        <button type="button" class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 sm:w-auto dark:bg-red-500">Exclude</button>
        <button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:inset-ring-white/5">Cancel</button>
      </div>
    </div>
  </div>
</div>
```

## 17. Notification toast

**Purpose** confirmation after an action. **Anatomy** `fixed bottom-0 right-0` region with
`aria-live="polite"`, card `w-full max-w-sm rounded-lg bg-white shadow-lg outline-1 outline-black/5`,
outline icon `size-6 text-green-400`, title + body, close icon button with `aria-label`.

```html
<div aria-live="polite" class="pointer-events-none fixed inset-0 flex items-end px-4 py-6 sm:items-end sm:p-6">
  <div class="flex w-full flex-col items-center sm:items-end">
    <div class="pointer-events-auto w-full max-w-sm rounded-lg bg-white shadow-lg outline-1 outline-black/5 dark:bg-gray-800 dark:-outline-offset-1 dark:outline-white/10">
      <div class="flex items-start p-4">
        <div class="ml-3 w-0 flex-1 pt-0.5"><p class="text-sm font-medium text-gray-900 dark:text-white">Report scheduled</p><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The board pack goes out on Monday, November 2.</p></div>
        <button type="button" aria-label="Close" class="ml-4 inline-flex shrink-0 rounded-md text-gray-400 hover:text-gray-500 dark:hover:text-white"><!-- x-mark size-5 --></button>
      </div>
    </div>
  </div>
</div>
```

## 18. Stats

**Purpose** KPIs at the top of a dashboard. **Anatomy** a `dl` grid with `gap-px` on a `bg-gray-900/5`
background (hairlines between tiles), each tile `bg-white px-4 py-5 sm:p-6`: label `text-sm/6 font-medium
text-gray-500`, value `text-2xl font-semibold tracking-tight tabular-nums`, trend badge (§ 4), comparison
`text-xs text-gray-500`. Grid `grid-cols-1 sm:grid-cols-2 xl:grid-cols-4`.

```html
<dl class="grid grid-cols-1 gap-px overflow-hidden rounded-lg bg-gray-900/5 shadow-xs outline-1 outline-gray-900/5 sm:grid-cols-2 xl:grid-cols-4 dark:bg-white/10 dark:shadow-none dark:outline-white/10">
  <div class="bg-white px-4 py-5 sm:p-6 dark:bg-gray-900">
    <dt class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Monthly recurring revenue</dt>
    <dd class="mt-1 flex items-baseline justify-between gap-x-3"><span class="text-2xl font-semibold tracking-tight text-gray-900 tabular-nums dark:text-white">$84,210</span><span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 inset-ring inset-ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:inset-ring-green-500/20">+4.6%</span></dd>
    <dd class="mt-1 text-xs text-gray-500 dark:text-gray-400">from $80,510 in September</dd>
  </div>
</dl>
```

## 19. Charts (inline SVG)

**Purpose** trends. **Anatomy** a card; y-axis labels in an HTML column (`flex h-60 flex-col justify-between
text-xs/5 text-gray-500`), an SVG with `preserveAspectRatio="none"`, dashed gridlines
`stroke-gray-200 dark:stroke-white/10` and `vector-effect="non-scaling-stroke"`, the series line
`stroke-current` on an `text-indigo-600 dark:text-indigo-400` SVG, an area filled with a `currentColor`
gradient, x labels in a `grid grid-cols-12` (every other one `invisible sm:visible`), the hovered point and
its tooltip drawn statically. `role="img"` + an `aria-label` that states the numbers.

```html
<svg class="h-60 w-full text-indigo-600 dark:text-indigo-400" viewBox="0 0 720 240" preserveAspectRatio="none" role="img" aria-label="MRR from $61,200 in November 2025 to $84,210 in October 2026">
  <defs><linearGradient id="mrr-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".22"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
  <g class="stroke-gray-200 dark:stroke-white/10" stroke-dasharray="3 4"><line x1="0" x2="720" y1="120" y2="120" vector-effect="non-scaling-stroke"/></g>
  <path d="M30 168C150 152 390 106 690 42L690 240L30 240Z" fill="url(#mrr-fill)"/>
  <path d="M30 168C150 152 390 106 690 42" class="fill-none stroke-current" stroke-width="2.5" vector-effect="non-scaling-stroke"/>
</svg>
```

**Do** draw from tokens only (indigo for the series, gray for grid and labels). **Don't** use canvas or a chart library in a mockup.

## 20. Drawn credential fields (sign-in screens)

**Purpose** a picture of the sign-in form that never triggers a password manager. **Anatomy** `mock-label`
+ `div.mock-field` with `role="img"` and an `aria-label` that says what is drawn; `mock-field--secret` for
the dots, `--focus`, `--error`, `--empty`, `--disabled`. They are styled exactly like § 5 in both themes.
The submit is an `<a role="button">`. No `<form>`, no text `<input>`, no `type="password"` anywhere.

```html
<span class="mock-label">Email address</span>
<div class="mt-2"><div class="mock-field" role="img" aria-label="Email field, filled in">maya@lumenfield.io</div></div>
<span class="mock-label">Password</span>
<div class="mt-2"><div class="mock-field mock-field--secret mock-field--focus" role="img" aria-label="Password field, filled in">••••••••••••</div></div>
<a href="dashboard.html" role="button" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Sign in</a>
```

## 21. Empty state

**Purpose** first run, no data, no results. **Anatomy** dashed box `rounded-xl border-2 border-dashed
border-gray-300 px-6 py-12 text-center`, outline icon `size-12 text-gray-400`, title `text-base
font-semibold`, one sentence, one primary button; optional "start from a template" list below
(`divide-y` rows with a coloured icon tile and a stretched link).

```html
<div class="rounded-xl border-2 border-dashed border-gray-300 px-6 py-12 text-center dark:border-white/15">
  <svg class="mx-auto size-12 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><!-- document-chart-bar --></svg>
  <h2 class="mt-3 text-base font-semibold text-gray-900 dark:text-white">No scheduled reports yet</h2>
  <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">Send your board the same numbers you see here, on a schedule you choose.</p>
  <div class="mt-6"><a href="#" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Create a report</a></div>
</div>
```

## 22. Marketing sections

**Purpose** the public site (`landing.html`). **Pieces** header (`absolute inset-x-0 top-0`, links
`hidden lg:flex lg:gap-x-12`), hero (`text-5xl font-semibold tracking-tight text-balance sm:text-7xl`, a
blurred `bg-linear-to-tr from-sky-300 to-indigo-600` shape, an app screenshot framed in
`rounded-xl bg-gray-900/5 p-2 ring-1 ring-inset ring-gray-900/10`), logo cloud, icon feature grid
(`relative pl-16` + `size-10 rounded-lg bg-indigo-600` tile), stats, pricing tiers (`rounded-3xl p-8 ring-1`,
featured `ring-2 ring-indigo-600`), testimonial, FAQ with `<details>` (`group-open:` swaps plus/minus), dark
CTA panel, four-column footer.

```html
<div class="rounded-3xl p-8 ring-2 ring-indigo-600 xl:p-10 dark:bg-white/5 dark:ring-indigo-500">
  <div class="flex items-center justify-between gap-x-4"><h3 id="tier-growth" class="text-lg/8 font-semibold text-indigo-600 dark:text-indigo-400">Growth</h3><p class="rounded-full bg-indigo-600/10 px-2.5 py-1 text-xs/5 font-semibold text-indigo-600 dark:bg-indigo-500 dark:text-white">Most popular</p></div>
  <p class="mt-6 flex items-baseline gap-x-1"><span class="text-4xl font-semibold tracking-tight text-gray-900 dark:text-white">$99</span><span class="text-sm/6 font-semibold text-gray-600 dark:text-gray-400">/month</span></p>
  <a href="#" aria-describedby="tier-growth" class="mt-6 block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm/6 font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Start free trial</a>
</div>
<details class="group py-6" open>
  <summary class="flex w-full cursor-pointer list-none items-start justify-between text-left text-gray-900 dark:text-white [&::-webkit-details-marker]:hidden"><span class="text-base/7 font-semibold">How far back does the history go?</span></summary>
  <p class="mt-2 pr-12 text-base/7 text-gray-600 dark:text-gray-400">All of it.</p>
</details>
```

**Do** keep one indigo call to action per section. **Don't** add remote images: draw screenshots with HTML
and SVG, and logos as simple SVG marks with the wordmark in text.
