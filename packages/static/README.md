# create-graspr-static

![Graspr](graspr.png)

Scaffold a new [Graspr](https://github.com/phillipsharring/graspr-static-skeleton) static site (Vite + Tailwind + custom HTML components).

## Usage

```bash
npm create graspr-static my-site
```

This will:

1. Clone the [Graspr static skeleton](https://github.com/phillipsharring/graspr-static-skeleton)
2. Remove git history (fresh start)
3. Install npm dependencies

Then:

```bash
cd my-site
npm run dev
```

## What you get

A Vite-powered static site with:

- **Tailwind CSS v4** for styling
- **Custom HTML components**  - author pages with `<callout>`, `<heading>`, `<card>`, whatever you define
- **Layout system** with `[[app]]` slot and `<page-head>` injection
- **File-based page routing** (`content/pages/about.html` → `/about`)
- Hot reload in dev, page compilation for production

No HTMX, no Handlebars runtime, no client-side state. Just layouts, components, pages, and Tailwind.

## Requirements

- Node.js 18+
- Git

## Full app?

For a full-stack app with HTMX, auth, modals, and toasts, use [create-graspr-app](https://github.com/phillipsharring/graspr-installer) instead:

```bash
npm create graspr-app my-app
```

Pair with [Handlr](https://github.com/phillipsharring/handlr-installer) for the backend:

```bash
composer create-project phillipsharring/handlr-app my-app-backend
```
