# Database

> [!NOTE] Draft
> This page is a stub. Content coming.

## To cover

- **Db** — lazy PDO; validates config eagerly, connects on first query; `DbInterface` must be a singleton
- **Table** — CRUD over Records: `insert`, `update`, `delete`, `findById`, `findFirst`, `findWhere`, `paginate`
- **Record** — `@property` docblocks over an internal `$data` array (never real public props)
- **Query** — abstract base for read-only queries: `rows`, `row`, `scalar`, `count`, `column`
- UUID v7 primary keys, `uuidToBin` / `binToUuid`
- Migrations & seeders
