# @phillipsharring/create-handlr-static

![Handlr](handlr.png)

Scaffold a new [Handlr](https://github.com/phillipsharring/handlr-mono) static site (Vite + Tailwind + custom HTML components).

## Usage

```bash
npm create @phillipsharring/handlr-static my-site
```

This will:

1. Copy the bundled static skeleton into `my-site/`
2. Install npm dependencies

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

No HTMX, no Handlebars runtime, no client-side state. Just layouts, components, pages, and Tailwind. The build is powered by [`@phillipsharring/handlr-build`](https://github.com/phillipsharring/handlr-mono/tree/main/packages/build).

## Requirements

- Node.js 18+

## Full app?

For a full-stack app with HTMX, auth, modals, and toasts, scaffold the Handlr app skeleton instead:

```bash
composer create-project phillipsharring/handlr-app my-app
```

That pulls in [Handlr Frontend](https://github.com/phillipsharring/handlr-mono/tree/main/packages/frontend) (runtime) and [Handlr Backend](https://github.com/phillipsharring/handlr-mono/tree/main/packages/backend) (PHP) together.
