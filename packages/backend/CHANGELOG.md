# Changelog

## Unreleased

### Added

- **Request-scoped container** — `Container::scope()` returns a child that reads
  through to its parent but keeps writes local, discarded at end of request. Lets
  a pipe bind a request-lifetime instance (e.g. a resolved Record) that downstream
  handlers receive by type hint, without leaking into app-level bindings or across
  requests.
- **Lazy pipe resolution** — `Pipeline::defer()` resolves a route pipe only when
  the chain reaches it. `Router::dispatch()` now opens a per-request scope and
  defers route pipes through it, so a short-circuiting pipe (auth/policy denial)
  means downstream handlers are never constructed.
- **Resolution layer** — `Resolver` / `TableResolver` (findById-or-throw) and
  `RecordNotFound`, for turning a route `{id}` into its record.
- **Invariants layer** — `Invariant` interface + `Violation`, one class per rule.
- **Policies layer** — `Policy` interface (object-level authorization),
  `PolicyAction` marker, `Decision` (`grant`/`deny`/`orDeny`), and `PolicyDenied`.
- `Table` is now annotated `@template T of Record`, so `findById()` and a
  `Table<T>`-typed `Resolver::resolve()` carry the concrete record type.

## 0.1.0

Initial release. Nothing changed, except everything.
