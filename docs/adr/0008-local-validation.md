# ADR 0008 — Local validation limited to the specification

Status: Accepted (2026-11-22). Relates to [ADR 0000](0000-reference-architecture.md) rows 3–4 and
[ADR 0011](0011-country-and-closed-lists.md). Spec: api-reference §2.1, §2.5, §3.8, §4.4, §4.10, §5.3, §5.7, §5.13,
§5.14, §5.22.

## Context

The posting request is large, the PDF per-profile rules (employee / self-employed, §4.5.2) are broken or empty, and
the Swagger requires few fields. A local rule stricter than the server would refuse valid requests; a rule looser than
the published contract wastes a call and a quota slot. Invented rules are forbidden.

## Decision

- **Mechanism** (reference `Domain/PaymentRequest/Input` + `Domain/Validation`):
  - each value object (`Siren`, `Nir`, `EmailAddress`, `PhoneNumber`, `CalendarDate`, `Country`, …) validates its
    own value in its named constructor and throws `InvalidValue` (first error only);
  - typed objects (`Worker`, `Telework`, `PostingRequest`, …) take value objects and enforce their cross-field rules
    (`nombreJours` with `teletravail`) by signature (`Telework::withTelework` takes the number of days, so the
    rule cannot be skipped) and as `InvariantViolation` for the other rules; a host that builds them directly gets the
    first error. The draft reader reports the missing days as `field.required` at `…telework.daysPerMonth`;
  - the cumulative path is `Domain\Posting\Input`: nested `*Draft` classes of raw scalars (`PostingRequestDraft`,
    `WorkerDraft`, …) read by `PostingRequestReader::read(PostingRequestDraft, PostingValidationPolicy)` with the
    reference `DraftReader`, which turns every `InvalidValue` into a `Violation` at its field path and applies the
    cross-field and policy rules; result `PostingDraftReading` = `CompletePostingDraft` (holds the `PostingRequest`)
    | `IncompletePostingDraft` (holds the `ValidationResult`), thrown as `ValidationFailed` by
    `PostingRequest::fromDraft()`;
  - `Domain\Posting\PostingValidationPolicy` (`final readonly`) carries the opt-in flags and
    `validate(PostingRequest): ValidationResult`; the reader applies it, and `SubmitPostingHandler` (which receives a
    typed `PostingRequest`) applies it again before the call, so the policy holds on both paths. The bundle and the
    standalone builder configure it.
- **Validated locally, all at once** (`ValidationFailed` with every `Violation`, codes `<subject>.<reason>`, field
  path in the parameters): what the contract states and nothing else.
  - Required keys of the Swagger inside each object (`siren`; `nom`, `email`, `tel1`; the four children of
    `declaration`; `raisonSociale`, `adresseConnue`, `email`; `adresse`, `pays` of an address; `siren`, `nic` of the
    reference; both `signature` booleans; `nir`, `nomUsage`, `prenom`, `dateNaissance`, `nationalite`; `teletravail`;
    `adresseHabituelle`; `activiteSubstentielle`; `nature`, `pays`, `entreprise` of an activity).
  - Lengths counted in characters (`mb_strlen`): max 255 for names, labels and address lines, max 15 for
    `codePostal`, 7–30 for phones.
  - Patterns verbatim: enrolment `siren` exactly 9 characters and `nic` exactly 5, no pattern; reference `siren`
    `^[0-9A-Z]{9}$`, `nic` `^[0-9]{0,5}$` (no minimum, §5.14), `numeroCompteExterne` `^[0-9]{18}$`; `nir`
    `^[0-9]{6}[0-9AaBb][0-9]{6}$`, `cleNir` `^[0-9]{2}$`. The enrolment and reference constraints stay separate value
    objects (`Enrolment\Siren`, `Enrolment\Nic`; `Posting\CompanyReference`).
  - `format: email`: local check of the minimal shape only: one `@`, non-empty local and domain parts, no whitespace; no RFC regex (the server is the judge).
  - `format: date`: a real calendar date, sent `Y-m-d` (`CalendarDate`).
  - `nombreJours` 1–31, and required when `teletravail` is `true` (Swagger description).
  - Closed lists ([ADR 0011](0011-country-and-closed-lists.md)); `categorie` required on creation (§4.4, PDF).
- **Encoded, not validated**: `adresse` of a company is omitted from the wire when `adresseConnue` is `false`, and the
  host `adresse` when `adresseHabituelle` is `true` (Swagger: "ignored"); no violation is raised for it.
- **Typed as required** (the Swagger leaves them optional, the PDF treats them as mandatory, §5.13): `declaration`
  (its four children are required) and `declarant` (starred in PDF §3.3.1). `personne` and every field of `demande`
  stay optional.
- **Opt-in, off by default** (PDF-only rule, §5.7; the default validates the Swagger rules only): `PostingValidationPolicy::withPdfTeleworkRules()` (bundle
  `validation.pdf_telework_rules`) also requires `accordSalarie` and `pays` when `teletravail` is `true`.
- **Not validated** (left to the server until published, §5.22): NIR key (`cleNir` against `nir`), SIREN Luhn, empty
  required strings beyond the stated lengths, `dateDebut` ≤ `dateFin`, employee / self-employed conditions,
  `natureAutre` when `nature` is `AUTRE`, relations between `activitesDeclarees`, `activitesActuelles` and `nature`,
  `codePostal` per country, phone format, `commentaire` length.

## Consequences

- Every local refusal can be traced to a sentence of the contract or of the PDF.
- When URSSAF publishes a rule, it is added behind a policy flag first, then made default in a minor release with a
  CHANGELOG entry.
