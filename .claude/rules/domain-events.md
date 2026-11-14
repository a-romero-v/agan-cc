# Domain Events

One port, one adapter. Kept deliberately small: there is a single consumer (Inventory releases reservations).

- Aggregates use the `AggregateRoot` trait (`recordEvent()` / `releaseEvents()`).
- Events implement the `DomainEvent` marker interface, are `final readonly` and carry only scalars and value objects, never entities. Timestamps come from `ClockInterface`.
- Command handlers publish released events via `DomainEventPublisherInterface` **after flush, before commit, inside the `TransactionInterface` block**.
- `MessengerDomainEventPublisher` is the only adapter. It dispatches the event itself to the Messenger bus, without a wrapper message.
- Routing is by interface: `App\Shared\Domain\DomainEvent` → transport `domain_events`.
- The transport is set only by `MESSENGER_TRANSPORT_DSN`: `sync://` in test, `doctrine://default` by default. AMQP works without code changes.
- Messenger is used **only** for domain events, never for commands or queries.
- The queue table `messenger_messages` is created by `messenger:setup-transports`, which is part of `app:db:reset`.
- Verified at scaffold: web requests and the worker share the SQLite file without lock errors. Three concurrent senders (150 transactions each, publishing inside the transaction) against a running worker: 450 sent, 450 handled, no "database is locked". `BEGIN IMMEDIATE` plus the busy timeout make writers queue up.

## Handlers
- An event handler is a pure-PHP class in `Application/EventHandler` with `__invoke(ConcreteEvent $event)`. One handler per event type.
- It is registered as a Messenger handler in `config/services.yaml` with the tag `messenger.message_handler`. No Symfony attribute and no wrapper class, so Application stays framework-free. Verified in M3: Messenger reads the event type from the `__invoke()` parameter; `DomainEventDeliveryTest` guards the routing by interface and the tag-based registration with a fixture handler registered in `config/services_test.yaml`.
- There are no `*Subscriber` and no `*MessengerHandler` classes.

## Rules
- Delivery is at-least-once, so event handlers must be **idempotent**.
- Event handlers do not throw domain exceptions towards the client. A release handler ignores stale versions instead of failing.
- With the Doctrine transport on the same DB, publishing inside the transaction acts as a transactional outbox. With other transports, an event can be lost after commit. That risk is accepted and documented in the README.
- A release event dropped after all retries is healed by the reservation TTL (R11, R12). A `failed` transport is optional.
- Events from other contexts (`*/Domain/Event`) are the published language. They may be imported only by Application event handlers. Deptrac allows exactly that.
- Test and production differ: with `sync://` the handler runs inside the request transaction and its failure reaches the HTTP response (as a 500); in production it runs after the commit in its own transaction. At least one integration test uses the real Doctrine transport (request, `messenger:consume --limit=1`, assert).

## Deviations from the reference
- One adapter instead of two: no `SymfonyDomainEventPublisher` and no EventDispatcher subscribers. The second adapter would never be active here.
- No `DomainEventMessage` wrapper and no `*MessengerHandler` classes with type checks. Events are routed by interface and the Application handlers are registered by tag.
- No `UnwrapDomainExceptionMiddleware`: event handlers do not surface domain exceptions.
- Events are published inside the transaction. The reference publishes after `save()` without a surrounding transaction.
- The default transport is `doctrine://default` instead of AMQP (no RabbitMQ).
- Events carry no entities.
