# Runtime (handlr-frontend)

`@phillipsharring/handlr-frontend` is a composable HTMX toolkit. Import only what you
call — the barrel has no side effects — or opt into the batteries with one import.

## The two entry points

This contract (ADR 0002) is the toolkit's shape:

```js
// Pure named exports, ZERO side effects. Take only what you use; it tree-shakes.
import { openFormModal, HandlrToast, onAfterSwap } from '@phillipsharring/handlr-frontend';

// The batteries. Import once for side effects to wire the default listeners.
import '@phillipsharring/handlr-frontend/init';
```

`./init` wires: CSRF (fetch + HTMX header injection), boosted-nav handling,
auth-state, form errors, search, sortable, the lifecycle hooks, and the declarative
layer (`initActions()`, `initFormResponses()`, `initAuthFlows()`). Everything it turns
on is **also** usable à la carte from the barrel — new capability means a named export
or an explicit `initX()`, never a module that self-registers when imported from `.`.

A typical app entry:

```js
import Handlebars from 'handlebars';
import '@phillipsharring/handlr-frontend/init';
import { registerHandlebarsHelpers, registerToastHelpers } from '@phillipsharring/handlr-frontend';

registerHandlebarsHelpers(Handlebars);
registerToastHelpers(Handlebars);
```

## One global: `window.htmx`

The package sets exactly one global — `window.htmx` (a single shared instance, so
inline scripts and HTMX extensions all use the same one). It does **not** create
`window.App`. If you want `App.ui.openFormModal(...)` style access from inline
`<script>` tags, your app builds that namespace and hangs the exports on it:

```js
import * as HF from '@phillipsharring/handlr-frontend';
window.App = {
    ui: { openFormModal: HF.openFormModal, toast: HF.HandlrToast },
    hooks: { onAfterSwap: HF.onAfterSwap },
    getRouteParams: HF.getRouteParams,
};
```

## HTMX extensions

Two HTMX extensions ship in `lib/` and are activated with `hx-ext`:

- **`json-enc`** — encodes non-GET request bodies as JSON (instead of form-encoding),
  and sets the JSON `Content-Type`/`Accept`. Handlr APIs speak JSON, so this is the
  default for forms.
- **`client-side-templates`** — renders a JSON response through a Handlebars
  `<template>` on the page. This is what turns an API response into DOM. See
  [HTMX Patterns](./htmx) for the full pattern (object vs array templates, the
  `meta` envelope, pagination).

```html
<body hx-ext="json-enc, client-side-templates">
```

## Handlebars rendering

`registerHandlebarsHelpers(Handlebars)` registers the partials and helpers the
templates use — `formButtons`, `copyIdBtn`, and helpers like `eq`, `truncate`,
`humanize`, `timeAgo`, `formatDateTime`. The datetime helpers parse values as **UTC**
and format to the viewer's local zone (backend timestamps are UTC).

## Lifecycle hooks

Boosted navigation replaces everything inside `#app` on each swap, so a one-time
`DOMContentLoaded` listener won't re-run for content that arrives later. Use the hooks
instead — each fires only when the swap target is `#app`:

```js
import { onPageLoad, onAfterSwap, onAfterSettle, onHistoryRestore } from '@phillipsharring/handlr-frontend';

onPageLoad(doc => { /* DOMContentLoaded — runs immediately if already loaded */ });
onAfterSwap(target => { /* after #app content is swapped in */ });
onAfterSettle(target => { /* after HTMX settles — hx-trigger attrs are wired */ });
onHistoryRestore(() => { /* back/forward navigation */ });
```

Use `onAfterSettle` (not `onAfterSwap`) when you need `hx-trigger` attributes on the
new content to already be active.

## Boosted navigation

With `hx-boost="true"` + `hx-select="#app"` on `<body>`, links become in-place
`#app` swaps. `boosted-nav.js` (wired by `./init`) handles the sharp edges: it
overrides `hx-select` for non-boosted elements so JSON/template responses aren't
filtered, redirects inherited `hx-target="this"` back to `#app`, preserves the
`<title>`, and does a full page load when the incoming `data-layout` differs from the
current one.

## Runtime modules

If you use build-time [modules](/modules/), their browser-side `init()` runs via
`initModules()` (from `@phillipsharring/handlr-build/modules`), called once in your
app entry with the same module list your `site.config.js` uses.

## See also

- [Declarative Behavior](./declarative) — build UI with attributes, not JS.
- [HTMX Patterns](./htmx) — forms, templates, errors, refresh.
- [Build](./build) — the static HTML this runtime animates.
