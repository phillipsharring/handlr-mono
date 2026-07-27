# Principles

The *why* behind Handlr. These are the values the framework is built on — the
reasons things are shaped the way they are. When a design question comes up, these
are how it gets answered. They're also a fair way to decide whether Handlr is for
you: if you nod along, you'll be at home; if you don't, that's useful to know early.

## The backend: explicit, not magic

**Explicit over magic.** Constructor injection everywhere, no static global state, no
facades, nothing resolving out of a hidden global. Every abstraction is a small
interface you can read top to bottom: to know what a route does, follow its pipe
list; to know what a pipe needs, read its constructor. It is deliberately not a
framework pretending to be Laravel.

**Instances over statics — never a static method.** State and behavior live on injected
instances, not static classes or methods. Two containers stand ready to hand you what
you need: a global one for shared services and a per-request scope for request-lifetime
things, so reaching for `SomeThing::doIt()` is never necessary. The payoff is not just
consistency, it's **testability** — an injected dependency is trivially swapped for a
fake in a test, while a static call is a hard-wired global you have to fight. If you find
yourself writing a static method, stop and inject an instance instead.

**Laziness is a correctness guarantee, not just an optimization.** Route pipes and the
handler are constructed only when the chain reaches them. A short-circuiting auth,
CSRF, or policy pipe means the handler is *never built*. That's what makes "an
unauthorized request cannot construct the handler" a structural property rather than
a convention you hope everyone follows. (The free performance win — denied requests
do no work — is a bonus.)

**Scope binding is the injection mechanism.** Each request opens a throwaway child
container. Shared services fall through to the parent; anything a pipe binds locally
is visible only for that request and discarded at the end. A pipe binds a value by
type, a downstream handler receives it by type hint. No request bag, no string keys,
no leakage between requests.

**Small constraints that make big mechanisms correct.** The container's `has()` is
deliberately narrow — true only for explicit registrations — precisely so scope
delegation works. Handlr chooses intentional, local strictness to keep the larger
machine sound.

## Authorization is structural

**A forgotten check is an IDOR bug, so the check is made unforgettable.** The wall
between one tenant's data and another's is a code-level ownership check, and a check
each handler must *remember* to run eventually gets forgotten. So the framework
resolves the record, consults its policy, and binds it in — before the handler runs.
The handler cannot act on an object it didn't receive, which makes "forgot the
ownership check" unrepresentable for resolve-bound routes.

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
awareness — it takes a validated input and returns a structured result. The same
handler serves an HTTP request and an event dispatch; a listener is just a handler on
an event name. Keeping the result a value is exactly what lets one piece of logic feed
both paths.

## The frontend is a toolkit, not a framework

**Apps call it; it does not call apps.** The frontend is a composable toolkit on top of
HTMX, not an inversion-of-control framework. Everything added to it defaults to the
toolkit shape rather than drifting back toward framework coupling.

**Pure barrel, opt-in batteries.** Importing the package runs nothing — you take only
what you call, and it tree-shakes. A separate `./init` entry wires the default
listeners for those who want batteries. New capability is a named export or an explicit
`initX()`, never a module that self-registers on import.

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
tables. A feature that needs to own a table (undo, an audit log) is a **module**, not a
dormant core migration behind a flag. Core exposes the seam at zero cost when unused;
any app opts a feature in with one install and pays nothing until it does. Clean
schema ownership, lean core.

**Co-versioned lockstep; a module is one dual-published unit.** The frontend runtime and
backend framework are released in lockstep, so their versions can't drift. A module
ships as a single repo with both halves at the same version, auto-discovered on both
sides — no "did I install both halves at matching versions?"

**Build and runtime are separate, and dev matches prod.** The build tool (compile HTML,
no JS runtime) is deliberately separate from the runtime, so a static site can depend on
the build package alone and add the runtime later against identical output. The build's
dev server and production bake feed one rendering core, precisely so they produce the
same HTML.

**Runtime code never imports `node:*`.** Browser-side code and build-time
filesystem code live in separate exports. Mixing them is the exact bug class that once
broke a production build, so the boundary is codified.

**Known debt is named, not normalized.** Where older code self-registers on import, that's
recorded as debt with a stated direction, so new code sets the good precedent instead of
growing the pile.

---

*Where these come from:* the [Architecture Decision Records](https://github.com/phillipsharring/handlr-mono/tree/main/docs/adr)
(ADR 0001–0004) capture the debates and trade-offs behind them in full.
