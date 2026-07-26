# CLI & Makers

Handlr ships Symfony Console entrypoints for the repetitive parts: scaffolding code,
running migrations, and seeding. Each locates your app (via the bootstrap path
helpers + Composer autoload) and reads the same [service-provider registry](./service-providers)
the web app does — so migrations and seeders from every provider are visible.

## Makers — `make:*`

`make.php` registers a set of code generators as `make:<name>` commands, driven by the
`GeneratorRunner` (`src/Generator/`). Each maker emits one or more files from a stub;
it refuses to overwrite an existing file unless the maker explicitly allows it.

| Command | Generates | Options |
|---|---|---|
| `make:handler` | a `Handler` | |
| `make:record` | a `Record` | `--no-uuid` |
| `make:table` | a `Table` | |
| `make:pipe` | a `Pipe` | `--post` / `--patch` / `--delete` |
| `make:migration` | a migration file | |
| `make:seeder` | a `Seeder` | |
| `make:scaffold` | a full feature — Input + Handler + Pipe + Test | `--no-pipe`, `--event-only` |
| `make:maker` | a new `MakerInterface` | |

`make:scaffold` is the workhorse: it produces the Input/Handler/Pipe/Test set for a
new endpoint in one go, ready to attach to a route in your provider.

```bash
php vendor/bin/handlr make:scaffold CreateWidget --post
php vendor/bin/handlr make:record WidgetRecord
php vendor/bin/handlr make:table WidgetsTable
```

(The exact `vendor/bin` entry depends on how the app wires the scripts — check the
skeleton's `composer.json` `scripts`.)

## Writing a custom maker

Implement `MakerInterface` (`src/Generator/MakerInterface.php`):

```php
interface MakerInterface
{
    public function name(): string;
    public function description(): string;
    public function arguments(): array;
    public function options(): array;
    public function generate(InputInterface $input, string $stubsPath): array; // GeneratedFile[]
}
```

Return `GeneratedFile(string $path, string $content, bool $allowOverwrite = false)`
objects; the runner writes them and registers your maker as `make:<name>`. Stubs live
under `stubs/`. `make:maker` scaffolds one for you.

## Migrations — `migrate`

`migrate.php` drives the `MigrationRunner`:

```bash
php vendor/bin/handlr migrate up            # run pending migrations
php vendor/bin/handlr migrate up 1          # step-wise: one batch
php vendor/bin/handlr migrate rollback      # roll back the last batch
php vendor/bin/handlr migrate rollback 3    # roll back 3 batches
php vendor/bin/handlr migrate fresh         # drop everything and re-run
```

Migration files are `{timestamp}_{desc}.php`. The runner tracks applied files in a
`migrations` table (batch / file / ran_at). Paths from every provider's
`migrationPaths()` are merged and **sorted by timestamp**, so a provider's migrations
interleave with the app's in chronological order.

## Seeding — `db:seed`

`seed.php` drives the `Seeder`:

```bash
php vendor/bin/handlr db:seed                # run seeders
php vendor/bin/handlr db:seed --fresh        # truncate first, then seed
php vendor/bin/handlr db:seed WidgetsSeeder   # a specific seeder
```

`Seeder::seed([TableClass => rows])` supports nested `_relations` for FK
auto-injection; `--fresh` truncates first; there's an `upsert` path for raw data dumps.

## Installing a module — `module:install`

`module.php` installs both halves of a dual-published [module](/modules/) (the composer
package and its npm counterpart) at matching versions. It validates the module name and
streams the install; it does **not** auto-register the module's provider — you add that
to your config.

## See also

- [Service Providers](./service-providers) — where migration/seed paths come from.
- [Database](./database) — what migrations and seeders operate on.
- [Writing a Module](/modules/) — packaging a feature for `module:install`.
