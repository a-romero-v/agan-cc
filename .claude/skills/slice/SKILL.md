---
name: slice
description: Build one use case (endpoint or event handler) end to end in this project, from the API contract to the tests. Use when starting a new command, query, endpoint or event handler, e.g. "add item to cart" or "release stock on item removed".
---

# Build one use case end to end

Work through the steps in order. Each step names the rule file that holds the details; read it instead of guessing. Stop and ask if the task contradicts `SPEC.md` or the API contract.

Argument: the use case, ideally with its AC and rule IDs from `SPEC.md` (e.g. `add item, AC1, R1-R3`).

## 0. Scope
- Find the acceptance criteria and business rules in `SPEC.md` that this use case covers. List them; they become the tests in step 6.
- If the change is larger than one use case, present a plan first.

## 1. Reference project
- Look at how `../sample-hexagonal-api` solves the same kind of thing (controller, handler, mapper, Behat steps).
- Reuse what fits. Note every deviation for step 7.

## 2. Contract first
- Change or add the fragments under `api-contract/` (`paths/<resource>/<operation>.yaml`, `schemas/<Name>.yaml`).
- `docker compose exec php composer app:openapi:build`.
- Errors use the `ProblemDetails` schema and carry an `errorCode` (`.claude/rules/errors.md`).

## 3. Domain
- Aggregate method with an intention-revealing name, value objects, repository interface, ports.
- Domain events for state changes other contexts care about (`.claude/rules/domain-events.md`).
- Exceptions extend `AbstractDomainException` with `#[DomainError]` and the right `ErrorType`; pick the code from the context's range (`.claude/rules/errors.md`).
- No framework imports, no `new \DateTimeImmutable()`, no setters.

## 4. Application
- One command or query class plus one handler, in its own folder (`CLAUDE.md`, "Structure and naming"). Commands return void; queries return `*View` read models.
- The command handler owns the transaction: `TransactionInterface::run()`, load the aggregate inside it, save, then publish the released events before the block ends.
- Other contexts only through a port of this context (`CLAUDE.md`, "Cross-context adapters").

## 5. Infrastructure
- New or changed tables: present them to the user first (columns, keys, indexes, constraints) and wait for the go-ahead. Conventions are in `.claude/rules/persistence.md`, "Schema".
- Record, mapper and repository in `<Context>/Infrastructure/Persistence`; add the context's mapping to `config/packages/doctrine.yaml` if it is the first Record. Generate the migration with `doctrine:migrations:diff` and read it before keeping it.
- Controller: validated DTO via `#[MapRequestPayload]`, UUID route requirements, no try/catch. Every route with `{cartId}` carries `#[IsGranted(CartVoter::…, subject: 'cartId')]` (`.claude/rules/security.md`).
- Register handlers in `config/services.yaml` (bus locators; event handlers by the `messenger.message_handler` tag).
- A new bounded context must be added to the list in `deptrac-contexts.php`.

## 6. Tests
- Unit tests in `tests/Unit/` mirroring `src/`, with in-memory port implementations. Every repository port has one.
- Behat scenario per AC, tagged with its ID, in `features/<context>/`, using `the request body matches the OpenAPI spec` and `the response matches the OpenAPI spec`. Full response snapshots, also for failures (`CLAUDE.md`, "Tests").
- Integration test in `tests/Integration/` where Behat cannot reach: concurrency, the real Doctrine transport.
- Every security rule touched gets a test.

## 7. Wrap up
- Record deviations from the reference in `README.md`, "Deviations from the Reference Project"; non-obvious decisions in "Design Decisions".
- Then run `/step-done`.
