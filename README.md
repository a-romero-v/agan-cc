# Shopping Cart API

A shopping cart REST API built with Symfony, **without API Platform**. It demonstrates hexagonal architecture, DDD, CQRS and event-driven design.

- Functional specification: [`SPEC.md`](SPEC.md)
- API contract: `docs/openapi.yaml` (generated from `api-contract/`)
- Progress: [`docs/plan.md`](docs/plan.md)

> Work in progress. Sections marked _TBD_ are filled in as the corresponding step is built.

## Tech Stack

_TBD at scaffold: exact PHP, Symfony and tool versions._

## Setup

_TBD at scaffold._ Target: `make setup` builds and starts the containers, installs dependencies, resets the databases and loads fixtures. `make qa` runs all checks.

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
| Cart view across contexts | Through ports of the Cart context, no SQL joins across contexts | Keeps the contexts independent and the persistence swappable |

## Accepted Risks

| Risk | Why it is accepted |
|---|---|
| Stock hoarding through many guest carts | Limited by the rate limit, the quantity cap (R2) and the reservation TTL (R11) |
| A domain event can be lost after commit with a non-Doctrine transport | With `doctrine://default` on the same database, publishing inside the transaction acts as an outbox; a lost release is healed by the TTL |
| JWT keys are committed in `config/jwt/` | Demo convenience only; never do this in a real project |

## Deviations from the Reference Project

Reference: <https://github.com/a-romero-v/sample-hexagonal-api>. Every deviation is listed here with its reason, in the same commit as the change. The list below is the planned set; entries are confirmed as they are implemented.

| Area | Reference | Here | Why |
|---|---|---|---|
| Tooling | Composer scripts only | Makefile on the host wrapping Docker Compose and the Composer scripts | PHP runs only in the container |
| Tooling | No architecture check, no CI | Deptrac, `composer audit` and GitHub Actions in QA | Layer and context rules are enforced, not just documented |
| Database | PostgreSQL | SQLite | No extra service; persistence stays swappable |
| Persistence | Auto-increment `int` plus `uuid` column | UUID primary key | One identity, generated before persisting |
| Persistence | `save()` returns the aggregate | `save()` returns `void` | Commands return nothing; reads go through queries |
| Persistence | Money as `float` | Integer cents plus currency | No rounding errors |
| Errors | HTTP status in the domain (`#[ApiException]`) | `#[DomainError]` with a business-level `ErrorType` | The domain knows no HTTP |
| Errors | `{code, error}` | RFC 9457 Problem Details | Standard format |
| Errors | Access denied is 403 | Access denied is 404 | No existence leak (R6) |
| Security | Registration, logout, JWT blocklist, admin role | Not carried over | Out of scope |
| Security | JWT only | Additional cart-token authenticator for guests | Anonymous carts |
| Security | `denyAccessUnlessGranted()` in the method | `#[IsGranted]` on the route | Declarative, checked by a route-coverage test |
| Security | `/api` prefix, no rate limiting | No prefix, rate limiting | API-only application; abuse protection |
| Events | Two publisher adapters (EventDispatcher and Messenger), wrapper message, a subscriber and a Messenger handler class per event | One Messenger adapter, events routed by interface, Application handlers registered by tag | One consumer only; a second adapter would be dead code, and the wrappers add nothing |
| Events | Published after `save()` without a transaction | Published inside the transaction | Transactional outbox with the Doctrine transport |
| Events | AMQP transport, events carry entities | `doctrine://default`, events carry scalars and value objects | No RabbitMQ; events are a stable published language |
| Stock | Kept on `Product`, reserved asynchronously on `OrderPlaced` | Own `Inventory` context, synchronous, time-limited, versioned reservations | No overselling (R14) |
| Tests | Repository ports are mocked | In-memory implementations of the ports | Tests exercise behaviour, not call sequences |
