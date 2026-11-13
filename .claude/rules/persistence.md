# Persistence

The persistence layer is swappable.

- Domain and Application know only repository interfaces and ports. No Doctrine, SQL or DBAL.
- `*Record` classes, repositories, persistence mappers and raw SQL live in `<Context>/Infrastructure/Persistence`.
- Mapping via ORM attributes on `*Record` classes (as in the reference), with one `type: attribute` mapping per context in `doctrine.yaml` and `naming_strategy: underscore`. ORM attributes are allowed only on Records.
- `*Record` classes are the mapped persistence objects, not the domain model. Mappers convert between both (as in the reference):
  - `toDomain()` rebuilds the aggregate through `Aggregate::reconstitute()`.
  - `toRecord($aggregate, ?$existing)` writes onto the existing Record so Doctrine's change tracking produces an UPDATE.
  - Records are updated in place, so their properties are **not** `readonly`.
  - Records stay `final`. This relies on Doctrine native lazy objects, so no proxy subclassing is needed. Verified at scaffold: DoctrineBundle 3.1+ always enables them, no configuration needed.
- The UUID is the primary key. There is no surrogate integer ID. IDs are generated through a port before persisting.
- `Repository::save()` does `persist` + `flush` (as in the reference) and returns `void`. The transaction is owned by the command handler through `TransactionInterface`.
- Use portable mapping types only: UUIDs as strings, money as integers, timestamps as `datetime_immutable`.
- DB-specific SQL is allowed only in adapters, migrations and `tests/Behat/DatabaseContext` (the reference's reset is PostgreSQL-specific and must be rewritten for SQLite).
- Anything DB-specific (locking, upserts) stays behind a port, with one implementation per DB if needed.
- Migrations are platform-specific and the only thing regenerated when switching DBs.
- No foreign keys and no SQL joins across context boundaries. Read models that need data from another context go through a port (see `CLAUDE.md`, cart view).
- Optimistic lock on the cart: `save()` fails with a domain exception (`Conflict`) when the stored version is not the one that was loaded. The cart is always loaded **inside** the transaction block. SQLite's `BEGIN IMMEDIATE` already prevents lost updates; PostgreSQL would not, so the lock keeps the DB switch inside Infrastructure. The mechanism is decided when the cart repository is built (`#[ORM\Version]` or an explicit comparison, because the domain owns the counter) and covered by an integration test.
- Every repository port has an in-memory implementation for unit tests.
- DBs: SQLite, `var/data_dev.db` (dev) and `var/data_test.db` (test). `DATABASE_URL` is set in `compose.yaml`.
- SQLite enforces foreign keys only when each connection enables them. DBAL's `EnableForeignKeys` middleware is registered in `config/services.yaml`; `DatabaseSetupTest` guards it.
- `app:db:reset` drops all tables through the DBAL schema manager and runs the migrations. It does not use `doctrine:database:drop`, which DBAL does not support for SQLite.

## One cart per user (R7)
- The create-cart handler checks for an existing cart and creates one inside the same transaction.
- `CartOwner` is stored as `owner_user_id` (nullable, unique) and `token_hash` (nullable, unique). Guest carts have `owner_user_id = NULL`; SQLite and PostgreSQL both allow several NULLs in a unique index.
- The unique index on `owner_user_id` is only the safety net. The adapter translates a unique violation into a domain exception; the handler treats it as "cart already exists".
- Pitfall: after a failed flush the EntityManager is closed. The follow-up query must not rely on it.

## Deviations from the reference
- UUID primary keys instead of auto-increment `int` plus a separate `uuid` column.
- `save()` returns `void` instead of the reconstituted aggregate.
- Records are `final`.
- Money as integer cents instead of `float`.
- SQLite instead of PostgreSQL.
