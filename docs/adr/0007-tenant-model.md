# ADR 0007 — Tenant model

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md) rows 8 and 12.

## Context

The reference is multi-tenant (`TenantId`, `CredentialsProvider::forTenant()`). This API identifies the caller by its
OAuth2 credentials only: no platform id, no application id, no declarant id in the paths (api-reference §1). Enrolments
are attached to the issuer behind the credentials. Whether one credential set may act for several issuers is not
published.

## Decision

- A tenant is one set of OAuth2 credentials (`client_id`, `client_secret`) of one issuer (*émetteur*). Nothing else is
  resolved per tenant.
- `TenantId` is the first argument of every gateway method, command and query; `CredentialsProvider::forTenant()` is
  the only source of secrets (`Infrastructure\Credentials\InMemoryCredentialsProvider` and the bundle configuration
  `credentials.<tenant>.{client_id, client_secret}`, or a host service).
- Token cache key: `CacheKey::of('urssaf_mi.' . sha256(environment | base URI | token URI | scope | client id))`,
  stored as `hashed_<sha256>` when longer than the PSR-6 limit; the secret is never part of the key. The token URI is
  in the key because it is overridable ([ADR 0002](0002-http-client-adaptations.md)).

## Consequences

- Two tenants with the same `client_id` share the cached token.
- The package does not check that an enrolment id or a dossier id belongs to the tenant: URSSAF does (404 / 403).
