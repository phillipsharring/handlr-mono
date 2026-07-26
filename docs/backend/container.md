# The Container & Request Scope

Handlr's container is a small autowiring DI container. It has the usual
registration methods, reflection-based constructor injection, and one feature that
the rest of the framework leans on hard: **request scopes**.

## Registration

```php
$container->bind(LoggerInterface::class, Logger::class);   // interface → concrete
$container->singleton(Db::class, $db);                     // shared instance
$container->factory(Report::class, fn() => new Report());  // new every resolution
$container->alias('log', LoggerInterface::class);          // second name

$logger = $container->get(LoggerInterface::class);
```

`bind()` dispatches by argument type: a callable becomes a `factory`, an object
becomes a `singleton`, a class-string sets an interface→concrete map. Both classes
in a bind are checked to exist.

## Autowiring

`get()` resolves a class by reflecting its constructor and resolving each typed
parameter from the container, recursively. Rules:

- **Class-typed** params resolve from the container.
- **Builtin or untyped** params must have a default (otherwise a `ContainerException`).
- **Union-typed** params must have a default.
- A **circular dependency** guard throws rather than looping.
- Resolving `Container::class` / `ContainerInterface::class` returns the container itself.

Two convention hooks exist beyond the constructor: a setter `inject(...)` (used only
when the constructor has no required container params) and a no-arg `init()`
post-construction hook.

## `has()` is deliberately strict

`has($abstract)` is true **only for explicit registrations** (a bind, singleton,
factory, or alias) — it reads through to a parent container but returns `false` for
a class that merely *could* be autowired. This strictness is what makes scopes work.

## Request scopes

`$container->scope()` returns a child container whose `$parent` is the current one.
The Kernel-level container holds your app's shared services; a scope is a throwaway
overlay for a single request.

```php
$scope = $container->scope();
$scope->singleton(ChecklistRecord::class, $record);  // request-only binding

$handler = $scope->get(DeleteChecklist::class);       // receives $record by type hint
```

The resolution rule inside a scope:

> If the class is **not** registered locally and the **parent has it**, delegate to
> the parent (shared services stay shared, single-instance). Otherwise the **child
> builds it itself** — so its dependencies resolve *through the child* and can pick
> up anything bound locally in the scope.

That second clause is the whole trick. A handler isn't registered anywhere, so the
scope builds it locally; building it locally means its `ChecklistRecord` constructor
param resolves against the scope, where a pipe bound the resolved record. A shared
service like `Db` *is* on the parent, so it resolves once, up there, and every scope
shares it.

```php
// A pipe hands a value to everything downstream, by type:
final class BindCurrentTeam implements Pipe
{
    public function __construct(private ContainerInterface $container, private TeamsTable $teams) {}

    public function handle(Request $req, Response $res, array $args, callable $next): Response
    {
        $team = $this->teams->findById($args['team_id']);
        $this->container->scope();                 // (dispatch already opened one)
        // ...bind into the active request scope...
        return $next($req, $res, $args);
    }
}
```

In practice you rarely write scope plumbing yourself — the
[`ResolvePipe`](./authorization) does exactly this to deliver a resolved, authorized
record to a handler. But the mechanism is general: any pipe can bind a
request-lifetime instance, and any downstream constructor can receive it by type
hint, with no service-locator lookup and no leakage across requests.

## Why scopes instead of a request bag

The alternative — stuffing values into a `$request` attributes bag and pulling them
out by string key in the handler — is stringly-typed and easy to forget. Scope
binding keeps it **typed**: the handler declares `ChecklistRecord $checklist` in its
constructor, and either the framework provides it or construction fails loudly. It
also composes with laziness — because the handler is `defer()`'d, a pipe that
short-circuits (auth/policy denial) means the handler, and its demand for the bound
record, never materialize.

## See also

- [Core Concepts](./concepts) — the lifecycle and lazy pipe resolution.
- [Authorization](./authorization) — the `ResolvePipe` that binds records into the scope.
