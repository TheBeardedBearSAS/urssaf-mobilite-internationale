# ADR 0010 — Symfony bridge and coexistence with the other URSSAF packages

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md) rows 16–17,
[ADR 0002](0002-http-client-adaptations.md), [ADR 0006](0006-personal-data.md) and [ADR 0007](0007-tenant-model.md).
Precedents: CESU ADR 0013, AVCS bundle (no Messenger), AE ADR 0015 (standalone guard).

## Context

The reference ships a Symfony 6.4 / 7.x bundle with Messenger, Lock and Scheduler integrations. This SDK is stateless
([ADR 0001](0001-stateless-sdk.md)), shares the `/tiersdecl/v1` gateway with the CESU, PAJE and auto-entrepreneur
packages, and several URSSAF packages may be installed in the same application. The sandbox token host is not
published.

## Decision

1. **Names.** Bundle `Bridge\Symfony\UrssafMobiliteInternationaleBundle`, alias and configuration root
   `urssaf_mobilite_internationale`; service ids, parameters, tag and translation domain with that prefix; console
   namespace `urssaf:mi:`; Monolog channel and cache key prefix `urssaf_mi`; recipe variables `URSSAF_MI_ENV`,
   `URSSAF_MI_CLIENT_ID`, `URSSAF_MI_CLIENT_SECRET`. Nothing is shared with another URSSAF bundle.
2. **Configuration.** `environment` (`sandbox` | `production`), `base_uri`, `token_uri` (both optional, validated as
   in ADR 0002 at build time when literal and at runtime when resolved from `%env()%`), `user_agent`,
   `headers.request_id`, `token_safety_margin`, `http_client`, `http_timeout` (default client only),
   `credentials.<tenant>.{client_id, client_secret}` or `credentials_provider`, `tenants`, `token_store.{cache_pool,
   service}`, `gateway.simulated`, `certificate.bad_request_as_in_progress` ([ADR 0003](0003-certificate-result.md)),
   `validation.pdf_telework_rules` ([ADR 0008](0008-local-validation.md)), `rate_limiter.enabled` (default `false`,
   [ADR 0000](0000-reference-architecture.md) row 14). No `messenger`, `lock`, `scheduler`, `outbox`, `persistence` key.
3. **Commands** (`--tenant`, optional when one tenant is configured): `urssaf:mi:enrolment:request <siren> --nic
   --category`, `urssaf:mi:enrolment:list --offset=0 --limit=20 --sort` (defaults are a package choice, [ADR 0009](0009-enrolments-and-pagination.md) §5.20), `urssaf:mi:enrolment:show <id>`,
   `urssaf:mi:enrolment:delete <id>` (confirmation; `--force` required without interaction),
   `urssaf:mi:certificate <id> <output-file>` ([ADR 0005](0005-files.md)), `urssaf:mi:check`. Exit codes `0`, `1`
   (failure), `2` (invalid usage).
4. **No posting command.** The request holds a NIR, names and addresses: command-line arguments end in shell history,
   process lists and cron logs, and a file option would only move the payload. Hosts call `SubmitPostingHandler`.
5. **Simulator guard.** `gateway.simulated` is refused with the literal `production` at build time, and
   `SimulatedGatewayFactory` refuses it at runtime when the environment resolves to `production`. The standalone
   `UrssafMobiliteInternationaleBuilder::withGateway()` refuses `Environment::Production`.
6. **Diagnostics** (`urssaf:mi:check`): configuration (resolved environment and URIs), credentials, token store,
   authentication (token call), simulated gateway warning.
7. **Recipe.** `recipe/thebeardedbear/urssaf-mobilite-internationale/0.1/` (bundle registration, config with
   `%env()%`, `.env` entries), checked by `FlexRecipeTest`, built by `bin/build-flex-endpoint`. Submission to
   `symfony/recipes-contrib` happens at release, only after the maintainer's agreement.
8. Symfony `^6.4.3 || ^7.4 || ^8.0` in `require-dev`, reference `conflict` floors; Symfony 8 in the CI matrix on
   PHP ≥ 8.4.

## Consequences

- Installing this bundle next to the CESU, PAJE or auto-entrepreneur bundle needs no extra configuration.
- Documented in `docs/symfony.md`, `docs/standalone.md` and `docs/testing.md`.

## Amendments

- **2026-11-22 (Symfony bridge lot).**
  - Decision 1: service ids, the container parameter (`urssaf_mi.tenants`) and the diagnostic tag
    (`urssaf_mi.installation_check`) are prefixed `urssaf_mi.`, like the cache keys and the Monolog channel; only the
    bundle alias and the translation domain keep the long name `urssaf_mobilite_internationale`.
  - Decision 2: a client secret must be an `%env(...)%` placeholder (a literal secret is refused at build time); an empty
    `base_uri` / `token_uri` (an empty variable) means "not overridden" and the recipe reads them with
    `%env(default::...)%`; the `user_agent` and `headers.request_id` keys of `ApiConfiguration` are exposed.
  - Decision 3: every command accepts `--show-urssaf-errors` (ADR 0006); `urssaf:mi:certificate` exits with `3` when the
    certificate is not available yet (`CertificateInProgress`), `1` for `CertificateRefusedOrUnknown` and every failure,
    `2` for an invalid usage, and refuses an existing output file unless `--force` (ADR 0005); `urssaf:mi:enrolment:request`
    takes the required `--category` option. The failures printed by the commands are translated when a translator exists.
  - Decision 6: `urssaf:mi:check` has a `configuration` check (an invalid runtime-resolved option is a failed check, not a
    stack trace), and the token store check warns while the shared `cache.app` pool is used.
