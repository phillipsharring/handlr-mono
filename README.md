# Handlr

A co-versioned framework for building server-driven web apps and static sites: **HTMX + Handlebars + Tailwind** on the front end, a **Pipe + Handler** PHP framework on the back end, and a shared build toolchain. Everything here releases in lockstep.

This is a monorepo. Each package below is published independently to its registry (npm or Packagist); the backend and app skeleton are mirrored to read-only split repos that Packagist tracks.

## Packages

| Package | Dir | Registry | Role |
|---|---|---|---|
| `@phillipsharring/handlr-frontend` | [`packages/frontend`](packages/frontend) | npm | Browser runtime — boosted nav, auth, CSRF, modals, toasts, forms |
| `@phillipsharring/handlr-build` | [`packages/build`](packages/build) | npm | Build tooling — HTML compiler, page baker, Vite dev plugin |
| `phillipsharring/handlr-backend` | [`packages/backend`](packages/backend) | composer | PHP framework — Pipe, Handler, Table, Db, EventManager |
| `phillipsharring/handlr-app` | [`packages/app`](packages/app) | composer | Full-stack app skeleton (`composer create-project`) |
| `@phillipsharring/create-handlr-static` | [`packages/static`](packages/static) | npm | Static-site initializer (`npm create @phillipsharring/handlr-static`) |

## Scaffold an app

```bash
# Full app (frontend + backend)
composer create-project phillipsharring/handlr-app my-app

# Static site (no PHP)
npm create @phillipsharring/handlr-static my-site
```

## Repo layout

```
handlr-mono/
├── packages/
│   ├── frontend/   # @phillipsharring/handlr-frontend  (npm)
│   ├── build/      # @phillipsharring/handlr-build      (npm)
│   ├── backend/    # phillipsharring/handlr-backend     (composer)
│   ├── app/        # phillipsharring/handlr-app         (composer)  - frontend/ + backend/ halves
│   └── static/     # @phillipsharring/create-handlr-static (npm)    - bin/ + skeleton/
├── docs/           # ADRs and design notes
└── package.json    # npm workspaces: ["packages/*"]
```

## Development

npm packages use workspaces natively:

```bash
npm install
npm -w @phillipsharring/handlr-build test
```

The composer packages (`backend`, `app`) are published to Packagist via git-subtree-split mirror repos. Modules live in their own repos (each dual-published, npm + composer, lockstep version).

## Docs

Architecture decisions live in [`docs/adr`](docs/adr). Start with [ADR 0001 — Combine graspr and handlr](docs/adr/0001-combine-graspr-and-handlr.md).

## License

MIT
