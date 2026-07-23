# ADR 0004 — Policy / Invariant / Resolution layer (object-level authorization for Handlr)

- **Status:** Accepted 2026-07-22
- **Date:** 2026-07-22
- **Deciders:** Phillip Harrington
- **Relates to:** `0001-combine-graspr-and-handlr.md` (the framework this extends).
  Implemented on branch `feat/scope-lazy-pipes-policy-layer`.

---

## Context

Handlr apps are multi-tenant in the shared-table sense: one database, many users,
each row owned via a `user_id` column. The wall between User A's data and User B's
is a **code-level ownership check inside each handler** — not infrastructure. Miss
that check in one handler and B reads or mutates A's data.

A local pentest of a consuming app (Reuse Lists, 2026-07-22) confirmed three such
bugs, all the same shape:

| Finding | Endpoint | Root cause |
|---|---|---|
| F-001 (critical) | `DELETE /checklists/{id}` | `findById($id)` → soft-delete, **no ownership check** |
| F-002 (critical) | `PATCH /checklists/{id}` | update scoped by id only, not by owner |
| F-003 (high) | `PATCH /checklists/{id}/archive` | same as F-001 |

Meanwhile the *same app's* item handlers (`toggle`, `skip`, bulk `items`) and its
`share`/`reuse` handlers **did** enforce ownership, via ad-hoc query helpers
(`ChecklistsQuery::canEditBy()` / `isAccessibleBy()`). So the app already had a
hand-rolled policy engine — it was just **opt-in per handler**, and opt-in checks
get forgotten. Three were forgotten.

### Why this is a framework problem

The failure mode is structural, not a one-off. An ownership check that each handler
must *remember* to call will eventually be missed as the surface grows. The right
fix is to make the check **unforgettable** — part of the request's structure, not a
line a developer opts into. That belongs in the framework, not copy-pasted into
every app.

### The missing layer

Handlr already models two of three request-time concerns and conflates the third:

1. **Validation** — is the input well-formed? (covered: `HandlerInput` + validators)
2. **Invariant** — can *anyone* do this right now, given system state / quotas /
   object state? (present, but ad-hoc — bags of `can*` methods, no interface)
3. **Policy** — can *this actor* perform *this action* on *this object*? (**absent
   as a first-class concept** — this is exactly where F-001/2/3 live)

Policies had been proposed twice before and declined each time, on the grounds that
there were "only a few call sites." That argument has expired: a single domain now
has ~15 object-scoped endpoints plus collaborators and admin routes. It is no
longer a few.

## Decision

Add a **Policy / Invariant / Resolution layer** to Handlr, and run all three
request-time guards **in the pipe stack, before the handler**. The handler assumes
its input is valid, its preconditions hold, and the actor is permitted — it does
the work and nothing else.

### The three layers

| Layer | Question | Verb → result | Granularity | Lives |
|---|---|---|---|---|
| Validation | is the input well-formed? | `validate` → valid/invalid | per input | pipe (existing) |
| **Invariant** | can this happen right now? | **`check()` → `?Violation`** | **one class per rule** | pipe |
| **Policy** | may this actor do X to this object? | **`consult()` → `Grant`\|`Deny`** | **one class per resource type**, action-dispatched | pipe |

Naming was chosen deliberately:

- **`consult`** for policy evaluation — you *consult a policy*. It does not collide
  with "authorize"/authZ, and it reads as English. Rejected: `authorize` (collides,
  and it isn't authorization per se), `enforce`/`evaluate` (flat), Laravel's
  `Gate`+`Policy` split (the two words don't go together).
- **`Grant` / `Deny`** for the decision — a clean antonym pair, distinct from the
  evaluation verb. `Decision::grant()` / `deny(reason)` / `orDeny()`.
- **`check()` → `?Violation`** for invariants — `null` is the boring "fine" case;
  a `Violation` is a *thing* that carries the reason. Rejected: `hold` as a verb
  (awkward), a symmetric `Held|Violated` pair (an invariant is "silence, or a
  complaint," not a binary — asymmetry is honest here).

### Distinguish request invariants from domain invariants

- **Request invariants** (quotas, state preconditions — "can this happen now?") →
  pipe, this layer.
- **Domain invariants** ("this aggregate can never be in an illegal state") → live
  inside the Record/domain, always. Not a pipe. Not covered by this ADR.

Auditing existing `*Invariants` bags is expected to reclassify many entries as
policies or quotas.

### Resolution and binding — how a policy gets its object

A policy needs the *loaded* object ("can B edit *this* checklist"). To load it once,
authorize it once, and hand it to the handler without the classic `findById`-then-
forget smell, resolution runs as a pipe:

1. **Resolve** — `Resolver::resolve(Table<T>, id): T`, or throw `RecordNotFound`
   (→ 404). Pure lookup; no auth, no state filtering.
2. **Consult** — `Policy::consult(actor, action, record)->orDeny()` (→ 403).
3. **Bind** — put the record into the **request-scoped container**.
4. The handler type-hints the record (`__construct(ChecklistRecord $checklist)`) and
   receives it by injection.

The handler literally cannot act on an object it did not receive, and only receives
resolved-and-consulted ones. F-001/2/3 become **unrepresentable**.

### The two kernel primitives this requires

**Request-scoped container** — `Container::scope()` returns a child: reads fall
through to the parent, writes stay local and are discarded at end of request.
Autowired classes build in the child so their constructor deps resolve through the
child first (that is what lets a scope-bound record be injected). This beats the
alternatives (see below): no Request mutation, no global-container pollution, and
isolation is *explicit* (drop the child) rather than relying on PHP process death —
so it survives a long-running worker (Swoole/RoadRunner).

**Lazy pipe resolution** — `Pipeline::defer()` resolves a route pipe only when the
chain reaches it, and `Router::dispatch()` opens a per-request scope and defers
route pipes through it. This is a **prerequisite**, not a nicety: `dispatch()`
previously constructed the *entire* chain (including the handler) up front, before
running any pipe — so a record bound mid-chain would not exist when the handler was
built. Lazy construction also means a short-circuiting pipe (auth/policy denial)
never constructs the handler at all — a free correctness + performance win.

### The interfaces (see code for detail)

- `Handlr\Resolution\{Resolver, TableResolver, RecordNotFound}`
- `Handlr\Invariants\{Invariant, Violation}`
- `Handlr\Policies\{Policy, PolicyAction, Decision, PolicyDenied}`
- `PolicyAction` is a **marker interface** implemented by per-domain enums, so
  actions are typed at the call site (`ChecklistAction::Edit`) — dispatchable at a
  junction without being stringly-typed.
- `Table` is annotated `@template T of Record` so resolved records keep their type.

Policy is **one class per resource type**, action-dispatched (`match ($action)`),
because the actions over one resource share context (ownership, collaborators) —
cohesive, not a grab-bag. Invariant is **one class per rule**, because unrelated
rules bundled into a `can*` bag is incidental grouping. The principle: *split by
cohesion, not by count.*

Policy complements coarse RBAC (`RequirePermission`), it does not replace it —
permissions are vertical (does the actor hold a capability), policy is horizontal
(may the actor touch this specific row).

## Consequences

**Positive**
- The "forgot the ownership check" bug class is structurally eliminated for
  resolve-bound routes.
- Ad-hoc `canEditBy`/`isAccessibleBy` query helpers collapse into one `*Policy` per
  resource — one place to read and audit authorization.
- Lazy pipes: denied requests stop constructing handlers; general perf win.
- Scoped container is a reusable primitive (request-lifetime state without leaks),
  not single-purpose.

**Negative / costs**
- Two kernel changes (`Container::scope`, lazy `Pipeline`/`Router`). Covered by
  tests; the lazy change is behind existing dispatch semantics (147 tests green).
- Policy's `consult(object $resource)` can't narrow its param type in the signature
  (PHP contravariance), so implementations keep `object` + an `assert`/`instanceof`
  and lean on the `@template` for static typing. Standard PHP-generics compromise.
- Each app must adopt: write policies, add resolve-pipes, retire ad-hoc checks.

## Alternatives considered

- **Mutate the Request** (attach the record) — loses type safety, invites key
  collisions. Rejected.
- **Pollute the global DI container** (bind the record on the root) — works only
  because PHP dies per request; leaks under a long-running worker. Rejected in
  favor of an explicit scope.
- **A `RequestContext` registry** — either per-type getters (`getChecklist()`, a
  bag that grows forever) or a generic string-keyed map (loses type safety). A
  second registry beside the container that does the same job worse. Rejected;
  the scoped container gives type-safe injection with one concept.
- **Laravel's `Gate` + `Policy`** — the naming pair is incoherent and `Gate` is a
  static facade. Rejected. We keep the `Policy` noun with a `consult` verb.
- **`Container::factory()` for lazy pipes** — returns void, registers by alias,
  mutates container state per pipe. Wrong tool. Laziness comes free from resolving
  `$scope->get($class)` at execution time inside the pipeline closure.
- **Keep policies opt-in per handler** (status quo) — this is precisely what
  produced F-001/2/3. Rejected.

## Follow-ups (not in this ADR)

- Adopt in Reuse Lists: `ChecklistPolicy` + resolve-pipes; retire `canEditBy`/
  `isAccessibleBy`; fix F-001/2/3.
- `ErrorPipe` mapping: `RecordNotFound` → 404, `PolicyDenied` → 403.
- Typed `RouteParams` object to replace the `array $args` bag in the pipe signature.
- Note the long-running-worker assumption where the per-request scope is torn down.
