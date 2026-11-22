# URSSAF "API Mobilité internationale" for PHP

<!-- the-bearded-bear:badges:start -->
[![CI](https://github.com/TheBeardedBearSAS/urssaf-mobilite-internationale/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/TheBeardedBearSAS/urssaf-mobilite-internationale/actions/workflows/ci.yml?query=branch%3Amain) [![Coverage](https://codecov.io/gh/TheBeardedBearSAS/urssaf-mobilite-internationale/branch/main/graph/badge.svg)](https://app.codecov.io/gh/TheBeardedBearSAS/urssaf-mobilite-internationale) [![Mutation score](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FTheBeardedBearSAS%2Furssaf-mobilite-internationale%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/TheBeardedBearSAS/urssaf-mobilite-internationale/main) [![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-mobilite-internationale&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=TheBeardedBearSAS_urssaf-mobilite-internationale) [![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-mobilite-internationale&metric=sqale_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-mobilite-internationale) [![Reliability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-mobilite-internationale&metric=reliability_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-mobilite-internationale) [![Security](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-mobilite-internationale&metric=security_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-mobilite-internationale) [![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/TheBeardedBearSAS/urssaf-mobilite-internationale/badge)](https://scorecard.dev/viewer/?uri=github.com/TheBeardedBearSAS/urssaf-mobilite-internationale)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon.dist) [![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
<!-- the-bearded-bear:badges:end -->

`thebeardedbear/urssaf-mobilite-internationale` — client, domain model and Symfony integration for the URSSAF API that
submits posting (*détachement*) requests within the European Economic Area directly, without filling in the
international mobility form, and that manages the enrolment of the company and the download of the mobility certificate.

Namespace: `TheBeardedBear\UrssafMobiliteInternationale`.

Developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).

> [!IMPORTANT]
> **Unofficial package.** This project is an independent open-source implementation of a public API. It is not
> developed, endorsed, certified or supported by URSSAF, and it is not affiliated with URSSAF in any way. "URSSAF" is
> used only to name the API this package talks to. Using this package does not replace the URSSAF habilitation
> process; the URSSAF documentation remains the authority on the API, and you remain responsible for the data your
> application sends to URSSAF.

## Status

The six operations are implemented (domain model, OAuth2 HTTP gateway, application handlers, framework-free builder,
in-memory simulator, Symfony bundle with `urssaf:mi:*` commands and a Flex recipe) and covered by unit and functional
tests. The package is **not released yet**: no tag, not on Packagist, and the wire mapping has not been confirmed against
the real URSSAF sandbox, because the specification publishes no payload example (opt-in contract tests exist, see
[Sandbox contract tests](#sandbox-contract-tests)). The open points are listed in
[docs/api-reference.md](docs/api-reference.md) section 5 and the next steps in [ROADMAP.md](ROADMAP.md). Expect changes
before `1.0.0`.

The package is stateless ([ADR 0001](docs/adr/0001-stateless-sdk.md)): it stores nothing (no database, no Doctrine, no
Messenger), keeps only the OAuth2 access token in a cache you provide, and never persists nor logs a posting request.
You must store the identifiers it returns (enrolment id, dossier id), because the API lists no posting.

## Scope

| Operation | Endpoint |
|---|---|
| Request an enrolment | `POST /enrolements` |
| List enrolments | `GET /enrolements` |
| Get one enrolment | `GET /enrolements/{id}` |
| Delete an enrolment | `DELETE /enrolements/{id}` |
| Submit a posting request (EU) | `POST /declaration/detachement/UE` |
| Download the mobility certificate | `GET /certificat/{id}` |

OAuth2 client credentials, scope `ods.dlagd`, base URL `https://api.urssaf.fr/tiersdecl/v1/mobilite` (sandbox:
`https://api-edi.urssaf.fr/tiersdecl/v1/mobilite`).

## Installation

```sh
composer require thebeardedbear/urssaf-mobilite-internationale
```

The package requires PHP 8.2+ and PSR interfaces only (`psr/http-client`, `psr/http-factory`, `psr/http-message`,
`psr/cache`, `psr/simple-cache`, `psr/clock`, `psr/log`). Bring your own PSR-18 client and PSR-17 factories, for instance
`composer require symfony/http-client nyholm/psr7`. Until the first release is published, install it from the Git
repository (Composer `vcs` repository).

## Quick start (standalone, without Symfony)

You need an OAuth2 client id and secret from the URSSAF API portal (restricted offer, see
[docs/api-reference.md](docs/api-reference.md) section 1). Every call is made for a tenant, the identifier of one set of
credentials in your application ([ADR 0007](docs/adr/0007-tenant-model.md)).

```php
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\RequestEnrolment;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Command\SubmitPosting;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Urssaf\CertificateAvailable;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Query\GetCertificate;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\EnrolmentCategory;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Enrolment\Siren;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingRequest;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Posting\PostingValidationPolicy;
use TheBeardedBear\UrssafMobiliteInternationale\Domain\Shared\TenantId;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Standalone\UrssafMobiliteInternationaleBuilder;

$tenant = TenantId::fromString('tenant-1');

$package = UrssafMobiliteInternationaleBuilder::create(
    new HttpTransport($psr18Client, $psr17RequestFactory, $psr17StreamFactory),
    new InMemoryCredentialsProvider(['tenant-1' => Credentials::of($clientId, $clientSecret)]),
    Environment::Sandbox,
)
    ->withTokenStore($sharedTokenStore) // optional: default is in memory, per process
    ->build();

// 1. Request the enrolment of a company (SIREN: use your own). A user of that company then validates it on urssaf.fr.
$enrolment = $package->requestEnrolment()(
    new RequestEnrolment($tenant, Siren::fromString($siren), null, EnrolmentCategory::Rg),
);
// Store $enrolment->id() and poll $package->getEnrolment() until $enrolment->isAccepted().

// 2. Declare a posting. $draft is a PostingRequestDraft of raw values; every violation is reported at once.
$request = PostingRequest::fromDraft($draft, PostingValidationPolicy::default());
$receipt = $package->submitPosting()(SubmitPosting::of($tenant, $request));
// Store $receipt->dossierIds() IMMEDIATELY: no operation lists the postings.

// 3. Download the certificate. It may not be ready yet.
$result = $package->getCertificate()(new GetCertificate($tenant, $receipt->first()));
if ($result instanceof CertificateAvailable) {
    $result->file()->stream()->copyTo($resource); // streamed, never buffered in memory
    $result->file()->stream()->close();
}
// CertificateInProgress: try again later. CertificateRefusedOrUnknown: do not poll.
```

The package never retries and never resends a posting: after an `OutcomeUnknown`, check with URSSAF before sending the
posting again. Everything you must know is in [docs/standalone.md](docs/standalone.md) (builder options, the optional
enrolment guard, failures and retries). `PostingRequestDraft` and its nested drafts are in
`src/Domain/Posting/Input/`; `tests/Unit/Domain/Posting/Input/PostingDrafts.php` builds a complete one from synthetic
values.

## Quick start (Symfony)

Symfony 6.4, 7.x and 8.x. With Symfony Flex the bundle is registered and `.env` receives the variables below (until the
recipe is published in `symfony/recipes-contrib`, see [docs/symfony.md](docs/symfony.md) to register the bundle by hand
or to serve the recipe from your own endpoint):

```dotenv
URSSAF_MI_ENV=sandbox            # sandbox | production
URSSAF_MI_CLIENT_ID=
URSSAF_MI_CLIENT_SECRET=         # keep it in the secrets vault or .env.local, never in git
URSSAF_MI_BASE_URI=              # optional override, https only
URSSAF_MI_TOKEN_URI=             # optional override, https only (the sandbox token host is not published)
```

```yaml
# config/packages/urssaf_mobilite_internationale.yaml
urssaf_mobilite_internationale:
    environment: '%env(URSSAF_MI_ENV)%'
    base_uri: '%env(default::URSSAF_MI_BASE_URI)%'
    token_uri: '%env(default::URSSAF_MI_TOKEN_URI)%'
    credentials:
        default:
            client_id: '%env(URSSAF_MI_CLIENT_ID)%'
            client_secret: '%env(URSSAF_MI_CLIENT_SECRET)%'

when@test:
    urssaf_mobilite_internationale:
        gateway: { simulated: true }
```

```sh
php bin/console urssaf:mi:check                                      # installation diagnostics (deployment step)
php bin/console urssaf:mi:enrolment:request 123456789 --category=RG  # fictitious SIREN: use your own
php bin/console urssaf:mi:enrolment:list --limit=20
php bin/console urssaf:mi:enrolment:show <enrolment-id>
php bin/console urssaf:mi:enrolment:delete <enrolment-id>
php bin/console urssaf:mi:certificate <dossier-id> certificate.pdf   # exit code 3: not available yet
```

The use case handlers (`SubmitPostingHandler`, `GetCertificateHandler`, ...) are public services to inject. There is no
posting command: a NIR must not end in a shell history, so the application builds the `PostingRequest` and calls
`SubmitPostingHandler`. Details, configuration reference, token cache pool and exit codes: [docs/symfony.md](docs/symfony.md).
In tests, `gateway.simulated: true` (refused with `production`) replaces the API with an in-memory simulator
([docs/testing.md](docs/testing.md)).

## Documentation

- [docs/api-reference.md](docs/api-reference.md) — technical reference (single source of truth for the package).
- [docs/standalone.md](docs/standalone.md), [docs/symfony.md](docs/symfony.md), [docs/testing.md](docs/testing.md) — use without
  Symfony, the bundle, and testing a host application.
- [docs/adr/](docs/adr/) — architecture decision records; [docs/security/assurance-case.md](docs/security/assurance-case.md) —
  security assurance case.
- [docs/roadmap.md](docs/roadmap.md) — architecture of the implementation and the order it was built in.
- [docs/openapi/](docs/openapi/) — official OpenAPI contract(s) from the URSSAF API portal (production and sandbox).
- `docs/reference/` — official URSSAF documentation: not versioned (URSSAF documents), download it from the links in [docs/api-reference.md](docs/api-reference.md) into this local, git-ignored folder.
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/mobi

## Requirements

PHP 8.2+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, hadolint, guard, coverage, infection
make test        # unit + functional tests
make coverage-branches  # branch coverage (Xdebug, slow), at least 96 %
make bench       # benchmarks (PHPBench, tests/Benchmark)
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

Once the repository is public, `main` publishes its quality proofs, linked from the badges at the top of this file: the
coverage report on Codecov, the full mutation report on the Stryker Dashboard, the SonarQube Cloud quality gate and the
OpenSSF Scorecard. The benchmarks (`tests/Benchmark`, PHPBench in `tools/phpbench`, out of the package dependencies)
run on every pull request against the base commit, and the job fails on a regression of more than 20 %.

### Sandbox contract tests

The tests of `tests/Contract` (suite and group `contract`) call the real URSSAF sandbox. They are opt-in: outside the
default run, outside the coverage and mutation gates, and skipped while `URSSAF_SANDBOX_CLIENT_ID` and
`URSSAF_SANDBOX_CLIENT_SECRET` are not set. No real identifier and no recorded response is committed.

```sh
export URSSAF_SANDBOX_CLIENT_ID=... URSSAF_SANDBOX_CLIENT_SECRET=...
make test-contract
```

| Variable | Required | Purpose |
|---|---|---|
| `URSSAF_SANDBOX_CLIENT_ID`, `URSSAF_SANDBOX_CLIENT_SECRET` | yes | OAuth2 client credentials of the sandbox; without them the whole suite is skipped |
| `URSSAF_SANDBOX_BASE_URI` | no | replaces the API base URI (path included, `https` only) |
| `URSSAF_SANDBOX_TOKEN_URI` | no | replaces the OAuth2 token URI: the sandbox token host is not published |
| `URSSAF_SANDBOX_ENROLMENT_SIREN` | no | SIREN to enrol: without it the enrolment life-cycle (request, list, get, delete) is skipped |
| `URSSAF_SANDBOX_ENROLMENT_NIC`, `URSSAF_SANDBOX_ENROLMENT_CATEGORY` | no | NIC (5 characters) and category of that enrolment (default `RG`) |
| `URSSAF_SANDBOX_POSTING_SIREN`, `URSSAF_SANDBOX_POSTING_NIC` | no | company whose enrolment is validated: without both, **no posting is sent** (the test is skipped) |
| `URSSAF_SANDBOX_POSTING_EXTERNAL_ACCOUNT` | no | external account number (18 digits) of that company |
| `URSSAF_SANDBOX_DOSSIER_ID` | no | dossier number (UUID): without it the certificate test of an existing dossier is skipped |

The enrolment and the posting are real calls on your sandbox (the enrolment is deleted at the end of its test); the
people, contacts and addresses of the posting are the synthetic values of the unit tests. In the CI, the manual job
`contract` (Actions > CI > Run workflow, `contract` checked) reads the same variables from the `urssaf-sandbox`
environment (secrets for the credentials and the identifiers, repository variables for the URIs and the category).

## Versioning

[Semantic Versioning](https://semver.org/). No version is released yet: until `1.0.0`, a minor release (`0.x`) may
contain breaking changes, announced in the [changelog](CHANGELOG.md). `SpecVersion::CURRENT` holds the version of the
official URSSAF documentation implemented (`1.0.0`, the published PDF; the OpenAPI contract is 1.0.8, see the reference).

## Living next to the other URSSAF SDKs

The CESU, PAJE, auto-entrepreneur and this package share the `/tiersdecl/v1` gateway of URSSAF and can be installed in the
same Symfony application: nothing is shared between them. Here everything carries `mi`: console namespace `urssaf:mi:`,
variables `URSSAF_MI_*`, service ids and token cache keys `urssaf_mi.`, Monolog channel `urssaf_mi`, bundle alias
`urssaf_mobilite_internationale`. Give each package its own credentials and its own token cache pool (table and rules in
[docs/symfony.md](docs/symfony.md#living-next-to-the-other-urssaf-packages)).

<!-- the-bearded-bear:start -->
## Other URSSAF packages

The same architecture, quality gate and conventions, one package per URSSAF API (overview: [the-bearded-bear.com/open-source/urssaf](https://the-bearded-bear.com/open-source/urssaf)):

| API | Package | Status |
|---|---|---|
| Tiers de prestations (immediate tax-credit advance) | [`thebeardedbear/urssaf-tiers-prestations`](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations) | Released (Packagist) |
| Attestations Vérification Code Sécurité (certificate verification) | [`thebeardedbear/urssaf-attestations-verification-code-securite`](https://github.com/TheBeardedBearSAS/urssaf-attestations-verification-code-securite) | Released (Packagist) |
| DPAE (pre-employment declaration) | [`thebeardedbear/urssaf-dpae`](https://github.com/TheBeardedBearSAS/urssaf-dpae) | Coming soon |
| Plateforme numérique (withholding of contributions by digital platforms) | [`thebeardedbear/urssaf-plateforme-numerique`](https://github.com/TheBeardedBearSAS/urssaf-plateforme-numerique) | Released (Packagist) |
| Tierce Déclaration auto-entrepreneur | [`thebeardedbear/urssaf-tierce-declaration-auto-entrepreneur`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-auto-entrepreneur) | Released (Packagist) |
| Tierce Déclaration CESU | [`thebeardedbear/urssaf-tierce-declaration-cesu`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu) | Released (Packagist) |
| Tierce Déclaration PAJE | [`thebeardedbear/urssaf-tierce-declaration-paje`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje) | Released (Packagist) |

## Professional support

This package is developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).
If you need help with an URSSAF integration, The Bearded Bear can help with:

- the URSSAF habilitation and homologation process (subscription, sandbox tests, move to production);
- integrating the package into your application (Symfony, Doctrine or framework-free);
- support with a guaranteed response time, including the follow-up of URSSAF API changes;
- custom developments on top of the package.

Project page and integration offer: [the-bearded-bear.com/open-source/urssaf-mobilite-internationale](https://the-bearded-bear.com/open-source/urssaf-mobilite-internationale).
<!-- the-bearded-bear:end -->

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md), never in a public issue.

## License

MIT. See [LICENSE](LICENSE). The software is provided "as is", without warranty of any kind.

The API specification summarised in [docs/api-reference.md](docs/api-reference.md) belongs to URSSAF; see the sources
listed there.
