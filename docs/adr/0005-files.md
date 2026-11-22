# ADR 0005 — Certificate files: streamed, never logged

Status: Accepted (2026-11-22). Relates to [ADR 0002](0002-http-client-adaptations.md),
[ADR 0003](0003-certificate-result.md) and [ADR 0006](0006-personal-data.md). Precedent: PN ADR 0005.

## Context

`GET /certificat/{id}` returns `application/pdf` (`format: binary`); its size, file name and `Content-Disposition` are
not published (api-reference §4.13, §5.21). The certificate names the posted worker: it is personal data. The
Application layer may not depend on PSR-7.

## Decision

- Port `Application\Port\Urssaf\BinaryStream`: `read(int $length): string`, `eof(): bool`,
  `copyTo(resource $target): int`, `close(): void`. Infrastructure adapter `Urssaf\Http\Psr7BinaryStream` wraps the
  PSR-7 body; Testing has `SimulatedBinaryStream`.
- `Application\Port\Urssaf\DownloadedFile`: `stream(): BinaryStream`, `contentType(): ?string`,
  `contentDisposition(): ?string` (raw headers when present). The content type is exposed, not enforced: the contract
  says `application/pdf`, the real value is checked by the contract tests.
- The body is never buffered whole (`getContents()` / `__toString()` are not used), never decoded, parsed, stored,
  logged nor put in an exception. `DownloadedFile` and the stream have a `__debugInfo()` that hides the content.
- The console command writes the stream to the given path with `copyTo()`, creates the file with mode `0600`, and
  refuses an existing file unless `--force`.

## Consequences

- Memory stays bounded whatever the certificate size.
- The host decides where certificates are kept and for how long; the package keeps nothing.
