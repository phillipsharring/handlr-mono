# CLI & Makers

Handlr ships Symfony Console entrypoints for the repetitive parts — scaffolding code,
running migrations, seeding. You don't call them directly, and **there is no global
`handlr` binary.** The [app skeleton](/getting-started/installation) wires each one as a
**composer script** in `backend/composer.json`, so from a scaffolded app you run them
with `composer run` from the `backend/` directory:

```bash
cd backend
composer run migrate
composer run make:scaffold CreateWidget
```

Each script locates your app (via the bootstrap path helpers + Composer autoload) and
reads the same [service-provider registry](./service-providers) the web app does — so
migrations and seeders from every provider are visible.

## Makers — `composer run make:*`

The skeleton exposes these generators. Each emits one or more files from a stub and
refuses to overwrite an existing file unless the maker allows it. Extra arguments after
the command name are forwarded to the generator:

```bash
composer run make:record WidgetRecord
composer run make:table WidgetsTable
composer run make:handler ListWidgetsHandler
composer run make:pipe ListWidgets
composer run make:scaffold CreateWidget      # Input + Handler + Pipe + Test in one go
composer run make:migration create_widgets_table
composer run make:seed WidgetsSeeder
```

| Script | Generates |
|---|---|
| `composer run make:record` | a `Record` |
| `composer run make:table` | a `Table` |
| `composer run make:handler` | a `Handler` |
| `composer run make:pipe` | a `Pipe` |
| `composer run make:scaffold` | a full feature — Input + Handler + Pipe + Test |
| `composer run make:migration` | a migration file |
| `composer run make:seed` | a `Seeder` |

`make:scaffold` is the workhorse: it produces the Input/Handler/Pipe/Test set for a new
endpoint, ready to attach to a route in your provider.

> [!NOTE] The framework has more makers than the skeleton wires
> The generator framework ships additional makers (a `--no-uuid` record, `--post` /
> `--patch` / `--delete` pipe variants, `make:maker` itself). The skeleton's
> `composer.json` wires the common set above; add a script line to expose others, or
> call `php ./vendor/phillipsharring/handlr-backend/scripts/make.php make:<name>` directly.

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
objects; the `GeneratorRunner` writes them and registers your maker as `make:<name>`.
Stubs live under `stubs/`.

## Migrations

Driven by the `MigrationRunner`, wired as composer scripts:

```bash
composer run migrate            # run the next batch of pending migrations (up 1)
composer run migrate:rollback   # roll back the last batch
composer run migrate:fresh      # drop everything and re-run from scratch
```

Migration files are `{timestamp}_{desc}.php`. The runner tracks applied files in a
`migrations` table (batch / file / ran_at). Paths from every provider's
`migrationPaths()` are merged and **sorted by timestamp**, so a provider's migrations
interleave with the app's in chronological order.

## Seeding

Driven by the `Seeder`:

```bash
composer run seed         # run seeders
composer run seed:fresh   # truncate first, then seed
composer run fresh        # migrate:fresh && seed:fresh — a clean rebuild
```

`Seeder::seed([TableClass => rows])` supports nested `_relations` for FK
auto-injection; `seed:fresh` truncates first; there's an `upsert` path for raw data
dumps. `composer run fresh` is the everyday "reset my dev database" command.

## Installing a module

```bash
composer run module:install -- <name>
```

Installs both halves of a dual-published [module](/modules/) (the composer package and
its npm counterpart) at matching versions. It validates the module name and streams the
install; it does **not** auto-register the module's provider — you add that to your
config. (The `--` separates the module name from composer's own arguments.)

## See also

- [Service Providers](./service-providers) — where migration/seed paths come from.
- [Database](./database) — what migrations and seeders operate on.
- [Writing a Module](/modules/) — packaging a feature for `module:install`.
