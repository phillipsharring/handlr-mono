# Principles

The *why* behind Handlr. These are the values the framework is built on — the
reasons things are shaped the way they are. When a design question comes up, these
are how it gets answered. They're also a fair way to decide whether Handlr is for
you: if you nod along, you'll be at home; if you don't, that's useful to know early.

Where a principle gets dense, there's an **In plain terms** note underneath it.

## The backend: explicit, not magic

**Explicit over magic.** Constructor injection everywhere, no static global state, no
facades, nothing resolving out of a hidden global. Every abstraction is a small
interface you can read top to bottom: to know what a route does, follow its pipe
list; to know what a pipe needs, read its constructor. It is deliberately not a big
MVC framework — no facades, no global helper soup, no folder of conventions you have
to memorize before the first route works.

> **In plain terms:** if you want to know how a request is handled, you can read it.
> Follow the route to its list of pipes; open a pipe and its constructor lists
> everything it depends on. Nothing is wired up by an invisible convention.

**Prefer instances; statics are the rare exception.** State and behavior live on
injected instances, not static classes. You almost never need a static method — there
are two containers ready to hand you whatever you need (a global one for shared
services, a per-request scope for request-lifetime things), so `SomeThing::doIt()` is
the exception rather than the reflex. (The codebase has a couple, on purpose; the point
is that they're rare and deliberate.) The real payoff is testability: an injected
dependency is swapped for a fake in one line, where a static call is a hard-wired global
you have to work around.

**Pipes and handlers resolve lazily.** A route's pipes and its handler aren't built up
front — each is constructed only when the chain actually reaches it. That buys two
things. It's a free performance win: if an upstream pipe short-circuits (an auth, CSRF,
or policy denial), nothing downstream is ever constructed, so a rejected request does
almost no work. And *when you gate the route* — put an auth or policy pipe in front —
the fact that the handler is never built on a denial makes "an unauthorized request
can't reach the handler" a structural property, not a check every handler has to
remember. The safety is real, but it follows from you putting the gate there; the
framework makes that cheap and reliable, not automatic.

**Bind to the request scope; resolve by type.** Every request runs inside its own
throwaway child container — a *scope*. Shared services (the database, the logger) fall
through to the parent and stay shared; anything a pipe puts into the scope lives only
for that request and is discarded when it ends. The point is how one pipe hands work to
another: a pipe binds an object into the scope *by its type*, and a later pipe (or the
handler) names that type in its constructor and receives that exact object. No "request
bag" of stringly-keyed attributes to rummage through, and nothing bleeds from one
request into the next — which matters most under long-running worker processes, where
there's no fresh PHP process per request to hide behind.

> **In plain terms:** a pipe can prepare something — say, load and authorize the record
> for `/things/{id}` — and drop it in a per-request box. The handler just asks for that
> type in its constructor and it's there, already loaded and already checked.

**Local constraints in service of the whole.** Some pieces are deliberately strict so a
larger mechanism stays correct. The container's `has()`, for instance, reports true only
for things explicitly registered — not everything it *could* autowire — and that
narrowness is exactly what lets a scope tell "mine" from "the parent's." The aim is for
small, local rules to keep the bigger machine sound, so that where we've gotten it right
you don't have to think about it.

## Authorization is structural

**A forgotten check is an IDOR bug, so the check is made unforgettable.** The wall
between one tenant's data and another's is a code-level ownership check, and a check
each handler must *remember* to run eventually gets forgotten. So the framework
resolves the record, consults its policy, and binds it in — before the handler runs.
The handler cannot act on an object it didn't receive, which makes "forgot the
ownership check" unrepresentable for routes that use this pattern.

> **In plain terms:** for a route like "edit *this* checklist," you don't load the
> checklist inside the handler and hope you remembered to check the owner. You declare
> the record and the rule on the route; the framework loads it, checks it, and only then
> runs your code — with the loaded record already in hand.

**Resolve → consult → bind.** Resolution is a pure lookup (record or 404). Policy
consultation grants or denies (denied → 403). Binding hands the authorized record to
the handler. Load once, authorize once, hand it over — instead of the
`findById`-then-forget smell.

**Split by cohesion, not by count.** Three distinct questions: is the input well
formed (validation), can this happen right now (invariant — quotas, state), and may
*this actor* do *this action* on *this object* (policy). A policy is one class per
resource type because its actions share context; an invariant is one class per rule
because bundling unrelated rules into a `can*` bag is incidental grouping.

**Object policy complements RBAC, it doesn't replace it.** Coarse permissions are
vertical (does the actor hold a capability); object policy is horizontal (may the
actor touch this specific row). Both layers stay.

**Names that read like English.** You *consult* a policy and it *grants* or *denies*.
An invariant `check()` returns nothing when all is well, or a `Violation` that carries
its own reason. Deliberate, honest naming beats clever or borrowed vocabulary.

## Business logic is HTTP-agnostic

**Results are values, not thrown strings.** A Handler has no `Request`/`Response`
awareness — it takes a validated input and returns a structured result *value*, leaving
the caller to decide what to do with it: an HTTP pipe maps it to a response, while an
event dispatch may not need the value at all. That HTTP-agnosticism is exactly what lets
one handler serve both an HTTP request and an event dispatch — a listener is just a
handler registered on an event name.

## The frontend is a toolkit, not a framework

**Apps call it; it does not call apps.** The frontend is a composable toolkit on top of
HTMX, not an inversion-of-control framework. Everything added to it defaults to the
toolkit shape rather than drifting back toward framework coupling.

**Pure barrel, opt-in batteries.** Importing the package runs nothing — you take only
what you call, and it tree-shakes. A separate `./init` entry wires the default
listeners for those who want batteries. New capability is a named export or an explicit
`initX()`, never a module that self-registers on import.

> **In plain terms:** `import` the package and nothing happens until you call something,
> so you don't pay for features you don't use. Want the batteries-included setup? Import
> `./init` and the common wiring turns on.

**One global.** The package claims exactly one global (`window.htmx`, a single shared
instance). It does not create `window.App`; your app builds that namespace and hangs on
it only what it wants.

**Declarative behavior over hand-rolled script; fill HTMX's gaps, don't wrap it.** HTMX
makes the request declarative; this layer makes response-handling and small click
behaviors declarative too, through a few `data-*` attributes, with a thin imperative
registry as the escape hatch. Reach for a built-in HTML/attribute behavior first; write
script only when the attribute layer can't express it.

**Straight-HTMX is a supported option; API/JSON is the default.** Server-rendered
fragments are a blessed, scaffolded path you can reach for, without disturbing the
default.

## Packaging and boundaries

**The framework owns mechanism, not your data.** Core provides the seams — a request
identity, hooks at the data layer, authorization primitives — and stays out of your
tables. A feature that needs to own a table (undo, an audit log) is a **module** — or
just your own app code, which shares the same shape (a service provider) — not a dormant
core migration behind a flag. Core exposes the seam at zero cost when unused; any app
opts a feature in with one install and pays nothing until it does. Clean schema
ownership, lean core.

**Domain-driven by default, but your layout is yours.** The recommended shape is to group
a feature's pieces — its record, table, queries, pipes, handlers, policy, and listeners —
in one domain folder, with a **service provider** tying it to the framework (routes,
events, bindings). That service provider is the *one* thing the framework actually
requires; a module and your own app code share exactly that shape, which is why a feature
can start as app code and graduate to a module without changing how it's wired. Everything
else is convention — organize by type (`Records/`, `Tables/`, `Pipes/`) instead of by
domain if you prefer, the framework has no opinion about your folders. We reach for DDD
because it keeps a feature's files together and its provider easy to find, not because the
framework enforces it.

**Co-versioned lockstep; a module is one dual-published unit.** The frontend runtime and
backend framework are released in lockstep, so their versions can't drift. A module
ships as a single repo with both halves at the same version, auto-discovered on both
sides — no "did I install both halves at matching versions?"

**Build and runtime are separate, and dev matches prod.** The build tool (compile HTML,
no JS runtime) is deliberately separate from the runtime, so a static site can depend on
the build package alone and add the runtime later against identical output. The build's
dev server and production bake feed one rendering core, precisely so they produce the
same HTML.

**Runtime code never imports `node:*`.** Browser-side code and build-time filesystem
code live in separate exports. Mixing them is the exact bug class that once broke a
production build, so the boundary is codified.

> **In plain terms:** code that runs in the browser and code that runs at build time
> (which can touch the filesystem) are kept in separate files, so a filesystem call can't
> accidentally get shipped to the browser.

**Known debt is named, not normalized.** Where older code self-registers on import, that's
recorded as debt with a stated direction, so new code sets the good precedent instead of
growing the pile.

## What we deliberately leave out

Handlr says no to some things on purpose. Usually it isn't "that's hard" (though
sometimes it is) — it's "there's already a simple, honest way to do this, and the
abstraction would hide more than it helps."

**No query builder.** The framework assembles only simple, boring SQL — basic CRUD and
lookups. Anything past that goes in a query class, where you write real SQL. That's the
right tool for the job: SQL isn't the enemy in a database-backed app, and a fluent
builder that only approximates it tends to leak the moment a query gets interesting.
Write the query, keep it readable, move on.

**No heavyweight ORM.** A record is plain data and a table is a thin gateway. There's no
lazy-loading relation graph quietly firing extra queries behind a property access — you
can see what hits the database, because you wrote it.

**Multi-database is mostly already yours.** The framework hand-writes only trivial,
portable SQL, and every non-trivial query already lives in *your* query classes — so
pointing an app at PostgreSQL or SQLite is largely a matter of writing those queries for
your engine. A first-party dialect layer for the small built-in SQL is
[on the roadmap](/roadmap), but the way to get there is not a query builder.

---

*Where these come from:* the [Architecture Decision Records](https://github.com/phillipsharring/handlr-mono/tree/main/docs/adr)
(ADR 0001–0004) capture the debates and trade-offs behind them in full.
