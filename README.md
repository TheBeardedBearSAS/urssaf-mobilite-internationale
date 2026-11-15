# URSSAF "API Mobilité internationale" for PHP

`thebeardedbear/urssaf-mobilite-internationale` — client, domain model and Symfony integration for the URSSAF API that
submits posting (*détachement*) requests within the European Economic Area directly, without filling in the
international mobility form, and that manages the enrolment of the company and the download of the mobility certificate.

Namespace: `TheBeardedBear\UrssafMobiliteInternationale`.

> [!IMPORTANT]
> **Unofficial package.** This project is an independent open-source implementation of a public API. It is not
> developed, endorsed, certified or supported by URSSAF, and it is not affiliated with URSSAF in any way. "URSSAF" is
> used only to name the API this package talks to. Using this package does not replace the URSSAF habilitation
> process; the URSSAF documentation remains the authority on the API, and you remain responsible for the data your
> application sends to URSSAF.

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
- `docs/reference/` — official URSSAF documentation: not versioned (URSSAF documents), download it from the links in [docs/api-reference.md](docs/api-reference.md) into this local, git-ignored folder.
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

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md), never in a public issue.

## License

MIT. See [LICENSE](LICENSE). The software is provided "as is", without warranty of any kind.

The API specification summarised in [docs/api-reference.md](docs/api-reference.md) belongs to URSSAF; see the sources
listed there.
