# Inventory and Stock Reservation (R9–R14, AC6)

## Model
- `StockItem` (on hand) and `Reservation` (product, cart, quantity, expiresAt, cart version) live in `Inventory`.
- Available = on hand − active (non-expired) reservations.

## Before Inventory exists (AC1–AC5, AC7)
`StockReservationInterface` is implemented by an always-succeeding stub, so `reserved` is always `true`. The API contract already contains `reserved`.

The port has two sides: the modifying calls used by the command handlers and a read-only call that returns the reservation status per product of a cart. The view query handler uses only the read-only call (R11: viewing changes nothing).

## Cart version
- `Cart` holds a version counter that increases on every modification and is stored on the cart row.
- Both paths write the **absolute quantity** and the cart version to the reservation: the synchronous reserve and the asynchronous release.
- The version is also the cart's optimistic lock (see `persistence.md`).

## Reserve synchronously (R9)
- The Cart command handler calls `StockReservationInterface` inside a `TransactionInterface` block. The Application command handler owns the transaction; nested calls join the outer one.
- The reservation is set to the target quantity. Needed from stock = target quantity − this cart's active reservation for the product. That reservation can be higher than the cart quantity while a release is pending; then nothing extra is needed.
- The write lock is taken **before** the stock check.
- Insufficient stock: the adapter in `Cart/Infrastructure` throws Cart's `InsufficientStockException` (1xxx, 409). Nothing is persisted.

## TTL and re-reserve (R11, R13)
- Every modifying cart command extends the TTL of all reservations of the cart and re-tries expired ones.
- Decreasing an expired item never fails: the new quantity is re-reserved best-effort, otherwise the item stays `reserved: false`. Increasing an expired item is an increase and can throw (R9).
- Viewing a cart changes nothing.
- Time comes from `ClockInterface`.

## Release asynchronously (R10)
- `Cart` records `CartItemRemoved` and `CartItemQuantityDecreased`. `Inventory` releases in its event handler.
- Events carry the **absolute resulting quantity** and the cart version. The handler sets the reservation to that state and ignores stale versions.
- Never release a delta. Delayed, duplicated or reordered events must not corrupt a reservation.
- Test this at handler level (unit test with delayed, duplicated and reordered events). Behat cannot cover it, because `sync://` delivers in order.

## Concurrency (R14)
- Check-and-reserve runs under a write lock. Never read-then-write without the lock.
- Where the lock is taken depends on the DB:
  - SQLite: every transaction opened through `TransactionInterface` starts as `BEGIN IMMEDIATE`. This is a connection-level setting in Shared Infrastructure, not in the Inventory adapter: the Cart handler has already opened the transaction and read the cart, and upgrading the lock afterwards can fail with `SQLITE_BUSY`. A `busy_timeout` of 5 seconds is set, because the Messenger worker (Doctrine transport, same file) competes for the lock. Mechanism (verified at scaffold): the PDO driver options `Pdo\Sqlite::ATTR_TRANSACTION_MODE` (PHP 8.5) and `PDO::ATTR_TIMEOUT` in `config/packages/doctrine.yaml`; no middleware needed. It applies to every transaction on the connection. `DoctrineTransactionTest` guards it.
  - PostgreSQL would use `SELECT … FOR UPDATE` on `stock_item` inside the Inventory adapter.
- Covered by an integration test against the real DB: two PHP processes reserve the last item at the same time, exactly one succeeds (see `CLAUDE.md`, "Tests").

## Expiry (R11, R12)
Expired reservations are ignored by `expires_at`. Cleanup is optional housekeeping: `app:inventory:purge-expired`.

## Deviations from the reference
- Own `Inventory` context. The reference keeps stock on `Product`.
- Stock is reserved synchronously through a port. The reference reserves asynchronously on `OrderPlaced` and can oversell.
- Reservations are time-limited and versioned.
