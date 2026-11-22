# Implementation roadmap

Specification: [api-reference.md](api-reference.md). Architecture, conventions and quality gates are the same as in
`thebeardedbear/urssaf-tiers-prestations` (hexagonal layers enforced by Deptrac, PSR-only runtime, Symfony and
Doctrine bridges, 100 % coverage and MSI).

## Already in place (skeleton)

- `SpecVersion::CURRENT` (`1.0.0`, version of the published PDF; the Swagger is 1.0.8, see api-reference header).
- `Infrastructure\Urssaf\Environment` (Sandbox `https://api-edi.urssaf.fr` / Production `https://api.urssaf.fr` base
  hosts) and `Infrastructure\Urssaf\UrssafEndpoints` (token path `/api/oauth/v1/token`, scope `ods.dlagd`, base path
  `/tiersdecl/v1/mobilite`).

## Domain (`src/Domain`)

- `Enrolment\Enrolment` (id, SIREN, NIC, category, status, requested / updated dates), `EnrolmentId` (UUID),
  `EnrolmentCategory` (enum, api-reference §3.1, raw fallback), `EnrolmentStatus` (enum, §3.2, raw fallback),
  `Siren`, `Nic` value objects (lengths from §2; stricter formats only once confirmed, see api-reference §5).
- `Posting\PostingRequest` (aggregate of the `POST /declaration/detachement/UE` body) with value objects: `Declarant`,
  `Company` (`entrepriseDeclarant` / activity company), `CompanyReference` (`referenceEntreprise`), `Address`,
  `Worker` (`personne`), `ActivityDeclaration` (`Activite`, nature enum §3.6, optional `ActivityDetails`),
  `Telework`, `HostAddress` (`adresseAccueil`), `SubstantialActivity` (`activiteSubstentielle`, criterion enum §3.5),
  `Signature`, `Answer` (`NC` / `OUI` / `NON`, §3.4), `Country` (§3.3, `""` rejected).
- `Posting\PostingReceipt` (declaration id(s) returned by the 200 response).
- `Certificate\CertificateResult` (sum type: `Available` with the PDF content / `InProgress` / `RefusedOrUnknown`, see
  api-reference §4.3).
- `Domain\Exception\*`.

## Application (`src/Application`)

- Port `Port\Urssaf\MobilityGateway` (`requestEnrolment`, `listEnrolments`, `getEnrolment`, `deleteEnrolment`,
  `submitPosting`, `getCertificate`).
- Ports `CredentialsProvider`, `TokenStore` (same contracts as Tiers de prestations).
- Commands / queries with handlers: `RequestEnrolment`, `DeleteEnrolment`, `ListEnrolments` (offset / limit / sort),
  `GetEnrolment`, `SubmitPosting`, `GetCertificate`.
- Optional guard (api-reference §4.5): check the enrolment status before `SubmitPosting`; off by default.

## Infrastructure (`src/Infrastructure`)

- `Urssaf\Http\OAuth2TokenProvider`, `JsonApiClient` (extended for a binary `application/pdf` body and for empty 200 / 204
  bodies), `RetryAfter` (reuse the Tiers de prestations design).
- `Urssaf\UrssafHttpGateway` + `Urssaf\Wire\*` mappers (enrolment, posting request builder with the conditional rules
  the contract states, certificate status mapping 200 / 204 / 400 / 404).
- `Urssaf\Exception\ApiErrorCode` is **not** created: no code catalogue is published; the error parser keeps `code` /
  `message` / `description` raw and accepts an object, a list or an empty body (api-reference §1.3, §4.11).
- `ApiConfiguration` (environment + overridable base URI and token URI, since the sandbox token host is not published).

## Bridges

- Symfony bundle `UrssafMobiliteInternationaleBundle` (alias `urssaf_mobilite_internationale`): configuration
  (credentials, environment, base URI, token URI), HTTP client, token cache pool, console commands
  `urssaf:mi:enrolment:request|list|show|delete`, `urssaf:mi:certificate <dossierId> <output-file>` and `urssaf:mi:check`
  ([symfony.md](symfony.md), ADR 0010).
- Doctrine bridge: optional, only if the package must **track** its own posting requests and enrolments (store the
  `idEnrolement`, the declaration `id`, the status and last certificate state; the API offers no listing of postings,
  so the dossier id returned by the declaration is the only handle). Not needed for a first release; personal data
  (NIR, names, addresses) must never be persisted by the package.

## Runtime dependencies to add with the code

`ext-json`, `ext-mbstring`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/clock`, `psr/log`,
`psr/simple-cache` (or `psr/cache`), plus the matching `suggest` entries (Symfony and Doctrine bridges).

## Tests

- Unit: value objects, mappers and handlers with a fake gateway. **No official payload example exists** (api-reference
  §2, §5): `tests/Fixtures/wire/` is empty until payloads recorded on the sandbox (stripped of personal data) are added;
  until then mappers are tested against payloads built from the contract constraints in the tests themselves.
- Functional: Symfony bundle wiring.
- Contract (`make test-contract`, manual CI job): sandbox credentials `URSSAF_SANDBOX_CLIENT_ID/SECRET`, optional
  `URSSAF_SANDBOX_BASE_URI` and `URSSAF_SANDBOX_TOKEN_URI`. Goals, in order: confirm the sandbox token host, the
  enrolment life-cycle on the sandbox (validation without a human?), the declaration path and 200 body, the
  certificate status codes (204 vs 400, refused), pagination defaults, then record fixtures.
- Contract diff (cheap, no credentials): check `docs/openapi/mobi-prd.json` and `mobi-sb.json` stay identical
  except `servers[0].url`.

## Notes

- The CESU, PAJE, auto-entrepreneur and Mobilité internationale APIs share the `/tiersdecl/v1` gateway (same token
  mechanism, similar envelope). A shared library (OAuth2 token provider, JSON API client, error envelope) may be
  considered later; it is **out of scope** for this package, which keeps its own copy like the other URSSAF packages.

## Order of work

1. Domain value objects (enrolment first, then the posting request) and the certificate result type.
2. Wire mappers (enrolments, then posting request, then certificate).
3. HTTP gateway (OAuth2, errors, 429 handling, binary response).
4. Application commands and queries.
5. Symfony bridge.
6. Contract tests, record fixtures, then remove the open points from `api-reference.md`.
7. Doctrine bridge, only if tracking is wanted.
