# Roadmap

Intended direction for the next twelve months (November 2026 to November 2027). It is a statement of intent, not a
commitment; it is reviewed at each minor release. Proposals are welcome in GitHub issues. The implementation plan of
the first release is in [docs/roadmap.md](docs/roadmap.md).

## Planned

- **First release (0.1).** The six operations of the URSSAF *API Mobilité internationale* (enrolment request, list,
  detail and deletion, posting request within the EEA, mobility certificate download) are implemented: domain model,
  HTTP gateway, application handlers, framework-free builder, simulator and Symfony bundle. What remains before the tag
  is the sandbox confirmation below, the publication of the Flex recipe in `symfony/recipes-contrib` (with the agreement
  of the maintainer) and the release notes. The architecture is described in [docs/roadmap.md](docs/roadmap.md).
- **Run the contract tests against the real sandbox.** Execute the opt-in contract tests against the URSSAF sandbox,
  settle the open points of the [API reference](docs/api-reference.md) (section 5) as URSSAF confirms them (sandbox
  token host, enrolment life-cycle, certificate status codes), record fixtures stripped of personal data, and adjust
  the affected ADRs.
- **Follow the URSSAF API.** Track each new version of the URSSAF *API Mobilité internationale* (OpenAPI contract and
  technical documentation) and release the matching changes, with upgrade notes in the [changelog](CHANGELOG.md).
- **Release 1.0.** Stabilise the public API (domain model, gateway, handlers, Symfony bundle), then release `1.0.0`.
  From 1.0 on, breaking changes only happen in major versions, as required by [Semantic Versioning](https://semver.org/).
- **Keep up with the platform.** Support new PHP versions (8.5 and later) and the new Symfony versions as they are
  released, and drop the versions that reach their end of life in a minor release, announced in the changelog.
- **Keep the quality and security bar.** 100 % coverage and 100 % MSI, branch coverage of at least 96 %, PHPStan at
  level max, signed releases, and the OpenSSF Best Practices criteria.

## Not planned

- Other URSSAF APIs: each one gets its own package (see *Other URSSAF packages* in the [README](README.md)).
- Persisting postings, enrolments or personal data (NIR, names, addresses): the package is stateless
  ([ADR 0001](docs/adr/0001-stateless-sdk.md), [ADR 0006](docs/adr/0006-personal-data.md)) and has no Doctrine bridge;
  bringing one back needs a new ADR ([ADR 0000](docs/adr/0000-reference-architecture.md) row 15).
- Official bridges for other frameworks than Symfony: the core works without a framework.
