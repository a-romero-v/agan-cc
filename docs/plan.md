# Plan

Progress tracker. Each step ends with a green `make qa` and one or more commits. Update the checkbox after each completed step.

## M0 – Documentation
- [x] `SPEC.md`, `CLAUDE.md`, `.claude/rules/`, README skeleton, this plan

## M1 – Scaffold
- [x] Docker image, Compose (`php`), Makefile, Symfony skeleton
- [x] QA tooling: PHP-CS-Fixer, PHPStan level 8, Deptrac (layers and contexts), PHPUnit, Composer scripts (`app:*`). `app:qa` grows with the project: OpenAPI checks join in M2, Behat in M3
- [x] Doctrine with SQLite, migrations, DB reset command
- [x] `TransactionInterface` with `BEGIN IMMEDIATE` and `busy_timeout`, integration test for the write lock
- [x] Messenger transport `domain_events`, `worker` service; manual check of the worker against the SQLite lock (result in `.claude/rules/domain-events.md`)
- [x] Monolog with a separate log file for domain event delivery
- [x] GitHub Actions workflow running `make setup` and `make qa`

Items marked "verify at scaffold" in `.claude/rules/` are verified in the milestone that builds them; M1 covers the database and Docker ones.

## M2 – API contract
- [ ] `api-contract/` (base, paths, schemas incl. `ProblemDetails`), `app:openapi:build`, `app:openapi:check-sync`, `bin/validate-openapi`
- [ ] Behat steps validating requests and responses against the contract

## M3 – Shared kernel
- [ ] Command and query bus ports with in-process adapters
- [ ] `AbstractDomainException`, `#[DomainError]`, `ErrorType`, exception mappers, Problem Details (4xxx codes)
- [ ] `ClockInterface`, ID generator, `TransactionInterface`, `AggregateRoot`, `DomainEvent`
- [ ] Domain event publisher port with the Messenger adapter, routing by `DomainEvent`
- [ ] Response hardening (`nosniff`), 415 and 400 handling
- [ ] Architecture test for the structure and naming conventions (`tests/Unit/Architecture`), verified once with deliberately misplaced classes

## M4 – Product
- [ ] `Product`, `Money`, `TaxRate`, repository, fixtures
- [ ] `GET /products`, `GET /products/{productId}`

## M5 – Cart core for guests (AC1–AC4, guest part of AC5)
- [ ] `Cart` aggregate, `CartItem`, `Quantity`, `CartOwner`, cart version, domain events
- [ ] Persistence: records, mapper, repository, optimistic lock with an integration test (stale version fails with `Conflict`), in-memory repository
- [ ] Ports `ProductCatalogInterface` and `StockReservationInterface` (always-succeeding stub)
- [ ] `POST /carts` (guest) with cart token, cart-token authenticator, `CartVoter`, route-coverage test (integration suite), 404 for foreign carts (R6)
- [ ] View cart with totals (AC4, R8)
- [ ] Add item (AC1), remove item (AC2), change quantity (AC3)

## M6 – Registered users and rate limits (AC5, AC7)
- [ ] `User` context, `POST /auth/login` (JWT), uniform login failure
- [ ] JWT authenticator, mutually exclusive with the cart-token authenticator
- [ ] One cart per user (R7): Behat for the sequential case, integration tests with two processes and for the unique-violation safety net
- [ ] Rate limiters: login, guest cart creation, failed token authentication (AC7)

**Deliverable milestone:** after M6 the brief is fully covered and the README is complete for this state.

## M7 – Stock reservation (AC6)
- [ ] `Inventory` context: `StockItem`, `Reservation`, fixtures
- [ ] Synchronous reserve behind the Cart port (R9), `InsufficientStockException`
- [ ] Asynchronous release through domain events, idempotent and version-aware (R10)
- [ ] TTL, re-reserve and `reserved: false` in the cart view (R11–R13)
- [ ] Integration tests: concurrency with two processes (R14), real Doctrine transport with `messenger:consume --limit=1`
- [ ] Optional: `app:inventory:purge-expired`

## M8 – Wrap-up
- [ ] README: tech stack, setup, architecture, design decisions and deviations confirmed against the code
- [ ] Final review of the commit history, send the repository link
