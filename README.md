# Shopping Cart API

A shopping cart REST API built with Symfony. It demonstrates hexagonal architecture, DDD, CQRS and event-driven design.

- Functional specification: [`SPEC.md`](SPEC.md)
- API contract: `docs/openapi.yaml` (generated from `api-contract/`)
- Progress: [`docs/plan.md`](docs/plan.md)

> Work in progress. Sections marked _TBD_ are filled in as the corresponding step is built.

## Tech Stack

| Component | Version |
|---|---|
| PHP | 8.5 (official `php:8.5-cli-alpine` image, plus `intl`) |
| Symfony | 8.1 |
| Composer | 2 |
| Doctrine | ORM 3, DBAL 4, Migrations |
| Database | SQLite 3 |
| Messaging | Symfony Messenger with the Doctrine transport |
| Logging | Monolog |
| Dev fixtures | DoctrineFixturesBundle 4 |

| PHPUnit | 13 |
| Behat | 4 (acceptance tests), with `opis/json-schema` for the contract validation |
| PHPStan | 2 (level 8, with the Symfony extension) |
| Deptrac | 4 |
| PHP-CS-Fixer | 3 (`@Symfony` plus `declare_strict_types`) |

_Further tools are added here as they are introduced._

## Setup

Requirements: Docker with Docker Compose, and `make`. PHP and Composer run only inside the container.

```sh
make setup     # build the image, install the dependencies, reset the databases, load the dev fixtures, start the containers
make db-reset  # drop all tables, run the migrations again (dev and test), load the dev fixtures, restart the worker
make openapi   # regenerate docs/openapi.yaml from api-contract/
make help      # list all targets
```

The API listens on <http://localhost:8000>. If the port is taken, set `APP_PORT`, e.g. `APP_PORT=8001 make setup`.

### Quality checks

```sh
make qa      # everything below, fails fast
```

`make qa` runs, in this order: the two OpenAPI checks (see below), `composer audit`, the code style check, PHPStan, Deptrac and PHPUnit. Deptrac runs twice: `deptrac.yaml` checks the layer direction (Domain ← Application ← Infrastructure, vendor code only in Infrastructure), `deptrac-contexts.php` checks the boundaries between the bounded contexts.

GitHub Actions runs the same two commands, `make setup` and `make qa`, on every push and pull request (`.github/workflows/qa.yaml`).

The database is SQLite, one file per environment: `var/data_dev.db` and `var/data_test.db`. The dev database is filled with a small demo catalogue (`src/Shared/Infrastructure/DataFixtures`); the product IDs are fixed, so they survive every reset. The test database stays empty: each test creates the data it needs.

Two containers run: `php` serves the API, `worker` consumes the domain events from a queue table in the same database. Its log is `var/log/domain_events.log`, the general application log is `var/log/dev.log` (both JSON lines); `docker compose logs worker` shows the worker's console output. In the test environment events are handled inline, so the tests need no worker.

### API contract

The API is designed contract-first. The source is `api-contract/`: `base.yaml` (info, security schemes, shared parameters and error responses), one file per operation in `paths/<resource>/` and one file per schema in `schemas/`. `make openapi` merges them into [`docs/openapi.yaml`](docs/openapi.yaml), which is committed.

Two checks guard it in `make qa`: `app:openapi:check-sync` fails when `docs/openapi.yaml` differs from what the fragments produce, and `app:validate:openapi` checks the structure of the generated file (required fields, unique operation IDs, resolvable `$ref`s, defined security schemes). Both checks are tested themselves, because a gate that always passes would go unnoticed: `CheckOpenApiSyncCommandTest` (a difference, a missing file) and `ValidateOpenApiScriptTest` (one broken spec per rule). `OpenApiBuilderTest` covers the merge of the fragments, which the sync check cannot judge: it compares the builder with its own earlier output.

Behat enforces the contract at runtime: `make behat` runs the acceptance tests as full HTTP round-trips through the kernel. The steps `the request body matches the OpenAPI spec` and `the response matches the OpenAPI spec` validate against `docs/openapi.yaml`. The response step checks the status code, the content type and the body; a status code the operation does not document fails the scenario. `OpenApiValidatorTest` proves that these steps really fail on a deviation. The request step is for valid requests only; a scenario that sends an invalid body on purpose leaves it out.

Behat is part of `make qa`. `features/shared/errors.feature` covers the generic errors, `features/<context>/` the endpoints. The database is emptied before every scenario; a scenario creates the data it needs with steps such as `the following products exist:`.

## Architecture

_TBD: bounded contexts, layers, request flow._

## Design Decisions

Rationale for non-obvious decisions. Entries are added together with the code that implements them.

| Topic | Decision | Why |
|---|---|---|
| Scope beyond the brief | Currency and VAT, guest and registered carts, stock reservation | Not required by the client; added deliberately to show the modelling (SPEC section 2) |
| Cart prices (R5) | The current catalogue price is shown, no snapshot when an item is added | A cart is not an order; a snapshot belongs to checkout, which is out of scope |
| Rounding (R8) | Net and VAT are computed per line from the gross amount in integer arithmetic, then summed | No floats; totals always equal the sum of the lines shown |
| Reservation TTL (R11) | 30 minutes, configurable | Bounds how long an abandoned cart can hold stock |
| Guest cart expiry | Not implemented | Expired reservations already free the stock (R11, R12); the rows are harmless |
| Foreign cart (R6) | 404, identical to an unknown cart | Does not reveal that a cart exists |
| Web server | PHP's built-in server (`php -S`) with `PHP_CLI_SERVER_WORKERS`, as in the reference | Enough for a demo and keeps the setup to one image; the workers allow parallel requests. Not for production, where FrankenPHP or PHP-FPM would be used |
| Write lock (R14) | Every SQLite transaction starts as `BEGIN IMMEDIATE`, with a 5 second busy timeout; set as PDO driver options | The lock is taken before the first read, so check-then-write cannot interleave and no lock upgrade can fail mid-transaction. PostgreSQL would use `SELECT … FOR UPDATE` in the adapter instead |
| Cart view across contexts | Through ports of the Cart context, no SQL joins across contexts | Keeps the contexts independent and the persistence swappable |
| Responses of cart commands | `POST /carts`, add item and change quantity return the full cart view | A client needs the new totals and reservation status after every change; one schema serves all cart responses. Internally the controller dispatches the view query after the command |
| Money in the API | An object `{amount, currency}` with the amount in cents; the tax rate in basis points (`taxRateBp`) | Integers only (R4, R8); the currency travels with every amount instead of being implied |
| Price on the product | One gross price in one currency, stored on the product (`price_amount`, `price_currency`) | Several currencies would be overkill for this scope. The currency is still explicit, never implied (SPEC, D1). Prices in further currencies would get a table of their own (`product_price`: product, currency, amount) with the price on the product as the default, an additive change. The larger part of that work would be in the cart, which would need a currency of its own: `Money` refuses to add amounts of different currencies |
| `Money` and `TaxRate` | Shared kernel (`Shared/Domain/ValueObject`); `ProductId` stays in `Product` | Product prices and cart totals are the same concept, and contexts may not import each other's Domain. Both reject invalid values with an `\InvalidArgumentException`: they are never built from client input, so an invalid one is a defect, not a client error |
| JWT keys | Generated by `make setup`, git-ignored, no passphrase | A committed private key lets anyone forge tokens; a passphrase stored beside the key adds nothing. Production would inject the key from a secret store |

## Deliberately Not Tested

Code and configuration with behaviour but without a direct test, and why. There are two accepted reasons: a defect would show up loudly through another check, or an automated test is not possible at reasonable cost. In the second case the row says how the behaviour was verified. Code without behaviour (interfaces, marker interfaces, enums without methods, attributes) is not listed.

| What | Covered or verified by | Why there is no direct test |
|---|---|---|
| `app:db:reset` | `make setup`, Behat, the integration tests | A broken reset fails every database-backed test at once; a dedicated test would find nothing new |
| `app:openapi:build` (the command) | `app:openapi:check-sync` in `make qa` | The command only writes the output of `OpenApiBuilder` to `docs/openapi.yaml`; if it wrote nothing or to the wrong place, the sync check would fail. The builder itself is tested |
| `Kernel` | Every integration test and Behat scenario boots it | Framework boilerplate |
| `ProblemDetailsResponse` | The mapper tests, `ApiExceptionSubscriberTest` and the Behat snapshots assert its complete output | A dedicated test would repeat them |
| No `X-Powered-By` header (`expose_php=0` in the Docker image) | By hand: `curl -I` against the running container shows no such header | Behat calls the kernel in-process and never sees the headers the PHP server adds; a test over real HTTP for one ini setting is not worth it |
| `ProductFixtures` and `app:fixtures:load` | `make setup`, which CI runs on every push | A fixture that does not load fails the setup; the data itself is demo content without rules |
| Separate log file `var/log/domain_events.log` | By hand: the worker's messages appear there in dev | The handler exists only in the dev configuration; in test, logging is switched off |

## Accepted Risks

| Risk | Why it is accepted |
|---|---|
| Stock hoarding through many guest carts | Limited by the rate limit, the quantity cap (R2) and the reservation TTL (R11) |
| A domain event can be lost after commit with a non-Doctrine transport | With `doctrine://default` on the same database, publishing inside the transaction acts as an outbox; a lost release is healed by the TTL |

## Deviations from the Reference Project

Reference: <https://github.com/a-romero-v/sample-hexagonal-api>. Every deviation is listed here with its reason, in the same commit as the change. The list below is the planned set; entries are confirmed as they are implemented.

| Area | Reference | Here | Why |
|---|---|---|---|
| Tooling | Composer scripts only | Makefile on the host wrapping Docker Compose and the Composer scripts | PHP runs only in the container |
| Tooling | PHP-CS-Fixer with `@Symfony`, risky rules off | Additionally `declare_strict_types` (the only risky rule) | Strict types are required in every file; the tool enforces it |
| Tooling | No architecture check, no CI | Deptrac, `composer audit` and GitHub Actions in QA | Layer and context rules are enforced, not just documented |
| Tests | Structure and naming are conventions only | `ConventionsTest` scans `src/` and fails on a misnamed or misplaced class | Deptrac checks dependencies, not names or locations |
| API contract | Every operation spells out its error responses; path parameters are repeated | Shared `components.responses`, `parameters` and `headers` in `base.yaml`, referenced from the operations | Eight operations share the same error bodies; one definition keeps them identical |
| API contract | One `bearerAuth` scheme, `security: []` globally | Two bearer schemes (`cartToken`, `jwt`) required globally; public operations opt out with `security: []` | Deny by default, also in the contract |
| Tooling | `$projectDir` of the OpenAPI commands wired in `services.yaml` | `#[Autowire('%kernel.project_dir%')]` on the constructor | Same style as `ResetDatabaseCommand`; no service definitions needed |
| Tests | Behat `4.x-dev` | Behat `^4.0` | A stable release exists now; `minimum-stability` stays `stable` |
| Tests | The contract steps skip silently when the status code or the content type is not in the contract | Undocumented status code, undocumented content type and a body where none is documented fail the scenario; `$ref`s to shared responses are resolved | A skipped check looks like a passed one. Covered by `OpenApiValidatorTest` |
| Tests | One Behat step per HTTP method | One step with the method as argument (`I send a :method request to :url`) | Same wording in the features, less code |
| CQRS | `CommandBusInterface::dispatch()` returns `mixed` | Returns `void` | Commands change state and return nothing; a controller that needs a body asks a query |
| CQRS | The buses call `handle()` on an untyped service; a missing handler is a `RuntimeException` | The buses check that the handler has a `handle()` method; wiring mistakes are `LogicException`s | Passes PHPStan level 8 without an ignore; a wiring mistake is a programming error |
| Domain | The domain calls `new \DateTimeImmutable()` and imports `symfony/uid` | Time and IDs come from ports (`ClockInterface`, `UuidGeneratorInterface`) with adapters in Infrastructure | Tests control time and IDs; the domain imports no library |
| Database | PostgreSQL | SQLite | No extra service; persistence stays swappable |
| Database | `app:db:reset` drops and re-creates the database | Drops all tables through the DBAL schema manager | DBAL cannot drop a SQLite database; dropping tables works on every platform |
| Persistence | Auto-increment `int` plus `uuid` column | UUID primary key | One identity, generated before persisting |
| Persistence | `save()` returns the aggregate | `save()` returns `void` | Commands return nothing; reads go through queries |
| Persistence | Money as `float` | Integer cents plus currency | No rounding errors |
| Product | Create, update and delete; `save()` and `delete()` on the repository, `toRecord()` on the mapper | Read-only catalogue: the repository only reads, the mapper only has `toDomain()` | Managing the catalogue is out of scope; products come from fixtures |
| Domain | Repository interfaces in `Domain/Repository/` | Next to their aggregate in `Domain/Model/` | The port belongs to the aggregate it stores |
| Fixtures | Loaded by hand, generated IDs | Loaded by `app:db:reset` for dev (`app:fixtures:load`), fixed UUIDs | One command gives a usable API; documented examples keep working |
| Tests | The Behat reset disables the PostgreSQL triggers and resets the sequences | The ORM purger alone | SQLite and UUID keys need neither |
| Errors | HTTP status in the domain (`#[ApiException]`) | `#[DomainError]` with a business-level `ErrorType` | The domain knows no HTTP |
| Errors | `{code, error}` | RFC 9457 Problem Details | Standard format |
| Errors | Access denied is 403 | Access denied is 404 | No existence leak (R6) |
| Errors | `HttpExceptionMapper` handles access denied and validation only; other exceptions fall through to Symfony's error page | It handles every HTTP exception, and `ApiExceptionSubscriber` turns anything unmapped into a 500 Problem Details response | Every error has the same format and an `errorCode`; no framework error page |
| Errors | The exception message is the response text | Framework exceptions get a fixed `detail` per case; only domain exceptions show their own message | Framework messages name routes, classes and parameters |
| Errors | Shared errors are domain exception classes (`AccessDeniedException`, `ValidationException`) created inside the mapper | `SharedErrorCode` enum in Infrastructure | They are HTTP concerns, not domain errors; nothing in the domain throws them |
| Errors | Mappers passed to the subscriber in `services.yaml` | `#[AutowireIterator]` on the constructor | No service definition needed |
| Security | Registration, logout, JWT blocklist, admin role | Not carried over | Out of scope |
| Security | JWT keys committed in `config/jwt/` | Generated at setup, git-ignored | No private key in a public repository |
| Security | JWT only | Additional cart-token authenticator for guests | Anonymous carts |
| Security | `denyAccessUnlessGranted()` in the method | `#[IsGranted]` on the route | Declarative, checked by a route-coverage test |
| Security | No hardening headers; PHP sends `X-Powered-By` | `X-Content-Type-Options: nosniff` on every response; `expose_php=0` in the image | A JSON body is never sniffed as HTML; the PHP version is not advertised |
| Security | `/api` prefix, no rate limiting | No prefix, rate limiting | API-only application; abuse protection |
| Events | Two publisher adapters (EventDispatcher and Messenger), wrapper message, a subscriber and a Messenger handler class per event | One Messenger adapter, events routed by interface, Application handlers registered by tag | One consumer only; a second adapter would be dead code, and the wrappers add nothing |
| Events | `DomainEventPublisherInterface` in `Shared/Domain` | In `Shared/Domain/Port` | All ports of the domain live in a `Port` folder |
| Events | Published after `save()` without a transaction | Published inside the transaction | Transactional outbox with the Doctrine transport |
| Events | AMQP transport, events carry entities | `doctrine://default`, events carry scalars and value objects | No RabbitMQ; events are a stable published language |
| Stock | Kept on `Product`, reserved asynchronously on `OrderPlaced` | Own `Inventory` context, synchronous, time-limited, versioned reservations | No overselling (R14) |
| Tests | Repository ports are mocked | In-memory implementations of the ports | Tests exercise behaviour, not call sequences |
