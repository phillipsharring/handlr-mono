# Routing & Junctions

Routes map an HTTP method + path to a **stack of pipes** ending in a handler. You
build them with a fluent `Router` / `RouteGroup` API, and you compose cross-cutting
pipe stacks once as **junctions** that service providers attach to.

## Verbs

```php
$router->get('/health', [HealthCheck::class]);
$router->post('/users', [ValidateUser::class, CreateUser::class]);
$router->patch('/users/{id:uuid}', [UpdateUser::class]);
$router->delete('/users/{id:uuid}', [DeleteUser::class]);
```

The array is the **pipe stack** for that route, run in order (onion middleware —
see [Core Concepts](./concepts)). The last entry is conventionally the handler.

## Path parameters

Parameters are `{name}` or `{name:type}`:

| Pattern | Matches | Compiles to |
|---|---|---|
| `{id}` | any non-slash segment | `[^/]+` |
| `{id:int}` | integers — `123` | `\d+` |
| `{id:uuid}` | UUIDs — `550e8400-…` | `[0-9a-f-]{36}` |
| `{slug:slug}` | slugs — `my-post` | `[a-z0-9-]+` |
| `{path:[a-z/]+}` | a custom regex | your regex, verbatim |

Typed params are self-documenting **and** disambiguating: a literal `/search` can
never match `/{id:uuid}`, so the two don't shadow each other. Extracted values
arrive in the pipe's `$args` (and on `$request->getRouteParams()`). Registering the
same `METHOD path` twice throws — with the offending provider named (see
[Service Providers](./service-providers)).

## Groups

`group()` shares a prefix and pipe stack across routes; call `end()` to pop back
out. Groups nest, and **a parameter can live in the group prefix** — so object-scoped
route trees don't repeat the id:

```php
$router->group('/checklists')
    ->get('', [ListChecklists::class])           // GET /checklists
    ->post('', [CreateChecklist::class])         // POST /checklists
    ->group('/{id:uuid}')
        ->get('', [GetOne::class])               // GET /checklists/{id}
        ->patch('', [Update::class])             // PATCH /checklists/{id}
        ->delete('', [Delete::class])            // DELETE /checklists/{id}
        ->group('/items')
            ->patch('', [UpdateItems::class])    // PATCH /checklists/{id}/items
            ->patch('/sort', [SortItems::class]) // PATCH /checklists/{id}/items/sort
        ->end()
    ->end()
->end();
```

Pipes are **inherited and merged** — a nested group's routes run the outer pipes
then the inner ones. `through([...pipes])` is a group with no prefix change: a way
to layer pipes on a run of routes without altering their paths.

> [!IMPORTANT]
> Always `end()` a nested group. Without it, subsequent routes attach to the
> nested group instead of its parent.

## Junctions

Real apps declare a few cross-cutting pipe stacks (CORS, session, CSRF, auth) once,
then let each domain's service provider attach its own routes. A **junction** is a
named extension point on a group:

```php
// app/routes.php — declare the junctions once
$router->group('/api', [CorsPipe::class, VerifyOriginPipe::class])
    ->junction('api.basic')                       // /api, no session/auth
    ->through([StartSessionPipe::class])
        ->junction('api.public')                  // + session, no auth
    ->end()
    ->through([StartSessionPipe::class, SessionAuthPipe::class, EnsureCsrfTokenPipe::class])
        ->junction('api.session')
        ->through([VerifyCsrfTokenPipe::class, RequireAuthPipe::class])
            ->junction('api.authed')              // logged-in
            ->group('/admin', [new RequirePermissionPipe('admin.access')])
                ->junction('api.admin')           // admin-only
            ->end()
        ->end()
    ->end()
->end();
```

```php
// app/SomeServiceProvider.php — fill a junction from anywhere
public function routes(Router $router): void
{
    $router->intoJunction('api.authed')
        ->group('/things')
            ->get('', [ListThings::class])
            ->post('', [CreateThing::class])
        ->end();
}
```

`intoJunction('name')` returns the group declared under that name, with its full
prefix and pipe stack — so anything attached inherits them. This is the seam that
lets a module ship routes without redeclaring your session/CSRF/auth stack.
Declaring a junction name twice, or looking up an unknown one, throws with the list
of available names. See [Service Providers](./service-providers) for load order.

## Global pipes

`$router->addGlobalPipe($pipe)` runs a pipe on **every** route, outermost first —
this is where the Kernel wires `ErrorPipe` (renders any thrown `RequestException`
at its status) and `LogPipe`.

## Resolve & authorize a route's object

Object-scoped routes can declare the record they operate on and the policy that
guards it. The framework then resolves the record, consults the policy, and binds
the record for the handler — before the handler runs. Full model in
[Authorization](./authorization).

### Per route

```php
$router->intoJunction('api.authed')
    ->patch('/checklists/{id:uuid}', [UpdateChecklist::class])
        ->resolves(ChecklistRecord::class)     // load by {id}, bind for the handler
        ->policy(ChecklistAction::Edit);       // consult ChecklistPolicy with Edit → 403 on deny
```

`resolves(RecordClass, $param = 'id')` names the record to load and bind (and the
route param holding its id). `policy(Action)` must follow `resolves()`; it consults
the record's registered policy and short-circuits `403` on denial. `resolves()`
alone binds the record with no authorization check.

### Group-level defaults

Call `resolves()`/`policy()` on a **group, before adding any route**, to set a
default that every route in the group (and nested groups) inherits — unless a route
overrides it:

```php
->group('/checklists/{id:uuid}')
    ->resolves(ChecklistRecord::class)->policy(ChecklistAction::View)  // group default
    ->get('', [GetOne::class])                    // inherits View
    ->post('/reuse', [Reuse::class])              // inherits View
    ->patch('', [Update::class])
        ->policy(ChecklistAction::Edit)           // per-route override
    ->group('/items')
        ->policy(ChecklistAction::Edit)           // subgroup default (record still inherited)
        ->patch('', [UpdateItems::class])
        ->patch('/{item_id:uuid}/toggle', [Toggle::class])
            ->policy(ChecklistAction::Check)      // override
    ->end()
->end();
```

The disambiguation is simple: `->group(...)` returns a fresh group with no routes
yet, so `resolves()`/`policy()` on it set the **group default**; once a route has
been added, they attach to **that route**. Register the group default *before* the
group's routes. `policy()` as a group default with no prior `resolves()` throws.

## How dispatch runs a route

`Router::dispatch()` matches the request, opens a per-request
[container scope](./concepts#the-container-scope), lays the global pipes, defers the
route pipes (they resolve lazily, from the scope, only when reached), injects the
`ResolvePipe` just before the handler when the route has resolve metadata, then runs
the onion. A short-circuiting pipe means the handler is never even constructed.

## See also

- [Core Concepts](./concepts) — pipes, the onion, lazy resolution, the container scope.
- [Authorization](./authorization) — resolution, policies, invariants.
- [Service Providers](./service-providers) — where routes, events, and bindings register.
