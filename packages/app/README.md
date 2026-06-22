# Handlr App

A full-stack Handlr app skeleton  - the starting point for a server-driven web app built on **HTMX + Handlebars + Tailwind** on the front end and a **PHP Pipe + Handler** framework on the back end.

Scaffold a new project with one command:

```bash
composer create-project phillipsharring/handlr-app my-app
```

A `post-create-project-cmd` runs `npm install` in the frontend half, so a single command sets up both sides. Then:

```bash
cd my-app
composer run dev      # PHP server on http://localhost:8000
npm run dev           # Vite dev server on http://localhost:5173 (proxies /api to :8000)
```

## What's inside

This package ships both halves of an app:

```
frontend/    Vite + Tailwind + HTMX runtime (built on @phillipsharring/handlr-frontend
             and @phillipsharring/handlr-build)
backend/     PHP API built on phillipsharring/handlr-backend  - auth, RBAC, CSRF,
             migrations, seeders, and the Pipe + Handler conventions
```

- **[frontend/](frontend/README.md)** — pages, layouts, components, and the runtime wiring.
- **[backend/](backend/README.md)** — handlers, pipes, data layer, and database lifecycle.

## The pieces it builds on

| Package | Registry | Role |
|---|---|---|
| [`@phillipsharring/handlr-frontend`](https://github.com/phillipsharring/handlr-mono/tree/main/packages/frontend) | npm | Browser runtime (HTMX, auth, modals, forms) |
| [`@phillipsharring/handlr-build`](https://github.com/phillipsharring/handlr-mono/tree/main/packages/build) | npm | Build tooling (HTML compiler, page baker, Vite plugin) |
| [`phillipsharring/handlr-backend`](https://github.com/phillipsharring/handlr-mono/tree/main/packages/backend) | composer | PHP framework (Pipe, Handler, Table, Db, EventManager) |

## Adding a module

A module is a single dual-published unit (npm + composer, lockstep version). Install both halves at the matching version:

```bash
composer run module:install -- <name>
```

## License

MIT
