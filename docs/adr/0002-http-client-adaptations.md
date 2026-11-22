# ADR 0002 — HTTP client adaptations

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md) rows 8–9 and 14. Precedents:
urssaf-plateforme-numerique ADR 0002, urssaf-tierce-declaration-cesu ADR 0002.

## Context

The reference `JsonApiClient` sends JSON bodies, `x-request-id` and `recouv.application.id`, refuses non-JSON bodies and
classifies errors with a published code catalogue. This contract declares no header (api-reference §1, §5.16), returns a
PDF on `GET /certificat/{id}`, an empty 200 on `DELETE /enrolements/{id}` and a 204 on the certificate, an error body
that is a list on the declaration 400, a single object elsewhere and empty on the declaration 401 / 500 (§1.3), a
PDF-only 403 on the declaration and a PDF-only 429 everywhere. The sandbox token host is not published (§5.10).

## Decision

- **Requests.** `Http\ApiRequest` has `get(operation, path, query)`, `post(operation, path, body)`,
  `delete(operation, path)` and `download(operation, path)`. Query strings are RFC 3986; `offset`, `limit`, `sort` are
  flat parameters (`style: form`, `explode: true`, [ADR 0009](0009-enrolments-and-pagination.md)). Path identifiers
  are UUIDs validated locally, so a path never carries free text.
- **Headers.** `Authorization: Bearer`, `Accept: application/json` (`application/pdf, application/json` on the
  download), `Content-Type: application/json` only when there is a body, `User-Agent`
  (`thebeardedbear-urssaf-mobilite-internationale`). No request-id nor application-id header by default;
  `ApiConfiguration` accepts an optional request-id header name, whose value is a UUID per attempt from the
  `IdGenerator` port (CESU / PN pattern), for when URSSAF asks for one.
- **Bodies.** 201 / 200 JSON bodies go through `Payload`; the `DELETE` 200 and the certificate 204 bodies are neither
  read nor decoded; the certificate 200 body is never decoded ([ADR 0005](0005-files.md)).
- **Status mapping** (every operation, unless [ADR 0003](0003-certificate-result.md) or
  [ADR 0004](0004-unknown-outcome-and-retry.md) says otherwise):

  | Status | Result |
  |---|---|
  | 2xx declared for the operation | success |
  | 400 | `UrssafRequestRejected` with the parsed `ApiErrors` |
  | 401 | token invalidated, call replayed **once** (a 401 proves the call was not processed), then `UrssafAuthenticationFailed` |
  | 403 on `submitPosting` | `UrssafRequestRejected` (status 403 kept): "not authorised to declare for this company" (PDF §4.5.3), a business refusal, not a credential failure; nothing recorded; 401 remains the only authentication signal on this operation |
  | 403 elsewhere | `UrssafAuthenticationFailed` (reference) |
  | 404 on the four enrolment operations | `UrssafResourceNotFound` (not retryable, errors kept) |
  | 429 | `UrssafUnavailable::quotaExceeded()` (`isQuotaExceeded()`, `Retry-After` delta-seconds when present) |
  | 5xx | `UrssafUnavailable` on reads and `DELETE`; `OutcomeUnknown` on the two `POST` ([ADR 0004](0004-unknown-outcome-and-retry.md)) |
  | anything else (1xx, 3xx, 4xx not listed above, undeclared 2xx) | `UnexpectedUrssafResponse`, except any 2xx on the two `POST` that is not the declared success with a readable body: `OutcomeUnknown` |

- **Error bodies.** `Wire\ErrorPayloadParser` accepts an object, a list of objects or an empty / unreadable body and
  returns `ApiErrors` (possibly `none()`) of `ApiError(code, message, description)`, all raw nullable strings; the
  swapped descriptions of `code` / `description` (§1.3) are not reinterpreted. Errors are kept on the exception, never
  logged.
- **Token endpoint.** `POST {tokenUri}` form-urlencoded with scope `ods.dlagd`; 400 / 401 / 403 →
  `UrssafAuthenticationFailed`, 429 / 5xx / transport → `UrssafUnavailable::whileAuthenticating()`.
- **URIs.** `ApiConfiguration(environment, ?baseUriOverride, ?tokenUriOverride, ?requestIdHeader, …)`. Defaults:
  `Environment` host + `UrssafEndpoints::API_BASE_PATH` / `TOKEN_PATH`. An override must be an absolute URI without
  query, fragment or user info, with the `https` scheme; `http` is refused, even for a local host (a mock server
  needs a TLS certificate). This closes the CESU / PAJE finding SR-01 (secret and token sent in clear).
- **Transport failures** keep the class name of the PSR-18 exception, never its message.
- **Logs**: method, path, status, duration, request id when configured; never a body, a token, a query string or a
  header value.
- **Quota**: an opt-in `RateLimiter` port with `SlidingWindowRateLimiter` is provided, disabled by default
  ([ADR 0000](0000-reference-architecture.md) row 14, [ADR 0010](0010-symfony-bridge.md) `rate_limiter`); a 429 stays
  `UrssafUnavailable::quotaExceeded()`.

## Consequences

- The client stays an internal Infrastructure class; only its request shapes and header policy differ from the
  reference.
- When the contract tests settle the token host, a header or the 403 / 404 bodies, the change is configuration or a
  one-line mapping change.

## Amendments

- **2026-11-22 (review).** The PSR-18 client, whether the bundle's default (created with `max_redirects: 0`) or one
  provided by the host (`http_client`, `HttpTransport`), must neither follow redirects nor retry: a followed 307 / 308
  would replay a `POST` with the declaration (NIR) or the client secret to another URL, and a silent retry defeats
  [ADR 0004](0004-unknown-outcome-and-retry.md). A 3xx answer therefore reaches `ResponseInterpreter` and ends as
  `UnexpectedUrssafResponse` (the "3xx" row of the status table; covered by tests on the API and the token endpoint).
