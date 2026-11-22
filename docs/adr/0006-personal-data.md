# ADR 0006 — Personal data

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md) row 10 and
[ADR 0005](0005-files.md). Spec: api-reference §4.12.

## Context

The posting request carries the posted person (`nir`, `cleNir`, `nomUsage`, `nomNaissance`, `prenom`,
`dateNaissance`, `lieuNaissance`, `paysNaissance`, `nationalite`, `adresse`, `email`, `tel1`, `tel2`), the host
address, the telework country, the declarant contact (`email`, `tel1`, `tel2`) and a free `commentaire`. For a
self-employed worker the SIREN / NIC and the company name identify a natural person too. The certificate names the
worker. URSSAF error descriptions may echo submitted values. The repository is public.

## Decision

- **Never logged**: request and response bodies, query strings, error bodies, the certificate content. Logs carry
  method, path (UUIDs only), status, duration and request id.
- **Never in an exception message**: violation messages are English, generic and carry the field path and the
  violation code, never the rejected value (`params()` holds limits such as `max`, not inputs). `UrssafRequestRejected`
  keeps the URSSAF `ApiErrors` for the caller but its message is built from the status and operation only.
- **Masked in `__debugInfo()`**: `Worker`, `Declarant`, `Address`, `HostAddress`, `Telework`, `PostingRequest`, the
  phone and email value objects, `commentaire` and `Credentials`.
- **SIREN / NIC**: never logged nor put in an exception message, but not masked: they are public registry identifiers
  of the enrolled company, the enrolment commands take the SIREN as an argument and print the SIREN / NIC of the
  operator's own enrolments. The NIR, names, birth date and addresses of a posting never reach the command line.
- **Console output**: commands print the HTTP status and package messages. The URSSAF error fields (`code`, `message`,
  `description`, whose semantics are not published, api-reference §1.3) are printed only with the explicit
  `--show-urssaf-errors` option, never a payload. There is no posting command ([ADR 0010](0010-symfony-bridge.md)).
- **Fixtures and tests**: no real NIR, SIREN, SIRET, name, address, email or phone, not even the example values of the
  Swagger when they may be real. Test data is visibly synthetic (`000000000`, `test@example.invalid`, …) and built in
  the tests; recorded sandbox payloads are reviewed and stripped before any commit. Gitleaks runs on the history.
- **Persistence**: the package stores nothing ([ADR 0001](0001-stateless-sdk.md)).

## Consequences

- Debugging a rejected posting relies on the URSSAF `code` / `message` and on the caller's own data, not on package
  logs.
- A test asserts that no log record and no exception message of the gateway tests contains a value of the synthetic
  request.
