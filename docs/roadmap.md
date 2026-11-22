# Implementation roadmap

Specification: [api-reference.md](api-reference.md). Architecture, conventions and quality gates are the same as in
`thebeardedbear/urssaf-tiers-prestations`, adapted by [ADR 0000](adr/0000-reference-architecture.md): hexagonal layers
enforced by Deptrac, PSR-only runtime, stateless SDK ([ADR 0001](adr/0001-stateless-sdk.md)), Symfony bundle, 100 %
coverage and MSI. This page records what is built and what remains; the direction of the next releases is in
[ROADMAP.md](../ROADMAP.md).

## Status

Every layer below is implemented and tested. The package is not released and the wire mapping is not yet confirmed
against the real sandbox (no official payload example exists, api-reference §5.18): see [Remaining work](#remaining-work).

## Domain (`src/Domain`)

- `Enrolment`: `Enrolment` (id, SIREN, NIC, category, status, requested / updated instants, each nullable on read),
  `EnrolmentId` (UUID), `Siren`, `Nic`, `EnrolmentPage`, `EnrolmentCategory` and `EnrolmentStatus` (enums, api-reference
  §3.1 and §3.2, the raw wire value kept next to a nullable enum view, [ADR 0009](adr/0009-enrolments-and-pagination.md)).
- `Posting`: `PostingRequest` (aggregate of the `POST /declaration/detachement/UE` body) and its value objects
  (`Declarant`, `Declaration`, `Company`, `CompanyReference`, `Address`, `Worker`, `PostingDetails`, `Telework`,
  `HostAddress`, `SubstantialActivity`, `ActivityDeclaration`, `Signature`, `Answer`, `Country`, ...), `PostingReceipt`,
  `DossierId`, `PostingValidationPolicy`, and `Posting\Input` (raw `*Draft` inputs and the readers that turn them into a
  `PostingRequest` while collecting every violation, [ADR 0008](adr/0008-local-validation.md),
  [ADR 0011](adr/0011-country-and-closed-lists.md)).
- `Shared`: `TenantId`, `CalendarDate`, `Instant`, `EmailAddress`, `PhoneNumber`. `Validation`: `Violation`,
  `ValidationResult`. `Exception`: `InvalidValue`, `InvariantViolation`, `ValidationFailed`,
  `PersonalDataNotSerializable`.
- The certificate sum type is **not** in the Domain: it lives in `Application\Port\Urssaf` because its variants carry
  port types ([ADR 0003](adr/0003-certificate-result.md)).

## Application (`src/Application`)

- Port `Port\Urssaf\MobilityGateway` (`requestEnrolment`, `listEnrolments`, `getEnrolment`, `deleteEnrolment`,
  `submitPosting`, `getCertificate`), `CertificateResult` (`CertificateAvailable` / `CertificateInProgress` /
  `CertificateRefusedOrUnknown`), `DownloadedFile`, `BinaryStream`, `ApiErrors`, and the gateway failures
  (`UrssafRequestRejected`, `UrssafAuthenticationFailed`, `UrssafUnavailable`, `UrssafResourceNotFound`,
  `OutcomeUnknown`, [ADR 0004](adr/0004-unknown-outcome-and-retry.md)).
- Ports `CredentialsProvider`, `Credentials`, `TokenStore`, `AccessToken`, `Clock`, `IdGenerator`, `RateLimiter`.
- Commands and queries with handlers: `RequestEnrolment`, `DeleteEnrolment`, `SubmitPosting`, `ListEnrolments` (offset /
  limit / sort, `firstPage()` = offset 0, limit 20), `GetEnrolment`, `GetCertificate`.
- Optional guard (api-reference §4.5): `SubmitPosting::afterCheckingEnrolment()` checks the enrolment status and SIREN
  before the posting; `SubmitPosting::of()` never checks.

## Infrastructure (`src/Infrastructure`)

- `Urssaf\Http`: `OAuth2TokenProvider`, `JsonApiClient` (binary `application/pdf` body, empty 200 / 204 bodies, one
  replay after a 401, no other retry), `RequestBuilder`, `ResponseInterpreter`, `RetryAfter`.
- `Urssaf\UrssafHttpGateway`, `UrssafGatewayFactory`, `HttpTransport`, `GatewayOptions` and `Urssaf\Wire\*` (enrolment and
  receipt mappers, posting request encoder, error parser). No `ApiErrorCode`: no code catalogue is published, the parser
  keeps `code` / `message` / `description` raw and accepts an object, a list or an empty body (api-reference §1.3, §4.11).
- `ApiConfiguration`, `Environment`, `UrssafEndpoints`, `HttpsUri`: environment plus overridable base URI and token URI
  (the sandbox token host is not published), `https` only.
- `TokenStore` (in memory, PSR-6, PSR-16), `Credentials\InMemoryCredentialsProvider`, `RateLimiter`
  (`SlidingWindowRateLimiter`, opt-in), clocks and identifier generators.

## Standalone and Testing

- `Standalone\UrssafMobiliteInternationaleBuilder` and the `UrssafMobiliteInternationale` facade ([standalone.md](standalone.md)).
- `Testing\SimulatedMobilityGateway`, the in-memory platform ([testing.md](testing.md)).

## Bridges

- Symfony bundle `UrssafMobiliteInternationaleBundle` (alias `urssaf_mobilite_internationale`): configuration
  (credentials, environment, base URI, token URI, token cache pool, opt-in rate limiter, validation and certificate
  options), HTTP client, console commands `urssaf:mi:enrolment:request|list|show|delete`,
  `urssaf:mi:certificate <dossierId> <output-file>` and `urssaf:mi:check`, translations, Flex recipe
  ([symfony.md](symfony.md), [ADR 0010](adr/0010-symfony-bridge.md)). There is no posting command.
- **No Doctrine bridge**, no Messenger, Lock nor Scheduler: the package is stateless and must never persist personal
  data (NIR, names, addresses). The API lists no posting, so the caller stores the dossier id that the declaration
  returns. The Deptrac `DoctrineBridge` layer and `src/Bridge/Doctrine/.gitkeep` stay, unused
  ([ADR 0000](adr/0000-reference-architecture.md) row 15). There is no `Contract` layer either: no integration event
  (row 11).

## Runtime dependencies

`ext-json`, `ext-mbstring`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/clock`, `psr/log`,
`psr/cache`, `psr/simple-cache`; Symfony is a `suggest`.

## Tests

- Unit: value objects, mappers, handlers and the gateway with hand-written fakes (no mock library). **No official payload
  example exists** (api-reference §2, §5.18): `tests/Fixtures/wire/` stays empty until payloads recorded on the sandbox
  (stripped of personal data) are added; mappers are tested against payloads built in the tests from the contract
  constraints, with synthetic values.
- Functional: Symfony bundle wiring, the Flex recipe, the standalone builder.
- Contract (`make test-contract`, manual CI job `contract`): opt-in, skipped without `URSSAF_SANDBOX_CLIENT_ID` and
  `URSSAF_SANDBOX_CLIENT_SECRET`; optional `URSSAF_SANDBOX_BASE_URI` and `URSSAF_SANDBOX_TOKEN_URI` (variables in the
  [README](../README.md#sandbox-contract-tests)).
- Benchmarks: PHPBench (`make bench`, `tests/Benchmark`).
- Contract diff (cheap, no credentials): `docs/openapi/mobi-prd.json` and `mobi-sb.json` must stay identical except
  `servers[0].url`.

## Remaining work

1. Run the contract tests against the real sandbox, in this order: confirm the sandbox token host, the enrolment
   life-cycle (validation without a human?), the declaration path and 200 body, the certificate status codes (204 vs 400,
   refused), the pagination defaults. Then record fixtures stripped of personal data, adjust the affected ADRs and
   remove the settled points from api-reference §5.
2. Publish the Flex recipe in `symfony/recipes-contrib` at release, with the agreement of the maintainer
   ([ADR 0010](adr/0010-symfony-bridge.md), decision 7).
3. Tag the first release.

## Notes

- The CESU, PAJE, auto-entrepreneur and Mobilité internationale APIs share the `/tiersdecl/v1` gateway (same token
  mechanism, similar envelope). A shared library (OAuth2 token provider, JSON API client, error envelope) may be
  considered later; it is **out of scope** for this package, which keeps its own copy like the other URSSAF packages.
