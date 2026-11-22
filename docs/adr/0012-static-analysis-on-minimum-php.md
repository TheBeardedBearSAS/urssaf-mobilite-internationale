# ADR 0012 — Static analysis on the minimum supported PHP version

Status: Accepted (2026-11-22). Departure from the reference Makefile; same decision as
urssaf-attestations-verification-code-securite ADR 0007 and urssaf-plateforme-numerique ADR 0013.

## Context

The package supports PHP 8.2 and later (`composer.json`). PHPStan includes `tomasvotruba/type-coverage` with
`constant: 100` (`phpstan.neon.dist`). The rule reads the PHP version running PHPStan: on 8.3+ it requires typed class
constants (`public const string X`), a parse error on 8.2. The skeleton already has four untyped constants
(`SpecVersion::CURRENT`, `UrssafEndpoints::TOKEN_PATH`, `TOKEN_SCOPE`, `API_BASE_PATH`), and its Makefile runs PHPStan
on `PHP_VERSION`, so `make ci PHP_VERSION=8.4` is expected to report them (not run yet; the siblings observed the same
failure on their untouched skeletons) although the code is correct. The CI `quality` jobs already
run on PHP 8.2.

## Decision

- The Makefile `phpstan` target always runs in a PHP 8.2 image (`PHP_ANALYSIS`), whatever `PHP_VERSION` is (lot 0).
- Every other step of `make ci PHP_VERSION=x` runs on the requested version.
- No PHPStan threshold or rule is changed.

## Consequences

- `make ci PHP_VERSION=8.4` checks runtime behaviour on 8.4 and static analysis on the supported floor.
- When PHP 8.2 support is dropped, constants get native types and the pin is removed.
