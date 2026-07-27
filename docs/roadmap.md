# Roadmap

Where Handlr is going. This is a living page — a friendly view of what's shipped,
what's in progress, and what's planned. It's direction, not a set of promises or
dates. Priorities shift as real apps surface real needs.

## Recently shipped

- **Object-level authorization.** Policies, resolution, and invariants — routes declare
  the record they operate on and the policy that guards it, and the framework resolves,
  authorizes, and binds it before the handler runs. See [Authorization](/backend/authorization).
- **Group-level `resolves()` / `policy()`.** Declare an object-scoped route tree's
  resolve + policy once for the whole group instead of on every route.
- **Declarative frontend behavior.** Handle form responses and small click behaviors with
  `data-*` attributes instead of hand-written scripts. See [Declarative Behavior](/frontend/declarative).
- **Request identity.** Every request carries a correlation id (in logs and an
  `X-Request-Id` header), resolvable anywhere — the groundwork for request-scoped features.
- **Build ergonomics.** Extensionless routes, optional minification, and cleaner build
  output.

## In progress

- **Undo.** An opt-in "undo the last thing I did" capability, built on the new request
  identity and a lightweight change-capture seam at the data layer. Shipping as a module
  so apps that want it opt in, and everyone else pays nothing. First target: the classic
  "Deleted. [Undo]" toast.

## Planned

- **Cache.** A framework cache primitive with a swappable driver (database and in-memory
  to start), configured in one place — useful for response and query caching, and for
  short-lived state.
- **Translations (i18n).** A first-class translation layer: message catalogs, a translator
  you resolve from the container, and locale resolution for both server-rendered and
  client-side strings.
- **Audit log.** A comprehensive, permanent record of who changed what — a separate module
  from undo, built on the same data-layer seam.
- **Multi-database support.** Most of this is already in your hands: the framework
  hand-writes only trivial, portable SQL, and any real query already lives in your own
  query classes — so targeting PostgreSQL or SQLite is mostly a matter of writing those
  queries for your engine. Planned: extract the small built-in SQL into per-engine dialect
  classes so the trivial part is a drop-in too. (Still not a query builder — see
  [Principles](/principles#what-we-deliberately-leave-out).)
- **Straight-HTMX as a first-class path.** Server-rendered fragments, scaffolded and
  documented end to end, alongside the default API + client-side-templates approach.
- **A "one of everything" example module.** A small but complete app — a record, a form, a
  list with pagination, an admin page, an event — installable as a single module so a new
  developer can see every capability wired together in context.
- **Recipe repos: one path, start to finish.** Small public repos that each show a single
  mechanic end to end — not demo apps, but the moves themselves: a static site that later
  adds the full framework; building a module; installing and using someone else's module;
  where your code plugs into the framework. Each goes from a working Handlr state to another
  working Handlr state, so you can see exactly what changes.

## Exploring

- **Redo and history.** Once undo lands, multi-step undo/redo and a per-record timeline are
  natural follow-ons.

---

Handlr is open source. If something here matters to you — or something you need isn't here —
issues and contributions are welcome on
[GitHub](https://github.com/phillipsharring/handlr-mono).
