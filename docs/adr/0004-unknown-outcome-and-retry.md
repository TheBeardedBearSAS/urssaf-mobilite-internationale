# ADR 0004 — Unknown outcomes and retries

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md) row 13,
[ADR 0001](0001-stateless-sdk.md) and [ADR 0002](0002-http-client-adaptations.md). Precedents: CESU ADR 0003, PN
ADR 0003.

## Context

No operation has an idempotency key. `POST /declaration/detachement/UE` answers an array of `{id}` (the dossier number,
*inferred*, §5.12) and **no operation lists or searches postings**: after a lost answer the package cannot find out
whether the posting was recorded, and a blind resend may create a second dossier. `POST /enrolements` answers 400 when
an enrolment already exists for the company (PDF §4.1), and `GET /enrolements` lists them, so a lost enrolment answer
can be checked. The SDK is stateless: there is no local `outcome_unknown` record and no reconciliation job.

## Decision

- The package never retries nor resends by itself; it classifies.
- **Safe to retry** (`RetryableException`): the token call; `GET /enrolements`, `GET /enrolements/{id}`,
  `GET /certificat/{id}` (reads); 429 on every operation (the call was not processed); a token failure before any
  call.
- **`DELETE /enrolements/{id}`**: transport errors and 5xx are `UrssafUnavailable` with `mayHaveReachedUrssaf()` true.
  A repeat is harmless, but its answer after a successful first delete (404, 400 or 200) is not published: the
  documentation tells the caller to confirm with `GetEnrolment` (404 or `SUPPRIME`).
- **Never resent automatically: the two `POST`.** A transport error, any 5xx, or **any 2xx other than the declared
  success with a readable body** throws `Application\Port\Urssaf\OutcomeUnknown` (`final`, implements
  `UrssafException` but **not** `RetryableException`, as in PN; carries the operation and the status or transport
  class name). This covers an undeclared 2xx (a 200 on `POST /enrolements`, a 201 on the declaration), invalid JSON,
  and any field that does not match the contract: for the enrolment, a missing or invalid `idEnrolement`, a malformed
  date or `siren`; for the posting, not an array, an **empty array**, or an item without a valid UUID `id`. On these two
  operations a processed call can therefore never surface as `UnexpectedUrssafResponse`.
- The replay after a 401 applies to every operation, the two `POST` included: a 401 proves nothing was processed.
- 400, 404 and the declaration 403 are rejections (nothing recorded, not retryable as is); 1xx, 3xx and the 4xx not
  mapped in [ADR 0002](0002-http-client-adaptations.md) are `UnexpectedUrssafResponse` (not processed). Local validation failures are thrown before any call.
- After an `OutcomeUnknown`:
  - enrolment: the caller lists the enrolments and looks for the SIREN (and NIC); a resend would at worst be refused
    with 400 "already exists";
  - posting: the caller must not resend before checking with URSSAF outside the API (case manager, urssaf.fr). The
    exception message says so; it never contains the payload.
- A posting answer with several `id` items is kept whole: `PostingReceipt` holds a non-empty list of `DossierId`
  (cardinality unexplained, §5.12).

## Consequences

- The package never produces a duplicate posting by itself.
- Some postings need a manual check; this is documented in `docs/standalone.md` and `docs/symfony.md`.
