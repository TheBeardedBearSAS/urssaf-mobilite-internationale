# Architecture decision records

Each record follows Context / Decision / Consequences. A new decision, or any departure from the reference package
(`thebeardedbear/urssaf-tiers-prestations`), gets its own record `NNNN-<title>.md`, accepted before the code that
implements it. Later changes are added as dated amendments at the end of the record.

| ADR | Title | Status |
|---|---|---|
| [0000](0000-reference-architecture.md) | Reference architecture inherited from urssaf-tiers-prestations | Accepted |
| [0001](0001-stateless-sdk.md) | Stateless SDK | Accepted |
| [0002](0002-http-client-adaptations.md) | HTTP client adaptations | Accepted |
| [0003](0003-certificate-result.md) | Certificate result | Accepted |
| [0004](0004-unknown-outcome-and-retry.md) | Unknown outcomes and retries | Accepted |
| [0005](0005-files.md) | Certificate files: streamed, never logged | Accepted |
| [0006](0006-personal-data.md) | Personal data | Accepted |
| [0007](0007-tenant-model.md) | Tenant model | Accepted |
| [0008](0008-local-validation.md) | Local validation limited to the specification | Accepted |
| [0009](0009-enrolments-and-pagination.md) | Enrolments: pagination, raw values and the optional guard | Accepted |
| [0010](0010-symfony-bridge.md) | Symfony bridge and coexistence with the other URSSAF packages | Accepted |
| [0011](0011-country-and-closed-lists.md) | `Country` and the other closed lists | Accepted |
| [0012](0012-static-analysis-on-minimum-php.md) | Static analysis on the minimum supported PHP version | Accepted |
