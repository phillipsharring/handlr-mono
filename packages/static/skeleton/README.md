# Graspr Static Skeleton

![Graspr](graspr.png)

Starter project for building static sites with [`@phillipsharring/graspr-build`](https://github.com/phillipsharring/graspr-build)  - an HTML custom-tag compiler, page baker, and Vite dev plugin. No runtime framework, no HTMX, no client-side state. Just layouts, components, pages, and Tailwind.

For a full app with HTMX, auth, modals, and toasts, use the [Graspr App Skeleton](https://github.com/phillipsharring/graspr-app-skeleton) instead.

## Getting Started

```bash
# Clone and set up
git clone git@github.com:phillipsharring/graspr-static-skeleton.git my-site
cd my-site
rm -rf .git
npm install
git init

# Start developing
npm run dev
```

Your site is running at `http://localhost:5173`.

## Project Structure

```
src/
  app.js              Vite entry  - imports CSS
  styles/
    style.css         Tailwind + custom theme + app styles
content/
  layouts/
    base.html         Base layout (head, body shell, [[app]] slot)
  pages/
    index.html        Home page
    404.html          Not-found page
  components/
    callout.html      Example custom-tag component
public/               Static assets (favicon, images, robots.txt)
site.config.js        Site name, URL, copyright  - injected into layouts
vite.config.js        Vite + graspr-build + Tailwind plugins
```

## Development

```bash
npm run dev       # Start Vite dev server with HMR
npm run build     # Production build (Vite + page compilation to dist/)
npm run preview   # Preview production build locally
```

The dev server renders pages on the fly through the graspr-build Vite plugin, so editing a layout, component, or page hot-reloads the browser without a full build step.

## Pages & Layouts

Every page declares its layout in a self-closing tag at the top, then the page body follows:

```html
<layout name="base" title="About" />
<page-head>
<meta name="description" content="..." />
</page-head>

<article>
    <h1>About</h1>
    <p>This renders inside the layout's [[app]] slot.</p>
</article>
```

- `<layout name="..." title="..." />` picks the layout from `content/layouts/`
- `<page-head>...</page-head>` injects extra `<head>` content (meta tags, Open Graph, etc.)
- Everything after is the page body, slotted into the layout's `[[app]]` placeholder

### Abstract URLs

Pages live in `content/pages/` as plain `.html` files and compile to `dist/<route>/index.html`. For extensionless URLs (`/about` instead of `/about/`), add a post-build step that flattens the tree  - see `phillipharrington.com` for a working example using `scripts/flatten-dist.mjs`.

## Components

Create reusable HTML components in `content/components/` as custom tags:

```html
<!-- content/components/callout.html -->
<aside class="rounded border border-blue-300 bg-blue-50 p-4 [[class]]">
    [[#if title]]<h3 class="font-bold">[[title]]</h3>[[/if]]
    [[slot]]
</aside>
```

Use them in pages:

```html
<callout title="Note" class="bg-blue-50">
    <p>This is a callout.</p>
</callout>
```

### Interpolation syntax

- `[[prop]]`  - HTML-escaped prop
- `[[{prop}]]`  - raw prop (for attribute values, HTML snippets)
- `[[slot]]`  - child content passed between the component's tags
- `[[#if flag]] ... [[else]] ... [[/if]]`  - conditional on a prop's truthiness

Custom tags can be single-word (`<callout>`  - works as long as `callout.html` exists in `content/components/`) or HTML custom-element style (`<my-callout>`  - must contain a hyphen).

## Customization

### Site Config

Edit `site.config.js` to set your site-wide layout variables:

```js
export default {
    siteUrl: 'https://my-site.com',
    siteName: 'My Site',
    copyright: '© My Site. All rights reserved.',
};
```

Reference them in layouts and pages as `[[siteName]]`, `[[siteUrl]]`, `[[copyright]]`.

### Adding JavaScript

For small amounts of interactivity (hamburger menus, theme toggles, scroll effects), add it directly to `src/app.js`. The skeleton has no runtime framework to fight with  - everything is a plain vanilla script and imports nothing unless you ask.

For anything larger (HTMX requests, auth-gated widgets, modal state), you probably want the [Graspr App Skeleton](https://github.com/phillipsharring/graspr-app-skeleton) instead, which bundles `@phillipsharring/graspr-framework` on top of graspr-build.

## What You Get From graspr-build

The build system (`@phillipsharring/graspr-build`) provides:

- Custom HTML tag expansion  - author pages with `<callout>`, `<heading h2>`, `<card>`, whatever you define
- Layout system with `[[app]]` slot and `<page-head>` injection
- `[[prop]]` / `[[{raw}]]` / `[[#if]]` / `[[slot]]` interpolation
- File-based page routing (`content/pages/about.html` → `/about`)
- Vite dev plugin with hot reload on layouts, components, and pages
- `graspr-build-pages` CLI for baking pages in production builds

You own and customize: layouts, pages, components, `src/app.js`, `src/styles/`, and `site.config.js`.

## License

MIT
