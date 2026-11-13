# SPEC – Shopping Cart REST API

> Functional specification: **what** the system must do.
> The **how** (architecture, conventions, tooling) lives in `CLAUDE.md`; rationale lives in `README.md` → "Design Decisions".
> The API contract is `docs/openapi.yaml`, generated from `api-contract/`. If this spec and the contract disagree, the contract wins and this spec gets updated.

## 1. Goal

A RESTful API for a shopping cart, built with current Symfony.
The focus is code quality and care, not speed.

Required features (from the challenge brief):

1. Add an item to the cart
2. Remove an item from the cart
3. Edit an item in the cart
4. View the cart

**Deliberate extension:** stock reservation. Adding items reserves stock so a cart cannot hold more than is actually available. This goes beyond the brief and is built **after** the core cart is complete and green.

## 2. Client Clarifications and Own Decisions

The client answered the open questions on 2026-11-13. Their priority: code quality, clean design and tests; realism is secondary. Everything beyond the four required features is optional and our own decision.

| #  | Topic                | Decision                                                                                     | Basis |
|----|----------------------|----------------------------------------------------------------------------------------------|-------|
| D1 | Currency / VAT       | One currency (EUR), one VAT rate (19 %). Both are modelled explicitly (Money carries a currency, the tax rate lives on the product), never hard-coded. | own decision; the client only requires a fixed amount per product |
| D2 | Additional endpoints | An anonymous cart with an access token, a login endpoint issuing a JWT for registered users, and a product list. | own decision; explicitly allowed, not required |
| D3 | Editing an item      | Editing means changing the quantity only.                                                    | confirmed by the client |
| D4 | Persistence          | SQLite, kept swappable via ports and adapters. Products, stock levels and test users are provided via fixtures. | no constraints from the client; fixtures confirmed |
| D5 | Delivery             | Own Git repository; the link is sent to the client.                                         | confirmed by the client |
| D6 | Stock reservation    | Added as a deliberate extension (see section 1).                                             | own decision; explicitly not required |

## 3. Scope

**In scope**
- Create a cart (anonymous) or resolve the user's cart (registered)
- Add an item, change its quantity, remove an item, view the cart
- Stock check and time-limited stock reservation when items are added or increased
- Login for registered users (JWT)
- Read-only product catalogue (list, detail), data from fixtures
- Consistent error responses according to RFC 9457 (Problem Details)

**Out of scope**
- Checkout, orders, payment (and therefore turning a reservation into a sale)
- Changing stock levels through the API (no admin role; stock comes from fixtures)
- Vouchers and discounts
- User registration, password reset
- Managing the product catalogue through the API
- Merging an anonymous cart into a user's cart after login (candidate for later)

## 4. Ubiquitous Language

| Term              | Context   | Meaning |
|-------------------|-----------|---------|
| **Product**       | Product   | A catalogue article with an ID, a name, a gross price and a tax rate. Contains no stock data. |
| **Cart**          | Cart      | Belongs to exactly one owner and contains zero or more items. |
| **CartItem**      | Cart      | A product in the cart with a quantity and its own UUID (`itemId`, used in the item endpoints). A cart holds at most one item per product. |
| **Quantity**      | Cart      | An integer within the allowed range (see R2). |
| **Owner**         | Cart      | Either an anonymous guest (identified by a cart token) or a registered user (identified by a JWT). Modelled as the value object `CartOwner` with exactly these two variants. |
| **Cart token**    | Cart      | A secret random token, issued once when an anonymous cart is created, granting access to exactly that cart. |
| **Total**         | Cart      | Gross total of all reserved items, also broken down into net amount and included VAT. |
| **StockItem**     | Inventory | The stock record of one product: the physically available quantity (**on hand**). |
| **Reservation**   | Inventory | A quantity of a product held for one cart until it expires. |
| **Available**     | Inventory | On hand minus the sum of all active (non-expired) reservations for that product. |
| **Reservation TTL** | Inventory | How long a reservation stays active after the cart was last modified (default 30 minutes). |

## 5. Business Rules

**Cart**
- **R1** Only existing products can be added.
- **R2** Quantity is between 1 and 99. A quantity of 0 is invalid; removing an item has its own endpoint.
- **R3** Adding a product that is already in the cart increases the quantity of the existing item. If the resulting quantity exceeds the maximum, the request is rejected with 422 and the cart stays unchanged.
- **R4** Monetary amounts are integers in the smallest unit (cents) plus a currency. No floats.
- **R5** Prices in the cart reflect the **current** catalogue price at the time the cart is viewed. *(Record in Design Decisions; alternative: price snapshot when the item is added.)*
- **R6** A cart is only visible to and modifiable by its owner. Someone else's cart is answered with 404, not 403, so its existence is not revealed.
- **R7** A registered user has exactly one cart. `POST /carts` returns the existing cart or creates one; a unique constraint in the database is the safety net, so concurrent `POST /carts` requests of the same user return the same cart.
- **R8** Rounding: prices are gross. Per cart line (unit price × quantity), net = gross × 10000 / (10000 + tax rate), rounded half up to whole cents, in integer arithmetic; VAT = gross − net. The tax rate is an integer in basis points (1900 = 19 %), never a float. Net and VAT are then summed over the lines. *(Record in Design Decisions.)*

**Inventory**
- **R9** Adding an item or increasing its quantity **synchronously** sets the cart's reservation for that product to the new quantity. Only the difference to what this cart currently holds as an active reservation must be available (that reservation can be higher than the cart quantity while a release is still pending). If not enough is available, the request is rejected with 409 and neither cart nor reservations change.
- **R10** Removing an item or decreasing its quantity releases the reservation **asynchronously** via domain events. A short delay before stock becomes available again is acceptable.
- **R11** A reservation expires after the reservation TTL. Every modification of a cart extends the TTL of all its reservations. Viewing a cart does not extend it.
- **R12** Expired reservations no longer count against available stock. No job is required for correctness; cleaning up expired rows is housekeeping only.
- **R13** Items whose reservation has expired are shown as `reserved: false` and are excluded from the totals. The next modifying action on the cart tries to reserve them again on a best-effort basis; if that is not possible, they stay `reserved: false`. A modification never fails because another, unrelated item cannot be re-reserved; only the item being added or increased can cause a 409 (R9). Decreasing the quantity of an expired item never causes a 409: the new quantity is re-reserved on a best-effort basis, otherwise the item stays `reserved: false`. Increasing an expired item counts as an increase and can cause a 409.
- **R14** Stock is never oversold, even under concurrent requests for the same product.

## 6. API Overview

`docs/openapi.yaml` is authoritative. This table is an overview only.

| Method | Path                               | Purpose                                     | Success |
|--------|------------------------------------|---------------------------------------------|---------|
| POST   | `/carts`                           | Anonymous: create a new cart and token. Registered: return or create the user's cart (idempotent). | 201 / 200 + `Location` |
| GET    | `/carts/{cartId}`                  | Cart with items, reservation status and totals | 200 |
| POST   | `/carts/{cartId}/items`            | Add an item (`productId`, `quantity`)       | 201 + `Location` (new item) / 200 (quantity increased) |
| PATCH  | `/carts/{cartId}/items/{itemId}`   | Change quantity (`quantity`)                | 200 |
| DELETE | `/carts/{cartId}/items/{itemId}`   | Remove an item                              | 204 |
| POST   | `/auth/login`                      | Login, returns a JWT                        | 200 |
| GET    | `/products`                        | Product list                                | 200 |
| GET    | `/products/{productId}`            | Product detail                              | 200 |

**Authentication**
- Anonymous: a guest calls `POST /carts` without credentials and receives the new cart's ID and a cart token. The token is returned only once. Afterwards: `Authorization: Bearer <cart-token>`.
- Registered: `POST /auth/login` returns a JWT. Afterwards: `Authorization: Bearer <jwt>`; `POST /carts` then returns the user's cart.

The cart endpoints behave identically for both groups.

**Requests and responses**
- Request bodies are `application/json` only, including `PATCH` (no `merge-patch+json`). Any other content type is answered with 415.
- `POST /carts/{cartId}/items` returns 201 with `Location: /carts/{cartId}/items/{itemId}` when a new item was created, and 200 when the quantity of an existing item was increased.

**Errors** (`application/problem+json`)

| Status | Meaning |
|--------|---------|
| 400    | Malformed JSON |
| 401    | Missing or invalid token |
| 404    | Unknown or foreign cart, unknown item, unknown product on `GET /products/{productId}` |
| 405    | Method not allowed |
| 409    | Insufficient stock (R9), or the cart was modified concurrently |
| 415    | Wrong content type |
| 422    | Validation error, e.g. quantity out of range, or unknown `productId` in a request body (R1) |
| 429    | Rate limit exceeded (login, guest cart creation, failed token authentication); includes `Retry-After` |
| 500    | Unexpected error, without internals |

Every error response carries a numeric `errorCode` as an extension member.

IDs are UUIDs.

## 7. Acceptance Criteria

**AC1 – Add item**
- Given an empty cart, when I add product P with quantity 2, then I get 201 and the cart contains P with quantity 2.
- Given P is in the cart with quantity 2, when I add P with quantity 3, then I get 200 and the quantity is 5.
- When I add an unknown product, then I get 422 and the cart stays unchanged.
- When I send a quantity outside 1–99, then I get 422.
- Given P is in the cart with quantity 98, when I add P with quantity 2, then I get 422 and the quantity stays 98 (R3).

**AC2 – Remove item**
- When I delete an existing item, then I get 204 and the item is no longer in the cart.
- When I delete an unknown item, then I get 404.

**AC3 – Edit item**
- When I set an item's quantity to 7, then I get 200 and the quantity is 7.
- When I send a quantity of 0, a negative quantity or a quantity above 99, then I get 422.

**AC4 – View cart**
- The response contains all items with product data, unit price, line total and reservation status (`reserved`), plus totals (gross, net, VAT). The `reserved` field is part of the contract from the start; until stock reservation exists it is always `true`.
- An empty cart returns an empty item list and totals of 0.
- Given P is in the cart, when P's catalogue price changes, then the next view shows the new price (R5).
- Rounding (R8, 19 %): 3 × 9.99 € gives a line of gross 2997, net 2518, VAT 479; 1 × 1.00 € gives gross 100, net 84, VAT 16; the cart totals are gross 3097, net 2602, VAT 495 (all in cents).

**AC5 – Access control**
- When I call `POST /carts` without credentials, I get 201 with a cart ID and a cart token, and that token grants access to this cart.
- Without a token, any other cart endpoint returns 401.
- With the token of another cart or user I get 404.
- A cart token is never accepted as a JWT and vice versa; tests cover guest, registered user and cross-user access.
- Given a registered user, when two `POST /carts` requests arrive concurrently, then exactly one cart exists afterwards (R7).

**AC6 – Stock reservation**
- Given P has 3 on hand and no reservations, when cart A adds 2, then P has 1 available.
- Given P has 1 available, when cart B adds 2, then I get 409 and neither cart B nor the reservations change.
- Given cart A holds 2 of P, when A decreases the quantity to 1, then P eventually has 1 more available.
- Given cart A holds 2 of P, when A removes the item, then the reservation is eventually released.
- Given a reservation is older than the TTL, then it no longer counts against available stock and the cart item is shown as `reserved: false`.
- Given P has 1 available, when two carts add 1 concurrently, then exactly one succeeds.
- Given cart A holds expired reservations for P and Q, and another cart has taken all stock of P, when A changes the quantity of Q, then the request succeeds and P stays `reserved: false`.
- Given release events are delayed, duplicated or processed out of order, then the reservation always ends up matching the cart's current quantity.
- Given cart A holds an expired reservation for P and no stock of P is available, when A decreases the quantity of P, then the request succeeds and P stays `reserved: false`.

**AC7 – Rate limits**
- When the login limit is exceeded, then further login attempts get 429 with `Retry-After`.
- When the limit for creating guest carts is exceeded, then further `POST /carts` requests without credentials get 429 with `Retry-After`.
- When the limit for failed token authentication is exceeded, then further requests with an invalid token get 429 with `Retry-After`.

## 8. Non-functional Requirements

- PHP and Symfony each in their current stable version; the versions are recorded at project start in `README.md` (Tech Stack).
- Runs via Docker Compose; `make setup` prepares everything and `make qa` runs all checks. Setup instructions in the README.
- The database is SQLite. Switching to another database (e.g. PostgreSQL) must only require changes in Infrastructure adapters, configuration and migrations, never in Domain or Application.
- Automated tests: PHPUnit unit tests for domain and application, PHPUnit integration tests against the real database (concurrency, message transport), Behat acceptance tests (full HTTP round-trips) validated against the contract.
- Static analysis and code style pass without errors, including in CI (GitHub Actions runs `make qa`).
- Architectural boundaries are checked automatically (see `CLAUDE.md`).
- Security: access control on every route that addresses a cart (`{cartId}`), rate limiting on login, guest cart creation and failed token authentication, no secrets in logs, dependency audit in `make qa` (see `CLAUDE.md` → Security).

## 9. Business Questions Decided by Us

The client leaves these to us. Each is recorded with its rationale in `README.md` → "Design Decisions".

- Price snapshot or current price: current price (R5).
- Reservation TTL: 30 minutes, configurable.
- Expiry of anonymous carts: not implemented; expired reservations already free the stock (R11, R12).
- Merging an anonymous cart into the user's cart after login: out of scope (section 3).
