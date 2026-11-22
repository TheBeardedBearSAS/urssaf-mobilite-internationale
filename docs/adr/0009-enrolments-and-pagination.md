# ADR 0009 — Enrolments: pagination, raw values and the optional guard

Status: Accepted (2026-11-22). Relates to [ADR 0002](0002-http-client-adaptations.md) and
[ADR 0011](0011-country-and-closed-lists.md). Spec: api-reference §2.1–§2.4, §3.1, §3.2, §4.4–§4.6, §4.8, §5.4, §5.20.

## Context

`GET /enrolements` takes `offset`, `limit` and an optional `sort` (syntax not published) as flat query parameters and
returns a bare array: no total, no ordering, no default nor maximum `limit`, behaviour without parameters unknown. No
field of `Enrolement` is required. The meaning of `status` is inferred (§3.2), two `categorie` values have no label,
and the PDF says a declaration needs an accepted enrolment (403 otherwise).

## Decision

- `ListEnrolments(TenantId, int $offset, int $limit, ?string $sort)`: `offset` ≥ 0 and `limit` ≥ 1 are both required
  (the package never calls without them); no maximum is enforced; `sort` is sent verbatim, non-empty, no syntax check.
- Result `EnrolmentPage`: `enrolments(): list<Enrolment>`, `offset()`, `limit()`, `isLastPage()` (fewer items than
  `limit`), `nextOffset(): ?int`. Iterating stops on a short page (§4.6); no total is invented.
- `Enrolment`: `id` (`EnrolmentId`, UUID, required on read), `siren`, `nic`, `category`, `status`, `requestedAt`,
  `updatedAt`, each nullable on read. `category` and `status` keep the raw string next to a nullable enum view
  (`EnrolmentCategory::fromCode(): ?self`, `EnrolmentStatus::fromCode(): ?self`): an unknown value never breaks a read.
  A present but malformed value (bad UUID, bad date) is an `UnexpectedUrssafResponse` on the reads and an
  `OutcomeUnknown` on the creation answer ([ADR 0004](0004-unknown-outcome-and-retry.md)). Dates are parsed as ISO 8601
  with offset, tolerant of `Z`, `+01:00`, `+0100` and fractional seconds (§4.8).
- `RequestEnrolment(TenantId, Siren, ?Nic, EnrolmentCategory)` → `Enrolment` (201). `DeleteEnrolment(TenantId,
  EnrolmentId)` → void (200, body ignored). `GetEnrolment(TenantId, EnrolmentId)` → `Enrolment`.
- **Optional guard** (§4.5, off by default): `SubmitPosting::afterCheckingEnrolment(TenantId, PostingRequest,
  EnrolmentId)` reads the enrolment first and throws `EnrolmentNotAccepted` (`InvariantViolation`,
  `enrolment.not_accepted`) unless its status is `ACCEPTE` and its SIREN equals `referenceEntreprise.siren`.
  `SubmitPosting::of(TenantId, PostingRequest)` does not check. The NIC is not compared (the link between an
  enrolment without NIC and the establishments is not published).

## Consequences

- The caller chooses its page size; the console command defaults to `--offset=0 --limit=20`, a package choice (§5.20)
  documented as such, not a server rule.
- When URSSAF publishes the defaults, the maximum or the `sort` syntax, they become validation rules of
  `ListEnrolments`.
