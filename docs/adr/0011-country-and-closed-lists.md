# ADR 0011 — `Country` and the other closed lists

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md) row 5,
[ADR 0008](0008-local-validation.md) and [ADR 0009](0009-enrolments-and-pagination.md). Spec: api-reference §3.3–§3.6,
§4.7, §4.9, §5.15.

## Context

The `Pays` schema has 226 distinct values: the empty string first, then upper-case French names without accents,
including aliases (`REPUBLIQUE DE COREE` / `COREE DU SUD`, …). No ISO mapping is published. `Pays`, `Nature`,
`CritereDActivite`, `type` and the `NC` / `OUI` / `NON` answer appear **only in the request**; `categorie` and `status`
appear in requests and responses.

## Decision

- `Domain\Posting\Country` is a backed `enum Country: string` with **225 cases**, the 226 values minus `""`, in
  contract order, aliases kept as separate cases (no merging, no ISO code). Case names are derived mechanically from
  the wire value: split on spaces, hyphens, apostrophes and underscores, capitalise each word (first letter upper, rest lower),
  concatenate (`PAYS-BAS` → `PaysBas`, `COTE D'IVOIRE` → `CoteDIvoire`, `REPUBLIQUE DE COREE` → `RepubliqueDeCoree`);
  the derived names are all distinct.
- `Country::fromWire(string): self` throws `InvalidValue` `country.unknown` for `""` or any other value (the message
  never repeats it). Being request-only, `Country` has no raw fallback.
- A unit test reads `docs/openapi/mobi-prd.json` and asserts that the cases equal `Pays` minus `""`, in order, so a
  contract update breaks the build.
- Other request-only enums, same convention, wire values verbatim: `Answer` (`NotApplicable` = `NC`, `Yes` = `OUI`,
  `No` = `NON`; never mapped to a boolean), `ActivityNature` (14 values of `Nature`, `SALARIE_INTERMITTENT` →
  `SalarieIntermittent`), `ActivityCriterion` (`CA`, `TempsTravail`, `Prestations`, `Remuneration`: the only exception
  to the derivation rule, case names = the mixed-case values, `Ca` would hide the acronym), `ActivityType` (`Autre` = `AUTRE`).
- Response enums keep the raw value next to a nullable view ([ADR 0009](0009-enrolments-and-pagination.md)):
  `EnrolmentCategory` (10 values, `Aa` … `Autre`) and `EnrolmentStatus` (`Accepte`, `Encours`, `Refuse`, `Supprime`).
- The unreferenced schemas `Etat`, `Fonction`, `EnteteDemande`, `DetachementSalarie` (§3.7) are not modelled.

## Consequences

- An unknown country is refused before any call, with the field path.
- Hosts map their own country codes to `Country`; the package publishes no ISO table.
