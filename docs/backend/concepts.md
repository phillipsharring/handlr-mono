# Core Concepts

Handlr is a middleware-style ("pipe") framework. One request flows through a small,
readable set of abstractions:

```
Kernel  →  Router::dispatch()  →  request-scoped Container  →  Pipeline of Pipes  →  Handler
```

Everything below the pipe layer is HTTP-agnostic: the same **Handler** serves an
HTTP request and an event dispatch. The two ideas that make the whole thing tick —
**laziness** and **scope binding** — are covered at the end.

## Pipe

A **Pipe** is onion middleware. It sees the HTTP `Request`/`Response` and either
passes control on or short-circuits.

```php
interface Pipe
{
    public function handle(Request $request, Response $response, array $args, callable $next): Response;
}
```

- Call `$next($request, $response, $args)` to continue to the next pipe.
- Return a `Response` **without** calling `$next` to short-circuit (auth failed,
  validation failed, cache hit) — nothing downstream runs.

Pipes are where HTTP concerns live: CORS, session, CSRF, auth, validation,
resolution. The `Pipeline` folds a route's pipe list into a nested chain of
closures and runs it (`src/Core/Routes/Pipeline.php`).

## Handler

A **Handler** is one unit of business logic. It has no HTTP awareness — no
`Request`, no `Response`.

```php
interface Handler
{
    public function handle(array|HandlerInput $input): ?HandlerResult;
}
```

The same handler runs whether the input came from an HTTP pipe or an
[event dispatch](./events). That symmetry is the point: business logic is written
once and reused.

## HandlerInput

A **HandlerInput** is a typed input object — the validated, sanitized shape a
handler expects, built from the raw request body (plus route params and any
server-set fields).

```php
interface HandlerInput
{
    public function __construct(array $body = [], ?Validator $validator = null);
}
```

A pipe usually builds one with `ValidatedInputFactory` (which merges route params +
parsed JSON body + server extras like a `user_id`, runs the [Validator](./validation),
and hands back `[$input, $errors]`). If `$errors` is non-empty the pipe returns a
`422` and the handler never runs; otherwise it calls `$handler->handle($input)`.

## HandlerResult

A **HandlerResult** is the structured outcome of a handler — never static, always an
injected instance:

```php
$this->result->ok($data, $meta);       // success
$this->result->fail($errors, $meta);   // failure
```

It carries `success`, `data`, `errors`, `meta`. The calling pipe maps it to a
`Response` (usually via the [Presenter](./api-responses) envelope). Keeping the
result a value — not a thrown exception or an echoed string — is what lets the same
handler feed both HTTP and events.

## Listener

A **Listener** is just a Handler registered on an event name. Same interface; it
typically returns `null`. This is why business logic composes cleanly across HTTP
and events — see [Events & Listeners](./events).

## The request lifecycle

1. **Kernel boot** (`src/Core/Kernel.php`) — registers core services (Logger, Db,
   Session, Mailer, the `ResolutionRegistry` + `Resolver`), adds the global pipes
   (`ErrorPipe`, `LogPipe`), then runs the provider lifecycle: `boot()` all
   providers → load `app/routes.php` (which *declares* junctions) → apply provider
   routes (which *fill* them). Order matters — junctions must exist before providers
   attach to them.
2. **Dispatch** (`Router::dispatch()`) — match the route, extract params, open a
   per-request container scope, build the pipeline, run the onion, return the
   `Response`.
3. **Teardown** — the request scope is discarded when dispatch returns; nothing
   leaks into app-level bindings or the next request.

## The container scope

`Router::dispatch()` opens a **request-lifetime child container** with
`$container->scope()`. Shared services (Db, Logger, tables) resolve from the parent;
anything a pipe binds locally is visible only for this request and thrown away after.

This child is how a pipe hands data to a handler by type. When a pipe calls
`$scope->singleton(ChecklistRecord::class, $record)`, a handler whose constructor
type-hints `ChecklistRecord` receives that exact instance — because the child scope
autowires *through itself*. The [ResolvePipe](./authorization) uses this to deliver
a resolved, authorized record to the handler. See the [Container](./container) page
for the mechanics.

## Laziness

Route pipes and the handler are **deferred** — `Pipeline::defer(fn => $scope->get($pipe))`
resolves a pipe only when the chain reaches it. So if an auth, CSRF, or policy pipe
short-circuits, the downstream pipes and the handler are **never constructed**. This
isn't just an optimization: it's what makes "an unauthorized request cannot build
the handler" a structural guarantee rather than a convention.

## Explicit, not magic

Constructor injection everywhere, no static global state, no facades. Each
abstraction is a small interface you can read top to bottom. When you need to know
what a route does, you follow its pipe list; when you need to know what a pipe
needs, you read its constructor. Nothing resolves out of a hidden global.

## See also

- [Routing & Junctions](./routing) — building the pipe stacks.
- [Container](./container) — DI and the request scope in depth.
- [Authorization](./authorization) — resolve → consult → bind.
- [Validation](./validation) — building typed, validated input.
