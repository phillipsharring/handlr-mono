# Backend Overview

`handlr-backend` (`phillipsharring/handlr-backend`, namespace `Handlr\`) is a
lightweight, middleware-style PHP framework built around the **Pipe + Handler**
pattern. It separates HTTP concerns from business logic, and makes object-level
authorization structural rather than something you remember to check.

The design bias is **explicit over magic**: constructor injection everywhere, no
static global state, small interfaces you can read top to bottom. It is not a
framework pretending to be Laravel — there are no facades and nothing resolves out of
a hidden global.

## The core abstractions

- **Pipe** — onion middleware that sees `Request`/`Response`. Auth, validation, CORS,
  CSRF, resolution. Short-circuits by returning a `Response` instead of calling `$next`.
- **Handler** — pure business logic. Receives a typed `HandlerInput`, returns a
  `HandlerResult`. No HTTP awareness — the same handler serves an HTTP request *and* an
  event dispatch.
- **HandlerInput** — a validated, sanitized input object. Identical whether the input
  came from an HTTP request or an event.
- **HandlerResult** — a structured outcome: `$this->result->ok($data)` or
  `$this->result->fail($errors)`. A value, never a thrown string.

Below the pipe layer, everything is Handler/HandlerInput.

## The request path

```
Kernel  →  Router::dispatch()  →  request-scoped Container  →  Pipeline of Pipes  →  Handler
```

Two ideas hold it together:

- **Laziness** — route pipes and the handler are deferred, constructed only when the
  chain reaches them. An auth or policy short-circuit means the handler never exists.
- **Scope binding** — each request gets a throwaway child container. A pipe can bind a
  value into it (a resolved record); a downstream handler receives that value by type
  hint. No request bag, no string keys.

Read [Core Concepts](./concepts) for the full flow.

## Where to go next

| Page | What it covers |
|---|---|
| [Core Concepts](./concepts) | Pipe / Handler / Input / Result, the lifecycle, laziness |
| [Container](./container) | DI and the request scope |
| [Routing & Junctions](./routing) | routes, groups, junctions, resolve/policy binding |
| [Authorization](./authorization) | resolve → consult → bind: policies, resolution, invariants |
| [Validation](./validation) | the rule/sanitizer engine |
| [Database](./database) | `Table`, `Record`, `Db`, `Query`, migrations |
| [API Responses](./api-responses) | the JSON envelope (Presenter) |
| [Auth](./auth) | session auth + coarse permissions |
| [Events & Listeners](./events) | the synchronous event bus |
| [Service Providers](./service-providers) | how features register themselves |
| [CLI & Makers](./cli) | `composer run make:*`, `migrate`, `seed` |

## Install

```bash
composer require phillipsharring/handlr-backend
```

Or scaffold a full app (backend + frontend) with
`composer create-project phillipsharring/handlr-app my-project` — see
[Installation](/getting-started/installation).
