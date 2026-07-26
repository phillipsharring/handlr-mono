# Service Providers

A **service provider** is how a feature registers itself with the framework: its
bindings, routes, events, migrations, and config defaults, all in one class. Providers
are what make a feature (or a whole [module](/modules/)) self-contained — add the
provider, and everything wires up.

## Lifecycle

`ServiceProvider` (`src/Core/ServiceProvider.php`) has ordered hooks. The registry
runs them in a fixed order across *all* providers, which is why the phases are split:

```php
final class ChecklistsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind only. No I/O, no resolving from the container yet.
        $this->container->bind(ChecklistsQuery::class, ChecklistsQuery::class);
    }

    public function boot(): void
    {
        // Runtime wiring. All providers have registered by now, so resolving is safe.
        $this->container->get(ResolutionRegistry::class)->for(
            ChecklistRecord::class,
            ChecklistsTable::class,
            ChecklistPolicy::class,
        );
    }

    public function routes(Router $router): void
    {
        $router->intoJunction('api.authed')
            ->group('/checklists')
                ->get('', [GetChecklistsList::class])
                ->post('', [PostCreateChecklist::class])
            ->end();
    }

    public function events(): array
    {
        return [
            'checklist.item.checked' => [MarkChecklistCompletedListener::class],
        ];
    }
}
```

| Hook | Runs | For |
|---|---|---|
| `register()` | first, for every provider | bindings only — no resolving, no I/O |
| `boot()` | after all `register()` | runtime wiring (resolving is safe now) |
| `routes(Router)` | after routes file loads | attach routes, usually into junctions |
| `events(): array` | at boot | the event → listeners map |
| `migrationPaths(): array` | CLI + boot | dirs of migration files |
| `seedPaths(): array` | CLI | seeder locations |
| `configDefaults(): array` | at boot | defaults that fill gaps (app config wins) |

> [!IMPORTANT] Why `register()` and `boot()` are separate
> `register()` runs for **every** provider before any `boot()`. So a provider can
> safely resolve, in `boot()`, a binding that another provider added in `register()` —
> the ordering guarantees it exists. Never resolve from the container in `register()`.

## Filling junctions

`routes()` receives the `Router`. The host app declares the cross-cutting pipe stacks
once (in `app/routes.php`) as named [junctions](./routing#junctions); a provider slots
its routes into one with `intoJunction()`, inheriting the prefix and pipe stack
without redeclaring CORS/session/CSRF/auth. This is the seam that lets a provider ship
routes it couldn't otherwise wire.

## Load order

The Kernel boots providers, **then** loads `app/routes.php` (declaring junctions),
**then** applies provider routes (filling them). So junctions always exist before a
provider reaches for one. Route-origin tracking means a duplicate-route error names
the offending provider, not just the path.

## Registering a provider

Add the provider class to your app config's provider list. The `ServiceProviderRegistry`
(`src/Core/ServiceProviderRegistry.php`) drives the lifecycle for both the web Kernel
and the CLI scripts — so `migrate` and `seed` see the same providers (and their
`migrationPaths()`/`seedPaths()`) that the web app does.

## Building a modular feature

A self-contained feature is: a provider + its handlers/pipes + a table/record + a
policy + migrations. Bundle those, expose the provider, and the feature installs by
adding one class. This is exactly how the shipped [modules](/modules/) (landing, a/b)
are built — and how the `module:install` command installs both halves of a dual-published
module. See [Writing a Module](/modules/).

## See also

- [Routing & Junctions](./routing) — the junctions providers fill.
- [Events & Listeners](./events) — the `events()` map.
- [CLI & Makers](./cli) — `make:scaffold` generates a provider-ready feature.
