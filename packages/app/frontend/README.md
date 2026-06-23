# App Frontend

The frontend half of a Handlr app, built on [Handlr Frontend](https://github.com/phillipsharring/handlr-mono/tree/main/packages/frontend)  - an HTMX + Handlebars + Tailwind CSS runtime. Scaffolded as part of `composer create-project phillipsharring/handlr-app`.

## Getting Started

From the project root (the `composer create-project` post-install step runs `npm install` for you; run it manually if needed):

```bash
npm install
npm run dev
```

Your app is running at `http://localhost:5173`.

## Project Structure

```
src/
  app.js              Main entry  - imports framework, registers helpers, lifecycle hooks
  namespace.js        Assembles window.App namespace for inline scripts
  styles/
    style.css         Tailwind + framework base styles + app overrides
content/
  layouts/
    base.html         Base layout (header, footer, modal/toast slots)
  pages/
    index.html        Home page
  components/
    modal.html        Global modal markup
    toast.html        Toast notification markup
    confirm-dialog.html  Confirm dialog template
scripts/
  html-compiler.mjs   Layout + component + page compiler
  build-pages.mjs     Static page generator for production builds
site.config.js        Site name, URL, copyright  - injected into layouts
public/               Static assets (favicon, images)
vite.config.js        Vite config with dev middleware and API proxy
```

## Development

```bash
npm run dev       # Start Vite dev server with HMR
npm run build     # Production build (Vite + static page generation)
npm run preview   # Preview production build locally
```

### Backend API Proxy

The dev server proxies `/api/*` to your backend:

```bash
# Default: http://localhost:8000
npm run dev

# Custom backend:
HANDLR_ORIGIN=http://localhost:9000 npm run dev
```

## Pages & Layouts

Pages live in `content/pages/` and declare their layout:

```html
<layout name="base" title="My Page" />
<div>
    <h1>Hello</h1>
    <p>This renders inside the layout's [[app]] slot.</p>
</div>
```

### Dynamic Routes

Use `[param]` in filenames for dynamic routes:

```
content/pages/items/[id]/index.html  →  /items/:id/
```

Extract parameters in your page scripts:

```js
window.onReady(function() {
    const params = App.getRouteParams('/items/[id]/');
    console.log(params.id);
});
```

### Components

Create reusable HTML components in `content/components/`:

```html
<!-- content/components/callout.html -->
<div class="border rounded p-4 [[class]]">
    <h3>[[title]]</h3>
    [[slot]]
</div>
```

Use them in pages:

```html
<callout title="Note" class="bg-blue-50">
    <p>This is a callout.</p>
</callout>
```

## Customization

### Site Config

Edit `site.config.js` to set your app name and other layout variables:

```js
export default {
    siteUrl: '/',
    siteName: 'My App',
    copyright: '© My App. All Rights Reserved',
};
```

### Window Namespace

Edit `src/namespace.js` to customize what's available to inline page scripts. Rename `window.App` to whatever suits your project.

### Adding App-Specific Code

Create a directory for your domain code (e.g., `src/my-app/`) and import it from `app.js`:

```js
import './my-app/index.js';
```

Register app-specific Handlebars helpers, HTMX lifecycle hooks, and styles from there.

## What You Get From the Runtime

[Handlr Frontend](https://github.com/phillipsharring/handlr-mono/tree/main/packages/frontend) (`@phillipsharring/handlr-frontend`) provides:

- HTMX boosted navigation fixes
- CSRF token management
- Auth-gated widget system
- Modal, toast, confirm dialog, typeahead widgets
- Form error handling
- Pagination, search, sortable tables
- Handlebars helpers and template rendering
- Generic base styles

You own and customize: layouts, pages, components, app.js, namespace.js, styles, and site config.

## License

MIT
