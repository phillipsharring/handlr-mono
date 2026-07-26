# Database

Handlr's data layer is a **table gateway** (`Table`) over an **active record**
(`Record`), backed by a thin PDO wrapper (`Db`). UUID primary keys are the default,
and the BINARY(16)↔string conversion is handled for you.

## Db

`Db` (`src/Database/Db.php`) wraps PDO. It validates config **eagerly** (a MySQL DSN
must include `dbname=`) but connects **lazily** on the first query. Register it as a
**singleton** so one connection (and one transaction) is shared across a request.

```php
$db->execute('SELECT * FROM users WHERE id = ?', [$id]);   // returns PDOStatement|false
$db->insertId();
$db->beginTransaction(); $db->commit(); $db->rollBack();
$db->uuidToBin($uuid);   $db->binToUuid($bin);             // 16-byte BINARY ↔ string, idempotent
```

`DbInterface` also has a `NullDb` implementation for tests.

## Record

A `Record` is an active-record row object. Its columns live in an internal `$data`
array reached through **magic `__get`/`__set`** — you document them with `@property`
docblocks, not real properties.

```php
/**
 * @property string      $id
 * @property string      $user_id
 * @property string      $name
 * @property string|null $deleted_at
 */
final class ChecklistRecord extends Record
{
    protected bool $useUuid = true;              // UUIDv7 id generated on construct
    protected array $uuidColumns = ['user_id'];  // extra UUID cols to decode
    protected array $casts = ['created_at' => 'date'];
    protected array $computed = ['item_count'];  // present on read, excluded from writes
}
```

> [!WARNING] Never declare real public properties for columns
> A real `public ?string $name = null;` **shadows** the `__get`/`__set` magic, so the
> value never reaches `$data` — and `update()` / `toPersistableArray()` silently drop
> it. Use `@property` docblocks only — this is the single most common Record mistake.

Useful methods: `toArray()` (pk + data, for API output), `toPersistableArray()` (pk +
data minus computed columns — what `insert`/`update` write), `usesUuid()`,
`uuidColumns()`. `__get` applies `$casts` on read (`date`→`DateTime`, plus
`int`/`float`/`bool`/`string`).

## Table

`Table` (`src/Database/Table.php`) is the gateway. Extend it with a table name and
record class:

```php
/** @extends Table<ChecklistRecord> */
final class ChecklistsTable extends Table
{
    protected string $tableName   = 'checklists';       // note: $tableName, not $table
    protected string $recordClass = ChecklistRecord::class;
}
```

The `@template T of Record` annotation means `findById()` and a `Table<T>`-typed
resolver carry the concrete record type through to your IDE and static analysis.

### Reads

```php
$table->findById($id);                                  // ?Record — use for pk lookups
$table->findFirst($columns, $conditions, $orderBy);     // ?Record
$table->findWhere($columns, $conditions, $orderBy, $limit, $offset);  // Record[]
$table->paginate($columns, $conditions, $page, $perPage, $orderBy);   // {data, meta}
$table->count($conditions);                             // int
$table->hydrate($rawRow);                               // Record — from a custom-query row
```

> [!TIP] Use `findById()` for primary-key lookups
> `findFirst` with multiple UUID conditions can behave surprisingly around binary
> conversion. For a pk lookup use `findById($id)`; when you need ownership too,
> `findById` then check the owner in code (or let a [Policy](./authorization) do it).

`hydrate($row)` is the sanctioned way to turn a **raw custom-query row** into a
record — it decodes the pk and every `$uuidColumns` entry from BINARY(16). Do **not**
use `new ChecklistRecord($row)` for a DB row: the constructor stores data verbatim
and won't decode the binary UUIDs, so `$record->user_id` would be raw bytes.

### Writes

```php
$table->insert($record);         // returns the record (auto-inc id back-filled for non-uuid rows)
$table->insertMany($records);    // single multi-row INSERT (ids not back-filled)
$table->update($record);         // affected rows; excludes pk + updated_at; throws with no id
$table->delete($record);         // hard delete; affected rows
```

For soft delete, keep a `deleted_at` column and set it via `update()` rather than
`delete()`.

### Conditions DSL

`$conditions` is an assoc array with a small operator vocabulary:

```php
['user_id' => $uid]                       // equality
['deleted_at' => null]                    // IS NULL
['archived_at' => ['NOT NULL']]           // IS NOT NULL
['count' => ['>=', 10]]                   // operator + value
['status' => ['IN', ['active', 'pinned']]] // IN (empty IN → 0=1)
['name' => ['LIKE', '%grocery%']]         // LIKE
```

`orderBy` is **indexed**, not assoc: `[['created_at', 'DESC'], ['name', 'ASC']]`.
Identifiers are validated (`^[a-zA-Z_][a-zA-Z0-9_]*$`) and backtick-quoted; `table.col`
is supported. UUID pk/columns are auto-converted for conditions.

## Query

For read models that don't fit CRUD, extend `Query` (`src/Database/Query.php`) and
write SQL directly (HEREDOC/NOWDOC, backtick identifiers, one clause per line):

```php
final class ChecklistsQuery extends Query
{
    public function isOwnedBy(string $checklistId, string $userId): bool
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM   `checklists`
            WHERE  `id` = ?
            AND    `user_id` = ?
            SQL;
        return $this->count($sql, [$this->uuidToBin($checklistId), $this->uuidToBin($userId)]) > 0;
    }
}
```

Protected helpers: `rows()`, `row()`, `scalar()`, `count()`, `column()`,
`uuidToBin()`, `binToUuid()`.

## Migrations & seeders

- **Migrations** (`src/Database/Migrations/`) — extend `BaseMigration` (`up()`/`down()`
  with `exec()`, `tableExists()`, `columnExists()`, `indexExists()` helpers). Files are
  `{timestamp}_{desc}.php`; `MigrationRunner` tracks batches in a `migrations` table and
  supports `migrate`, `rollback`, `fresh`. Provider migration paths merge and sort by
  timestamp so they interleave.
- **Seeders** — `Seeder::seed([TableClass => rows])` with nested `_relations` for FK
  auto-injection; `truncate()`; `upsert()` for raw dumps.

Both are driven from the CLI — see [CLI & Makers](./cli).

## See also

- [Authorization](./authorization) — `Table` + `Record` are what resolution loads.
- [CLI & Makers](./cli) — `make:record`, `make:table`, `make:migration`, `migrate`, `seed`.
