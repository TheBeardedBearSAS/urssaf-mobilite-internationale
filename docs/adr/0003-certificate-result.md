# ADR 0003 — Certificate result

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md) row 5,
[ADR 0002](0002-http-client-adaptations.md) and [ADR 0005](0005-files.md). Spec: api-reference §2.6, §4.3, §5.5.

## Context

`GET /certificat/{id}` answers 200 with the PDF, and the two sources disagree on the other statuses: the Swagger says
204 = "dossier exists and is being instructed" and 404 = "resource does not exist"; the PDF says 400 = "no downloadable
certificate yet, call again later" and 404 = "the file was refused". No body is specified for these cases. Callers
poll this operation, so "not yet" must not look like an error, and "refused" must not look like "retry later".

## Decision

- Sum type in `Application\Port\Urssaf` (not in `Domain`: its variants carry a `DownloadedFile` and `ApiErrors`, which
  are port types, and Deptrac forbids Domain → Application; departure from the roadmap, which named
  `Domain\Certificate`):
  - `interface CertificateResult { public function httpStatus(): int; }`
  - `final readonly class CertificateAvailable` — `file(): DownloadedFile` (200);
  - `final readonly class CertificateInProgress` — `errors(): ApiErrors` (204, and 400 by default, below);
  - `final readonly class CertificateRefusedOrUnknown` — `errors(): ApiErrors` (404). The name keeps both readings:
    the package cannot tell a refused dossier from an unknown id.
- Every variant keeps the raw HTTP status, and the non-available ones the raw parsed errors (empty for 204).
- **400 is configurable** (`GatewayOptions::withCertificateBadRequestAsInProgress(bool)`, bundle
  `certificate.bad_request_as_in_progress`). Default `true`: a **package choice**, not a spec rule (api-reference §4.3
  makes it conditional on a sandbox confirmation). Reasons: the PDF is the source of the functional meaning (§4.1) and
  the only input, the UUID, is validated locally, so a 400 is unlikely to come from a malformed request. With `false`,
  a 400 is an `UrssafRequestRejected`. Noted in §5.5 as not settled by the published sources: the default stays `true`
  until the sandbox shows the status of a 400, and any change of default comes with a CHANGELOG entry.
- 401 (replay), 429, 5xx and other statuses follow [ADR 0002](0002-http-client-adaptations.md); the operation is a
  read, safe to retry.
- `GetCertificate(TenantId, DossierId)` → `CertificateResult`. `DossierId` is a UUID value object, the `id` of the
  posting receipt.

## Consequences

- A poller matches on three variants; an `UrssafException` means a real failure, never "not ready yet".
- If the sandbox shows a status body for in-progress or refused dossiers, a typed view is added to the variants
  without changing the sum type.
