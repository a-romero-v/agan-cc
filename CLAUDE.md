# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Coding challenge: a shopping cart REST API in Symfony 8.1 / PHP 8.5. It demonstrates hexagonal architecture, DDD, CQRS and event-driven design. Code quality matters more than speed.
- This file describes the **target state**. The scaffold (Makefile, Composer scripts, Deptrac, `src/`) is built step by step, so some commands below may not exist yet.
- Business rules (R1…), acceptance criteria (AC1…) and the Ubiquitous Language are in `SPEC.md`. If a task contradicts `SPEC.md` or the API contract, **stop and ask**.
- Order: core cart for guests (AC1–AC4), then registered users and rate limits (AC5, AC7), then stock reservation (AC6). Cart routes are never unprotected: the cart-token authenticator and `CartVoter` are built together with `POST /carts`, before the first `{cartId}` route.
- Progress is tracked in `docs/plan.md`. Update it after each completed step.
- Everything is in English: code, docs and commits.
- Replies: extremely concise, key information only, no long code snippets.

## Reference project

Many approaches (OpenAPI build/sync, Composer scripts, buses, domain event publisher, exception mapping, Behat setup, DB reset command) come from the reference project <https://github.com/a-romero-v/sample-hexagonal-api>, either copied 1:1 or adapted.

- A local checkout is expected next to this repository, at `../sample-hexagonal-api`. Read it there instead of fetching from GitHub.
- Before building such a part, check how the reference project does it and reuse what fits.
- **Every deviation from the reference project must be recorded** in the `README.md` section "Deviations from the Reference Project": what changed, and why. Add the entry in the same commit as the change.
- Known deviations are listed in each `.claude/rules/*.md` file under "Deviations from the reference".

## Workflow

- **One session per milestone.** Each milestone in `docs/plan.md` (M2, M3, …) is worked on in a fresh session, to keep the context small. At the start of a session, read `docs/plan.md` to see where the work stands. When the last step of a milestone is committed, say so and ask the user to start a new session; do not begin the next milestone in the same one. Anything the next session needs must be in the repository (plan, README, rules), not only in the conversation.
- `/slice <use case>`: build one use case end to end, contract first (`.claude/skills/slice`).
- `/qa`: run the checks. Clears the test cache and runs `make qa` in a separate context on a small model, so only the summary comes back (`.claude/skills/qa`). Prefer it over calling `make qa` directly.
- `/step-done`: at the end of every step. Runs the checks, updates the docs, proposes the commit and waits (`.claude/skills/step-done`).
- Agent `reviewer` (`.claude/agents/reviewer.md`): read-only review against the spec and the rules. Only on request, for larger steps, before the commit is proposed.

## Commands

Claude Code runs on the host, PHP runs only in the Docker container. **Never run PHP or Composer on the host.** Use `make …` or `docker compose exec php <cmd>`.

**Makefile (host):** thin wrapper around `docker compose` and the Composer scripts.
- `make setup` — build, start, install, reset dev and test DBs, load fixtures, generate the JWT key pair if missing
- `make up` / `make down` / `make shell`
- `make qa`, `make test`, `make behat`, `make analyse`, `make cs-fix` — delegate to the Composer scripts

**Composer scripts (container):** the actual workflows.
- `composer app:qa` — `app:openapi:check-sync` → `app:validate:openapi` → `composer audit` → cs check → phpstan → deptrac → phpunit → behat (fails fast). **Must be green before a task counts as done.**
- `composer app:test` / `composer app:test:unit` / `composer app:test:integration` — PHPUnit. Single test: `php bin/phpunit tests/Unit/Path/FooTest.php --filter testName`
- `composer app:behat` — Behat (`APP_ENV=test`, needs the test DB). Single feature: `APP_ENV=test vendor/bin/behat features/<file>.feature`
- `composer app:analyse` — PHPStan level 8 on `src/` (warms the dev cache itself)
- `composer app:deptrac` — two runs: `deptrac.yaml` (layer direction) and `deptrac-contexts.php` (context boundaries). A new context must be added to the list in `deptrac-contexts.php`.
- `composer app:cs:check`, `composer app:cs:fix` — PHP-CS-Fixer, `@Symfony` plus `declare_strict_types`
- `composer app:db:reset` / `composer app:db:reset:test`, `composer app:test:clear:cache`
- `composer app:openapi:build` — regenerate `docs/openapi.yaml`

New workflows get a Composer script first, then a Make target if needed on the host.

**Docker Compose:** service `php` (app, PHP's built-in server with `PHP_CLI_SERVER_WORKERS` on port 8000) and service `worker` (same image, runs `php bin/console messenger:consume domain_events -vv`; logs in `var/log/domain_events.log` and via `docker compose logs worker`). The worker does not reload code: run `docker compose restart worker` after changing an event handler. `make db-reset` restarts it. The image is `php:8.5-cli-alpine`, which already ships `pdo_sqlite` and OPcache; only `intl` is added. The host port is `APP_PORT` (default 8000).

**CI:** one GitHub Actions workflow that runs `make setup` and `make qa` on every push and pull request.

## Architecture

Bounded contexts in `src/`: `Cart`, `Product` (catalogue), `Inventory` (stock, reservations), `User`, plus `Shared`. Each has `Application/`, `Domain/`, `Infrastructure/`.

Layer rules (enforced by Deptrac):
- **Domain**: no framework, Doctrine or third-party imports. Aggregates, value objects, repository interfaces, domain events, ports, exceptions.
- **Application**: depends only on Domain. Single-responsibility handlers in `Application/Command` and `Application/Query`; pure-PHP event handlers in `Application/EventHandler`, registered as Messenger handlers in `config/services.yaml` (no wrapper classes).
- **Infrastructure**: the only layer that may import Symfony or Doctrine.
- Contexts never import each other's Domain. Two exceptions: `*/Domain/Event` may be imported by other contexts' Application event handlers, and `*/Domain/Exception` by cross-context adapters (see below). Cross-context calls go through a port of the calling context (e.g. `Cart` → `ProductCatalogInterface`, `StockReservationInterface`).
- Time, IDs and tokens come from ports (`ClockInterface`, generators). Domain code never calls `new \DateTimeImmutable()` or imports `symfony/uid`. Transactions go through `TransactionInterface` (`Shared/Application`; adapter `DoctrineTransaction`).
- After changing files in `config/`, clear the test cache (`composer app:test:clear:cache`) before trusting a test result; the test environment does not always notice.

Domain model: `Cart` is the aggregate root; items change only through it (R2, R3). `Product` holds catalogue data, never stock. Value objects (`CartId`, `ProductId`, `Quantity`, `Money`, `TaxRate`) are immutable and self-validating. `Money` = integer cents + currency, never floats.

CQRS: commands return void and go through `CommandBusInterface`, queries through `QueryBusInterface` (ports in `Shared/Application/Bus`, in-process adapters with a service locator, no Messenger). Queries return read models, never entities. A controller that needs a body after a command dispatches a query.

Cart view across contexts: the view query handler loads the cart and reads product data and the current price through `ProductCatalogInterface` and the reservation status through `StockReservationInterface`. Both ports take the whole list at once (all product IDs, the whole cart), never one call per item. No SQL joins across context tables, not even for read models.

Cross-context adapters: the adapters live in `Cart/Infrastructure` and call the other context through `QueryBusInterface` (reads) or `CommandBusInterface` (writes), e.g. a "products by IDs" query of `Product`. They translate the other context's read model into Cart's own data objects and its failures into Cart exceptions. These adapters are the only place that may import another context's `Application` query, command and read-model classes and its `Domain/Exception` classes (to catch and translate them); Deptrac allows exactly that. The rest of the other context's Domain and its Infrastructure stay off limits.

API-first: `docs/openapi.yaml` is generated from `api-contract/`. Change the fragments **before** implementing, then `composer app:openapi:build`. Layout as in the reference: `api-contract/base.yaml` (info, servers, security schemes), `api-contract/paths/<resource>/<operation>.yaml` and `api-contract/schemas/<Name>.yaml`. The console commands `app:openapi:build` and `app:openapi:check-sync` and `bin/validate-openapi` are taken from the reference. The reference's `ErrorResponse` schema is replaced by `ProblemDetails`. Behat scenarios with JSON bodies must use `the request body matches the OpenAPI spec` and `the response matches the OpenAPI spec`.

### Structure and naming (as in the reference)
- One folder per use case: `Application/Command/<Name>/` with `<Name>Command` and `<Name>Handler`; `Application/Query/<Name>/` with `<Name>Query` and `<Name>Handler`.
- Read models are `*View` classes in `Application/Query/ReadModel/`.
- Event handlers live in `Application/EventHandler/` and end with `Handler`.
- Ports of the domain live in `<Context>/Domain/Port/`. Repository interfaces sit next to their aggregate. Application-level ports that are not domain concepts (`TransactionInterface`, the buses) live in `Shared/Application`.
- Domain events in `Domain/Event/`, exceptions in `Domain/Exception/`.
- Controllers and request DTOs in `Infrastructure/Http/`; records, mappers and repositories in `Infrastructure/Persistence/`; security adapters in `Infrastructure/Security/`.

### Detailed design
The detailed rules live in `.claude/rules/` and are loaded automatically: `domain-events.md`, `inventory.md`, `persistence.md`, `errors.md`, `security.md`.

## Security

Security is a first-class requirement, on a par with the business rules. Details and the threat list are in `.claude/rules/security.md`. Non-negotiable:

- **Deny by default.** Every route is authenticated unless it is explicitly public (`POST /carts`, `POST /auth/login`, product reads).
- **Authorisation on every route with `{cartId}`:** `#[IsGranted(CartVoter::VIEW|EDIT, subject: 'cartId')]`. A route without it is a bug, and a test fails when one is missing. `POST /carts` has no `cartId` and is the only cart route without it.
- **No existence leaks:** a foreign cart is 404, never 403 (R6), with a response identical to an unknown cart. Items are loaded only through their cart.
- **Secrets:** cart tokens come from a CSPRNG port, are returned once and stored only as a hash. Private keys are never committed: the JWT key pair is generated by `make setup` and git-ignored. Tokens, passwords and `Authorization` headers never appear in logs, events, error responses or test output.
- **Input:** request bodies go through validated DTOs (`#[MapRequestPayload]`); nothing is bound to domain objects directly. Raw SQL uses bound parameters only.
- **Rate limiting** on login, guest cart creation and failed token authentication.
- **Errors reveal nothing:** 500 responses carry no internals; login failures do not say whether the user exists.
- Every security rule has a test. If a task would weaken one of these points, **stop and ask**.

## Code rules
- `declare(strict_types=1);` everywhere. Classes `final`, properties `readonly` unless there is a reason (see `.claude/rules/persistence.md` for `*Record`).
- No setters in the domain. Named constructors and intention-revealing methods (`$cart->addItem(...)`).
- No PHPStan baseline. No `@phpstan-ignore` without a comment explaining why.
- Never weaken tests, Deptrac rules or the PHPStan level to get something green. Ask before adding dependencies.
- Present a plan before larger changes. Work in small steps, each with a green `make qa`.
- Rationale for non-obvious decisions goes in the `README.md` section "Design Decisions".

## Tests
- `tests/Unit/` mirrors `src/`, no DB or HTTP. Use in-memory port implementations instead of mocking domain classes. Every repository port has one. (Deviation: the reference mocks its repository ports.)
- `features/` + `tests/Behat/` (`ApiContext`, `DatabaseContext`): full HTTP round-trips against the test DB. Every AC in `SPEC.md` has a scenario tagged with its ID (e.g. `@AC3`). AC bullets that Behat cannot exercise (concurrent requests, delayed or reordered events) are covered by integration or unit tests instead.
- `tests/Unit/Architecture/`: one test scans `src/` and enforces the conventions from "Structure and naming" that can be decided mechanically: suffixes (`*Command`, `*Query`, `*Handler`, `*View`), read models `final readonly`, only interfaces in `Domain/Port/`, `*Record` only in `Infrastructure/Persistence/`, controllers only in `Infrastructure/Http/`, all classes `final` (exceptions listed in the test). Deptrac checks dependencies, not names or locations. A new convention gets a check here, not just a sentence.
- Behat style (as in the reference):
  - Features live in `features/<context>/`, one focused feature per file.
  - The endpoint is visible in the scenario (`When I send a POST request to "/carts"`); authentication and data setup are abstracted into steps.
  - Assert the full JSON response as a snapshot, not single fields; use the `@any` placeholder for generated values (IDs, tokens, timestamps).
  - Failure cases assert the whole Problem Details response, including `errorCode`.
- `tests/Integration/`: PHPUnit against the real test DB, without HTTP, part of `composer app:test`. "Integration" names the technique, not the importance. Planned tests:
  - Concurrency (R7, R14): Behat runs requests one after another in a single process, so it cannot produce a race. These tests work at handler level with **two PHP processes** running the same command at the same time, then assert the outcome (one cart for the user; exactly one of two carts gets the last item).
  - Safety net for R7: force a second insert past the handler's check and assert that the unique violation becomes the domain exception and the handler returns the existing cart. Deterministic; this is the path PostgreSQL would rely on.
  - Optimistic lock on the cart: `save()` with a stale version fails with the `Conflict` exception.
  - Real Doctrine transport: request, `messenger:consume --limit=1`, assert.
  - Route coverage: every route with `{cartId}` carries `#[IsGranted]` (needs the router, see `.claude/rules/security.md`).
  - The sequential behaviour of the same rules (a second `POST /carts` returns the same cart with 200) stays a Behat scenario.
- Shared test fixtures: `tests/Shared/Fixture`. Dev fixtures: `src/Shared/Infrastructure/DataFixtures`.

## Git and commits

**Never commit without my explicit approval.** Never push, amend, rebase or reset existing commits unless I ask.

When a step is done and `make qa` is green:
1. Propose the commit(s): for each, the files and the full message.
2. Wait for my approval. Only then run `git add` / `git commit`.

The history must let reviewers follow the progress and the approach step by step:
- **One logical change per commit.** Separate scaffolding, contract changes, domain, application, infrastructure and refactorings when they stand on their own. Never mix unrelated changes or formatting noise into a feature commit.
- Every commit leaves `make qa` green.
- **Conventional Commits** with the context as scope: `feat(cart): add item to cart`. Subject in imperative mood, ≤ 72 characters, no trailing period.
- **Body is required** for anything non-trivial: what changed and *why*, the chosen approach, trade-offs. Mention when something is taken from or deviates from the reference project.
- Footer references the spec: `Refs: AC2, R3`.
