# Build (handlr-build)

`@phillipsharring/handlr-build` turns a `content/` source tree — pages, layouts,
components — into a `dist/` of static HTML. It has two entry points into one
rendering core, so the dev server and the production bake produce identical HTML:

- The **Vite dev plugin** (`handlrBuild()`) renders pages on the fly during `vite dev`.
- The **page baker** (`handlr-build-pages` CLI) walks `content/pages/` after
  `vite build` and writes static HTML, baking in the hashed asset URLs from Vite's
  manifest.

It's ESM-only, Node 18+, and deliberately free of runtime concerns (HTMX, auth) —
those live in [handlr-frontend](./runtime). A static site can depend on handlr-build
alone.

## Project shape

```
my-site/
├── content/
│   ├── layouts/        base.html, …          (shared HTML shells)
│   ├── components/     callout.html, …       (custom-tag templates)
│   └── pages/          one HTML file per route
├── public/             static assets, copied as-is
├── src/
│   ├── app.js          Vite entry (at minimum, imports the CSS)
│   └── styles/         Tailwind CSS
├── site.config.js
└── vite.config.js
```

## Pages

A page is a body plus two optional leading directives:

```html
<layout name="base" title="About" />
<page-head>
  <meta name="description" content="About our team." />
</page-head>

<h1>About</h1>
<callout type="info">We build tools.</callout>
```

- `<layout name="..." title="..." />` — self-closing, must be first. Picks
  `content/layouts/<name>.html` (default `base`); its `title` overrides the
  route-derived one.
- `<page-head>…</page-head>` — extra `<head>` content, slotted into the layout's
  `[[pageHead]]`.
- Everything after is the body, slotted into the layout's `[[app]]`.

Files whose name starts with `_` are **skipped** (partials/private). A page's route
comes from its path: `about.html` → `/about/`, `blog/index.html` → `/blog/`,
`blog/post.html` → `/blog/post/`.

## Layouts and the `[[...]]` slots

A layout is a full HTML shell with placeholders that the compiler fills:

```html
<!doctype html>
<html>
  <head>
    <title>[[title]][[siteName]]</title>
    [[pageHead]]
    [[cssHref]]
  </head>
  <body>
    <main>[[app]]</main>
    [[jsSrc]]
  </body>
</html>
```

| Placeholder | Filled with |
|---|---|
| `[[app]]` | the fully-expanded page body |
| `[[title]]` | the page title + separator (or empty) |
| `[[pageHead]]` | the page's `<page-head>` content |
| `[[cssHref]]` | `<link rel="stylesheet">` from the Vite manifest |
| `[[jsSrc]]` | `<script type="module" defer>` from the manifest |
| `[[gitSha]]` | the short git SHA, as an HTML comment |
| `[[moduleAdminNav]]` | admin-nav links contributed by modules |
| `[[<anyKey>]]` | any `site.config.js` key — `[[siteName]]`, `[[copyright]]`, … |

There's no fixed list for the last row: **every key in `site.config.js` becomes a
`[[key]]` placeholder**, HTML-escaped.

## Components

A component is a reusable template in `content/components/<name>.html`. Invoke it as:

- a hyphenated custom element — `<my-callout>…</my-callout>`
- a single-word tag matching a file — `<callout>` ↔ `callout.html`
- explicitly — `<component name="callout" />` or `<component file="path.html" />`

Inside the template:

```html
<!-- components/callout.html -->
<div class="callout callout-[[type]]">
  [[#if dismissible]]<button class="close">×</button>[[/if]]
  [[slot]]
</div>
```

- `[[prop]]` — an HTML-escaped prop value.
- `[[{prop}]]` — a raw prop (for attribute values / HTML snippets).
- `[[slot]]` — the tag's child content.
- `[[#if flag]] … [[else]] … [[/if]]` — conditional on a prop's truthiness (nestable).
- A bare attribute (`<callout dismissible>`) arrives as `true`.
- A `class=` on the invocation merges onto the template's root element.

Expansion is multi-pass, so components nest; a recursion guard (50 passes) catches
loops.

### Handlebars runtime templates

`<template src="row" foo="bar">…</template>` inlines `row.html`, renders it with the
tag's attributes as props, and re-wraps it in `<template>` — this is how you embed the
client-side Handlebars templates that [HTMX patterns](./htmx) render at runtime.

### Dynamic routes

A bracketed path segment is dynamic: `content/pages/blog/[slug].html`. In the dev
server, real URLs match the pattern (`/blog/my-post/` → `[slug].html`). In the static
build, the page is baked at its literal `[slug]` path and the client resolves the
actual value at runtime.

## Config

### `site.config.js`

```js
export default {
    siteName: 'My Site',
    siteUrl: 'https://example.com',
    copyright: '© 2026 …',
    flatRoutes: false,        // or true | { keepExtension: ['404'] }
    minify: false,            // or true | { removeComments: false }
    devCss: '/styles/style.css?direct',  // dev-only FOUC fix (needs ?direct)
    modules: [ /* module objects */ ],
};
```

Every key becomes a `[[key]]`. `flatRoutes`, `minify`, and `devCss` are read as build
options — set them **here once** so the dev plugin and the CLI agree.

### `vite.config.js`

```js
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { handlrBuild } from '@phillipsharring/handlr-build/vite';
import siteConfig from './site.config.js';

export default defineConfig({
    root: 'src',
    publicDir: '../public',
    plugins: [tailwindcss(), handlrBuild({ siteConfig })],
    build: {
        outDir: '../dist',
        manifest: true,          // required — the baker reads dist/.vite/manifest.json
        emptyOutDir: true,
        rollupOptions: { input: { app: './src/app.js' } },
    },
});
```

### `package.json`

```json
{ "scripts": { "dev": "vite", "build": "vite build && handlr-build-pages" } }
```

`vite build` bundles assets and writes the manifest; `handlr-build-pages` bakes pages,
reads the manifest for hashed URLs, then deletes the manifest so a naive
`aws s3 sync dist/` never ships build-internal state.

## Tailwind and `@source`

Tailwind scans your source for class names at build time. Point it at anything that
contributes classes — including framework JS and, for a straight-HTMX app, the
backend's server-rendered views:

```css
@source "../../node_modules/@phillipsharring/handlr-frontend/src/**/*.js";
@source "../../../backend/resources/views/**/*.{php,html}";
```

## Output & hosting

- **Default** (`flatRoutes: false`): each route is a directory with `index.html` —
  `/about/` → `dist/about/index.html`.
- **`flatRoutes: true`**: extensionless files — `/about/` → `dist/about`. On S3,
  re-tag them `text/html` after sync (they have no extension to infer a MIME from).
  Nested-route conflicts (`/blog/` vs `/blog/post/`) are a hard error.

## Modules

Modules contribute pages and components. They are **explicit entries** in
`site.config.js`'s `modules` array — not a `node_modules` glob. `resolveModuleDirs()`
turns them into extra page/component dirs, appended after your app's own, so a
module's `pages/blog/index.html` routes to `/blog/` just like an app page. Route
collisions across the app and its modules are a hard error. See
[Writing a Module](/modules/).

## See also

- [Runtime](./runtime) — the JS layer that animates the baked HTML.
- [HTMX Patterns](./htmx) — the `<template>` runtime templates baked in here.
