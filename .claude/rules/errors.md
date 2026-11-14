# Error Handling

- The Domain knows no HTTP. No HTTP status codes in Domain or Application.
- Domain exceptions extend `AbstractDomainException` (`Shared/Domain/Exception`) and carry `#[DomainError(errorCode, type, message)]`. The name avoids a clash with PHP's built-in `\DomainException`.
  - `type` is the business-level enum `ErrorType` (`NotFound`, `Conflict`, `Invalid`).
  - The base class reads the attribute by reflection and interpolates `{{ placeholder }}` tokens from a context array (as in the reference). Subclasses only add a typed constructor.
- `ApiExceptionSubscriber` maps all exceptions centrally to RFC 9457 Problem Details (`application/problem+json`), with `errorCode` as an extension member. **Every** error response carries an `errorCode`, including the generic HTTP errors (see the 4xxx table).
  - Mappers implement `ExceptionMapperInterface`, tagged `app.exception_mapper`. The first mapper that supports the exception wins (as in the reference).
  - `DomainExceptionMapper`: the only place that turns `ErrorType` into a status (`NotFound` → 404, `Conflict` → 409, `Invalid` → 422).
  - `HttpExceptionMapper`: **every** `HttpExceptionInterface` (400 malformed JSON, 415, router 404/405, 429 rate limit with `Retry-After`), access denied and validation errors.
  - Framework exceptions get a fixed `detail` per case, never the exception message. Only `Allow` and `Retry-After` are passed on from the exception's headers.
  - The fallback lives in `ApiExceptionSubscriber`: anything no mapper supports becomes a 500 Problem Details response without internals.
  - The 4xxx codes are the enum `SharedErrorCode` (`Shared/Infrastructure/Http`); responses are built only through `ProblemDetailsResponse`.
  - Open until Symfony Security is installed (M5): the mapper handles `AccessDeniedHttpException`; Security's own `AccessDeniedException` must be covered and tested then.
  - Logging: status ≥ 500 as `error`, everything else as `info`.
- The 401 entry point and the login failure handler also return Problem Details. Lexik's default JSON format is not used.
- Validation errors: 422 with the extension member `violations: [{field, message}]`.
- Controllers have no try/catch.
- A new exception needs no mapper change. Only a new `ErrorType` does.

## Cases to get right
- Foreign or unknown cart (R6): there is exactly one path. `CartVoter` denies access in both cases, and `HttpExceptionMapper` turns every access denial of an authenticated principal into the same 404 with `errorCode` `4001` ("resource not found"). The two cases are therefore indistinguishable. Without a valid token the request never reaches the voter: the entry point answers 401 (`4004`). `CartNotFoundException` (1xxx) stays as a safeguard in the handlers but is unreachable for anyone but the owner.
- Insufficient stock (R9): the port belongs to Cart, so the exception the API shows is a Cart exception (1xxx, `Conflict`). The adapter in `Cart/Infrastructure` translates Inventory's result. 5xxx is for errors inside Inventory.
- Concurrent modification of a cart (optimistic lock, see `persistence.md`): `Conflict`, 409.
- Unknown product (R1): 404 on `GET /products/{productId}`, but 422 when the `productId` comes from a request body. These are two exceptions with different `ErrorType`, not one.

## Error code ranges
| Range | Context |
|---|---|
| 1xxx | Cart |
| 2xxx | Product |
| 3xxx | User |
| 4xxx | Shared, see below |
| 5xxx | Inventory |

Shared codes (generic, not tied to a context):

| Code | Status | Meaning |
|---|---|---|
| `4000` | 500 | Internal error (fallback); also any other 5xx HTTP exception, which keeps its status |
| `4001` | 404 | Resource not found: every access denial of an authenticated principal and router 404 |
| `4002` | 422 | Validation error |
| `4003` | 400 | Malformed request (e.g. invalid JSON); also any 4xx HTTP exception without a code of its own, which keeps its status |
| `4004` | 401 | Missing or invalid credentials (entry point, login failure) |
| `4005` | 405 | Method not allowed |
| `4006` | 415 | Unsupported media type |
| `4007` | 429 | Rate limit exceeded |

## Deviations from the reference
- `#[DomainError]` carries an `ErrorType` instead of `httpStatusCode`. The reference keeps the HTTP status in the Domain (`#[ApiException]`, `ApiBaseException`).
- Responses are RFC 9457 Problem Details instead of `{code, error}`.
- `HttpExceptionMapper` covers all HTTP exceptions, not only access denied and validation.
- Access denied is 404 instead of 403, and `4001` means "resource not found" instead of "access denied".
