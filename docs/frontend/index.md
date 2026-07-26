# Frontend Overview

The frontend is two packages, layered:

1. **handlr-build** (`@phillipsharring/handlr-build`) — Vite + Tailwind + a build
   pipeline that compiles layouts, pages, and components into static HTML. No JS
   runtime required. Usable standalone for a purely static site.
2. **handlr-frontend** (`@phillipsharring/handlr-frontend`) — the runtime on top:
   HTMX (with `json-enc` + `client-side-templates`), Handlebars, auth, modals, CSRF,
   boosted navigation, and a declarative behavior layer. For apps with a backend API.

They compose but don't require each other in one direction: a static marketing site
uses only handlr-build; a full app uses both.

## Static-only vs the full runtime

| You have… | Use | You get |
|---|---|---|
| A content site (marketing, docs, landing) | handlr-build only | static HTML, layouts, components, Tailwind — no JS runtime to ship |
| An app backed by a handlr API | both | the above + HTMX wiring, auth-gated UI, modals, toasts, CSRF, client-side templates |

You can start static and add the runtime later — the build output is the same static
HTML either way; the runtime just brings it to life.

## The toolkit stance

handlr-frontend is a **composable toolkit on top of HTMX, not an
inversion-of-control framework** (ADR 0002). Apps call it; it does not call apps. Two
consequences shape everything:

- **Two entry points.** `import '@phillipsharring/handlr-frontend'` (the barrel) is
  pure named exports with **zero side effects** — you take only what you call, and it
  tree-shakes. `import '@phillipsharring/handlr-frontend/init'` is the opt-in
  **batteries** that wire the default listeners.
- **One global.** The package sets exactly `window.htmx`. It does **not** create
  `window.App` — your app builds that namespace and hangs the exports it wants on it.

See [Runtime](./runtime) for the entry-point contract, and
[Declarative Behavior](./declarative) for the attribute-driven layer that lets you
build most UI without writing JavaScript.

## Peer dependencies

handlr-frontend declares these as peers (you install them):

```bash
npm install @phillipsharring/handlr-frontend
npm install htmx.org handlebars sortablejs   # peers
```

- `htmx.org@^2.0` — the transport.
- `handlebars@^4.7` — client-side templates.
- `sortablejs@^1.15` — drag-to-reorder (only if you use `[data-sortable]`).

handlr-build is a devDependency (it runs at build time):

```bash
npm install -D @phillipsharring/handlr-build
```

Its only optional peer is `html-minifier-terser@^7`, loaded solely when you turn
minification on.

## Where to go next

| Page | What it covers |
|---|---|
| [Build (handlr-build)](./build) | layouts, pages, components, the static bake |
| [Runtime (handlr-frontend)](./runtime) | entry points, HTMX extensions, lifecycle hooks |
| [Declarative Behavior](./declarative) | `data-action`, `data-on-success`, the action registry |
| [HTMX Patterns](./htmx) | forms, client-side templates, errors, refresh |
| [Modals & Toasts](./ui) | the global modal, modal forms, confirms, toasts |
| [CSRF](./csrf) | the token-in-header strategy, front and back |
| [Auth State](./auth-state) | auth-gated UI and 401/403 handling |
