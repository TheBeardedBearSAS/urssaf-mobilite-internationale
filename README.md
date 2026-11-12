# URSSAF "API Mobilité internationale" for PHP

`thebeardedbear/urssaf-mobilite-internationale` — client, domain model and Symfony integration for the URSSAF API that
submits posting (*détachement*) requests within the European Economic Area directly, without filling in the
international mobility form, and that manages the enrolment of the company and the download of the mobility certificate.

Namespace: `TheBeardedBear\UrssafMobiliteInternationale`.

> 🚧 **Skeleton — not implemented yet.** This repository currently contains the tooling and the specification only.
> The implementation follows [docs/roadmap.md](docs/roadmap.md).

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

## Documentation

- [docs/api-reference.md](docs/api-reference.md) — technical reference (single source of truth for the package).
- [docs/roadmap.md](docs/roadmap.md) — planned architecture and order of work.
- [docs/openapi/](docs/openapi/) — official OpenAPI contract(s) from the URSSAF API portal (production and sandbox).
- [docs/reference/](docs/reference/) — official URSSAF documentation.
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/mobi

## Requirements

PHP 8.2+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, guard, coverage, infection
make test        # unit + functional tests
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

## Versioning

Semantic Versioning. `SpecVersion::CURRENT` holds the version of the official URSSAF documentation implemented
(`1.0.0`, the published PDF; the OpenAPI contract is 1.0.8, see the reference).

## License

Proprietary — see [LICENSE](LICENSE).
