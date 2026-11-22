# ADR 0001 — Stateless SDK

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md). Same decision as
urssaf-attestations-verification-code-securite ADR 0001, for a different reason.

## Context

The reference orchestrates a long-running workflow: local records, state machines, atomic claims, a transactional
outbox, integration events, polling and reconciliation, persisted through Doctrine and driven by Messenger, Lock and
Scheduler. Unlike the stateless sibling, this API **writes** (enrolment, posting declaration) and has a life cycle:
an enrolment waits for a human validation on urssaf.fr, a posting request is instructed until its certificate is
available. But the life cycle is held by URSSAF and readable on demand: `GET /enrolements/{id}` gives the enrolment
`status`, `GET /certificat/{id}` the state of the dossier. No operation lists or searches the posting requests, and the
request is full of personal data (NIR, names, birth date, addresses) that the package must not persist (roadmap,
api-reference §4.12).

## Decision

- No aggregate with local state, no repository, unit of work, transaction manager, domain event, outbox or
  integration event.
- No Doctrine bridge, no Messenger message, no Lock, no Scheduler schedule.
- The application layer exposes one command or query per operation (`RequestEnrolment`, `DeleteEnrolment`,
  `ListEnrolments`, `GetEnrolment`, `SubmitPosting`, `GetCertificate`) and their handlers, which call the
  `MobilityGateway` port and return domain values or port results.
- The **caller** persists the identifiers it needs, `idEnrolement` and the dossier `id` of the posting receipt (the
  only handle on a posting: nothing lists them), and polls `GetEnrolment` / `GetCertificate` at its own pace.

## Consequences

- The only shared state is the OAuth2 token cache.
- A lost posting receipt cannot be recovered through the API ([ADR 0004](0004-unknown-outcome-and-retry.md)); the
  documentation tells hosts to store the receipt before anything else.
- The Deptrac `DoctrineBridge` layer and the Doctrine dev dependencies stay, so an optional tracking bridge (identifiers
  and statuses only, never personal data) can be added later. Doing so needs a new ADR.
