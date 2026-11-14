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
- [x] `api-contract/` (base, paths, schemas incl. `ProblemDetails`), `app:openapi:build`, `app:openapi:check-sync`, `bin/validate-openapi`
- [x] Behat steps validating requests and responses against the contract (`ApiContext`, `OpenApiValidator` with a unit test). No feature yet: there is no endpoint. `app:behat` exists but is not part of `app:qa`
- [x] Tests for the contract tooling, added after an audit against the rule "tests left out on purpose are documented" (`CLAUDE.md`, "Tests"): `CheckOpenApiSyncCommandTest` and `ValidateOpenApiScriptTest` (failure paths of the two QA gates), `OpenApiBuilderTest` (merge of the fragments). What stays without a direct test is listed with its reason in the README section "Deliberately Not Tested"

## M3 – Shared kernel
- [x] Command and query bus ports with in-process adapters (`SymfonyCommandBus`, `SymfonyQueryBus`; the locators in `config/services.yaml` are still empty)
- [x] `AbstractDomainException`, `#[DomainError]`, `ErrorType`, exception mappers, Problem Details (4xxx codes). `symfony/validator` added for the 4002 mapping. Left for M5/M6, when Symfony Security arrives: Security's `AccessDeniedException` in `HttpExceptionMapper`, the 401 entry point and the login failure handler
- [x] `ClockInterface` (`SystemClock`), `UuidGeneratorInterface` (`SymfonyUuidGenerator`, UUID v7, `symfony/uid` added), `TransactionInterface` (from M1), `DomainEvent`. The `AggregateRoot` trait moves to M5: PHPStan rejects a trait that nothing uses (`trait.unused`), so it is added together with `Cart`. Test doubles for the ports (fixed clock, predictable IDs) are added with the first test that needs them
- [x] Domain event publisher port with the Messenger adapter, routing by `DomainEvent`; tag-based handler registration verified with a fixture handler (`DomainEventDeliveryTest`)
- [x] Response hardening (`nosniff` on every response, `expose_php=0` in the image). 415 and 400 are mapped by `HttpExceptionMapper` (unit-tested in the mapper step); they can only occur end to end once an endpoint has a body, so their Behat scenarios move to M5
- [x] First Behat feature (`features/shared/errors.feature`: router 404 as Problem Details, nothing echoed, `nosniff`); `app:behat` is part of `app:qa`. There is no route yet, so a 405 cannot occur: its scenario moves to M4
- [x] Architecture test for the structure and naming conventions (`tests/Unit/Architecture/ConventionsTest`). Verified once with a throwaway context of 15 classes: all 12 misplaced ones were reported, the 3 correct ones were not

## M4 – Product
- [x] `Product`, `ProductId`, repository port with the Doctrine adapter (read-only: `findById`, `findAll`), table `product`, dev fixtures through DoctrineFixturesBundle (`app:fixtures:load`, part of `app:db:reset` for dev). `Money` and `TaxRate` live in `Shared/Domain/ValueObject`, because Cart needs them too; the net/VAT calculation (R8) is added with the cart view in M5. The in-memory repository is added with the first handler test (next step but one). For M5: the "products by IDs" query needs a `findByIds()` on the port
- [ ] `tests/Behat/DatabaseContext` (reset per scenario, rewritten for SQLite) and the data setup steps
- [ ] `GET /products`, `GET /products/{productId}`
- [ ] Behat scenario for 405 (`errorCode` 4005, `Allow` header) on a product route, added to `features/shared/errors.feature`

## M5 – Cart core for guests (AC1–AC4, guest part of AC5)
- [ ] `Cart` aggregate, `CartItem`, `Quantity`, `CartOwner`, cart version, domain events; `AggregateRoot` trait (`recordEvent()` / `releaseEvents()`, from the reference) with a unit test
- [ ] Persistence: records, mapper, repository, optimistic lock with an integration test (stale version fails with `Conflict`), in-memory repository
- [ ] Ports `ProductCatalogInterface` and `StockReservationInterface` (always-succeeding stub)
- [ ] `POST /carts` (guest) with cart token, cart-token authenticator, `CartVoter`, route-coverage test (integration suite), 404 for foreign carts (R6)
- [ ] View cart with totals (AC4, R8)
- [ ] Add item (AC1), remove item (AC2), change quantity (AC3)
- [ ] Behat scenarios for 415 (wrong content type) and 400 (malformed JSON) on the first endpoint with a body; `#[MapRequestPayload]` needs `symfony/serializer` (ask before adding)

## M6 – Registered users and rate limits (AC5, AC7)
- [ ] `User` context, `POST /auth/login` (JWT), uniform login failure
- [ ] JWT key pair generated in `make setup` through a Composer script (`lexik:jwt:generate-keypair --skip-if-exists`), git-ignored, no passphrase; the test environment and CI get it through `make setup`. Check that the tracked `.env*` files hold no real secret
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
- [ ] README: tech stack, setup, architecture, design decisions and deviations confirmed against the code; overview of the final database schema
- [ ] Final review of the commit history, send the repository link
