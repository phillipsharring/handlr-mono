# Changelog

## Unreleased

### Added

- **Fluent route policy binding** — `->resolves(RecordClass)` and `->policy(Action)`
  chain off a route declaration as an alternative to the `new Resolves(...)` spec
  arg. They attach metadata to the most recently registered route and return the
  group/router, so route chaining continues:

  ```php
  $router->delete('/checklists/{id:uuid}', [DeleteChecklist::class])
      ->resolves(ChecklistRecord::class)
      ->policy(ChecklistAction::Delete);
  ```

  `policy()` must follow `resolves()`. The spec-arg form still works.

## 0.12.0

### Added

- **Route policy binding** — a route can declare `new Resolves(RecordClass, Action)`
  as trailing metadata (`->delete('/checklists/{id:uuid}', [Handler::class],
  new Resolves(ChecklistRecord::class, ChecklistAction::Delete))`). `Router::dispatch()`
  injects a generic `ResolvePipe` just before the handler (after auth pipes) that
  resolves the record via its Table, consults its Policy with the action, and binds
  it into the request scope for the handler to receive by type hint.
- **`ResolutionRegistry`** — maps `RecordClass → [Table, Policy]` (populated in a
  provider's `boot()`), so routes name only the record + action. Registered as a
  singleton by the Kernel, along with a default `Resolver` (`TableResolver`).

### Changed

- `RecordNotFound` and `PolicyDenied` now extend `RequestException`, so the global
  `ErrorPipe` renders them as 404 / 403 wherever a record is resolved or a policy
  consulted (including `Decision::orDeny()` inside a handler). `RequestException` is
  no longer `final`.

## 0.11.0

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
