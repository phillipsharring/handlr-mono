# Frontend Overview

> [!NOTE] Draft
> This page is a stub. Content coming.

The frontend is two layers:

1. **handlr-build** (`@phillipsharring/handlr-build`) — Vite + Tailwind + build scripts that compile layouts, pages, and components into static HTML. No JS runtime required. Usable standalone for static sites.
2. **handlr-frontend** (`@phillipsharring/handlr-frontend`) — the runtime on top: HTMX (json-enc + client-side-templates), Handlebars, auth, modals, CSRF, boosted navigation. For apps with a backend API.

## To cover

- When to use static-only vs the full runtime
- How the two layers compose
- Peer dependencies
