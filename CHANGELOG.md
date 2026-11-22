# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-22

First release.

### Added

- Project skeleton: Docker tooling, quality gates (`make ci`) and GitHub Actions CI, identical to the other URSSAF
  packages.
- Specification: official OpenAPI contracts (`docs/openapi/`), official documentation (links in `docs/api-reference.md`, local copy in the git-ignored `docs/reference/`), technical
  reference (`docs/api-reference.md`) and implementation roadmap (`docs/roadmap.md`).
- Signed releases (workflow `release.yml`): each GitHub release carries the distribution archive, its `SHA256SUMS`
  and a SLSA build provenance attestation (`*.intoto.jsonl`, Sigstore); see *Verifying a release* in SECURITY.md.
- CodeQL analysis of the GitHub Actions workflows (workflow `codeql.yml`) on every pull request and push to `main`.

- Domain model (`src/Domain`): enrolment (`Enrolment`, `EnrolmentId`, `Siren`, `Nic`, `EnrolmentPage`, `EnrolmentCategory`
  and `EnrolmentStatus` keeping the raw wire value next to a nullable enum view), posting request (`PostingRequest` and
  its value objects, closed lists, `PostingReceipt`, `DossierId`), `TenantId`, and cumulative validation of the
  specification rules only (raw `*Draft` inputs read into a `PostingRequest`, every violation reported at once as
  `ValidationFailed`; opt-in `PostingValidationPolicy::withPdfTeleworkRules()`). Personal data is never serialised and
  masked in debug output (ADR 0006, 0008, 0011).
- Application layer: `MobilityGateway` port and the six handlers (`RequestEnrolment`, `ListEnrolments`, `GetEnrolment`,
  `DeleteEnrolment`, `SubmitPosting`, `GetCertificate`), `CertificateResult` (`CertificateAvailable` /
  `CertificateInProgress` / `CertificateRefusedOrUnknown`, ADR 0003), the optional enrolment guard
  `SubmitPosting::afterCheckingEnrolment()` (ADR 0009), typed failures including `OutcomeUnknown` for the two `POST`
  operations (ADR 0004), and a certificate streamed and never buffered (ADR 0005).
- OAuth2 client-credentials HTTP gateway over PSR-18 / PSR-17 (scope `ods.dlagd`, overridable `https` base and token URIs,
  one replay after a 401, no other retry, binary PDF and empty 200 / 204 bodies, errors as object, list or empty),
  token stores (in memory, PSR-6, PSR-16) and `InMemoryCredentialsProvider`; opt-in sliding-window rate limiter
  (200 requests per minute, per tenant and operation); logs carry method, path, status and duration only (ADR 0002, 0007).
- Framework-free `Standalone\UrssafMobiliteInternationaleBuilder` and `UrssafMobiliteInternationale` facade, and the
  in-memory `Testing\SimulatedMobilityGateway` (refused for the production environment).
- Opt-in sandbox contract tests (`make test-contract`, `tests/Contract`, manual CI job `contract`) and PHPBench
  benchmarks (`make bench`, `tests/Benchmark`).
- README: status, installation, standalone and Symfony quick starts, versioning and coexistence with the other URSSAF
  packages; implemented state of `docs/api-reference.md`, `docs/roadmap.md`, `docs/standalone.md`, `docs/symfony.md`,
  `docs/testing.md` and of the security assurance case.
- Symfony bundle (`UrssafMobiliteInternationaleBundle`, alias `urssaf_mobilite_internationale`, Symfony 6.4, 7.x and 8.x,
  stateless: no Messenger, Lock, Scheduler nor Doctrine): configuration tree (environment, overridable `https` base and
  token URIs, environment-variable credentials, token cache pool, simulated gateway refused in production, certificate
  400 handling, PDF telework rules, opt-in rate limiter), `urssaf:mi:*` console commands (`check`, `enrolment:request`,
  `enrolment:list`, `enrolment:show`, `enrolment:delete`, `certificate`), installation diagnostics, English / French
  translations of the violation codes, Flex recipe (`URSSAF_MI_*` variables) and `bin/build-flex-endpoint`
  (`docs/symfony.md`, ADR 0010). Symfony 8 joins the CI matrix on PHP 8.4.

### Changed

- The Doctrine, Lock and Messenger development dependencies are gone (the package is stateless); the Deptrac
  `DoctrineBridge` layer stays (ADR 0000 row 15).
- Open-source silver level, aligned on the reference package: `GOVERNANCE.md`, `CODE_OF_CONDUCT.md`, `ROADMAP.md`,
  security assurance case (`docs/security/assurance-case.md`), DCO sign-off (`bin/check-dco`, CI job `dco`),
  documented test policy and code review in `CONTRIBUTING.md`, SPDX licence headers on every source file
  (`bin/check-spdx`, `make guard`; PHP files through PHP CS Fixer `header_comment`), branch coverage of at least 96 %
  (`make coverage-branches`, CI job `coverage-branches` on main, nightly and on demand), Deptrac layers `Standalone`
  and `Testing`, and PHPStan always run on PHP 8.2 (ADR 0012).
- Infection: `timeout` raised from 30 s to 180 s, and `make infection` and the CI job fail when a mutant is skipped (Infection never runs a mutant whose covering tests exceed the limit, yet reports MSI 100 %).
- Infection: a timed-out mutant counts as escaped (`timeoutsAsEscaped`), and `make infection` and the CI job fail on any timed-out or syntax-error mutant: every mutant really runs and is killed.
- CI: verifiable quality proofs (Codecov, Stryker Dashboard, SonarQube Cloud, OpenSSF Scorecard; active once the
  repository is public), every GitHub Action pinned to a commit SHA and kept up to date by Dependabot, hadolint on the
  Dockerfile (`make hadolint`, part of `make ci`) with a non-root development image, and benchmarks with PHPBench
  (`make bench`, CI job `benchmark`, isolated in `tools/phpbench`).
- CI: the SonarQube Cloud analysis also requires the repository variable `SONARCLOUD=enabled`, set once the
  project is imported on SonarQube Cloud.

- README: maintainer (The Bearded Bear), the other URSSAF packages and professional support; composer
  `authors` now points to https://the-bearded-bear.com.

- The package is open source under the MIT license (previously proprietary). Added `CONTRIBUTING.md` and
  `SECURITY.md`, and a notice in the README stating that the package is unofficial and not affiliated with URSSAF.
