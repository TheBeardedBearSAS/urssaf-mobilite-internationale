# Standalone use (without Symfony)

`TheBeardedBear\UrssafMobiliteInternationale\Standalone\UrssafMobiliteInternationaleBuilder` wires the package from PSR
interfaces only. It builds `UrssafMobiliteInternationale`, a facade that returns one stateless handler per use case
(ADR [0001](adr/0001-stateless-sdk.md), [0010](adr/0010-symfony-bridge.md)).

## Building the package

```php
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafMobiliteInternationale\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafMobiliteInternationale\Application\Port\Credentials;
use TheBeardedBear\UrssafMobiliteInternationale\Standalone\UrssafMobiliteInternationaleBuilder;

$package = UrssafMobiliteInternationaleBuilder::create(
    new HttpTransport($psr18Client, $psr17RequestFactory, $psr17StreamFactory),
    new InMemoryCredentialsProvider(['tenant-1' => Credentials::of($clientId, $clientSecret)]),
    Environment::Sandbox,
)
    ->withTokenStore($sharedTokenStore)   // PSR-6 / PSR-16 store shared by every process
    ->withLogger($logger)
    ->build();
```

Every `with*` method returns a new builder.

| Method | Effect |
|---|---|
| `withEnvironment(Environment)` | `Sandbox` or `Production` (the URSSAF host of each is built in). |
| `withBaseUri(string)` / `withTokenUri(string)` | Override the API base URI and the OAuth2 token URI (the sandbox token host is not published, ADR [0002](adr/0002-http-client-adaptations.md)). `https` only, checked by `build()`. |
| `withTokenStore(TokenStore)` | Default: in memory, per process. Use a shared store in production (ADR [0007](adr/0007-tenant-model.md)). |
| `withOptions(GatewayOptions)`, `withLogger()`, `withClock()` | Rate limiter (opt-in), id generator, clock, logger, certificate 400 handling. |
| `withValidationPolicy(PostingValidationPolicy)` | Opt-in local rules applied before every posting (ADR [0008](adr/0008-local-validation.md)). |
| `withGateway(MobilityGateway)` | Replaces the HTTP gateway, for tests (see [testing](testing.md)). **Refused with `LogicException` for the production environment**, whatever the order of the calls. |

## Use cases

Each command or query carries the `TenantId` first; a tenant is one set of OAuth2 credentials (ADR 0007).

| Facade method | Command / query | Result |
|---|---|---|
| `requestEnrolment()` | `RequestEnrolment(TenantId, Siren, ?Nic, EnrolmentCategory)` | `Enrolment` |
| `listEnrolments()` | `ListEnrolments(TenantId, offset, limit, ?sort)` or `ListEnrolments::firstPage(TenantId)` (offset 0, limit 20: a package choice, ADR [0009](adr/0009-enrolments-and-pagination.md)) | `EnrolmentPage` |
| `getEnrolment()` | `GetEnrolment(TenantId, EnrolmentId)` | `Enrolment` |
| `deleteEnrolment()` | `DeleteEnrolment(TenantId, EnrolmentId)` | nothing |
| `submitPosting()` | `SubmitPosting::of(TenantId, PostingRequest)` or `SubmitPosting::afterCheckingEnrolment(TenantId, PostingRequest, EnrolmentId)` | `PostingReceipt` |
| `getCertificate()` | `GetCertificate(TenantId, DossierId)` | `CertificateAvailable` \| `CertificateInProgress` \| `CertificateRefusedOrUnknown` |

```php
$enrolment = $package->requestEnrolment()(new RequestEnrolment($tenant, Siren::fromString('...'), null, EnrolmentCategory::Rg));
// Store $enrolment->id() now, then poll GetEnrolment until the status is ACCEPTE (human validation on urssaf.fr).

$receipt = $package->submitPosting()(SubmitPosting::of($tenant, $request));
// Store $receipt->dossierIds() IMMEDIATELY: nothing lists the postings.

$result = $package->getCertificate()(new GetCertificate($tenant, $receipt->first()));
if ($result instanceof CertificateAvailable) {
    $result->file()->stream()->copyTo($resource); // streamed, never buffered
    $result->file()->stream()->close();
}
```

### The optional enrolment guard

A posting needs an accepted enrolment (URSSAF answers 403 otherwise). `SubmitPosting::afterCheckingEnrolment()` makes
the handler read the enrolment first and throw `Application\Exception\EnrolmentNotAccepted`
(`enrolment.not_accepted`, parameter `reason` = `status` or `siren`) unless its status is `ACCEPTE` and its SIREN equals
the `referenceEntreprise` SIREN; nothing is submitted then. The NIC is not compared. `SubmitPosting::of()` never checks.
The handler order is: validation policy, guard, one call.

## Failures, retries and uncertain outcomes (ADR [0004](adr/0004-unknown-outcome-and-retry.md))

The package never retries nor resends anything: it classifies, the caller decides. Failures are `UrssafException`s;
`RetryableException`s are safe to repeat.

- **Store the posting receipt before anything else.** No operation lists or searches postings: a lost receipt cannot be
  recovered through the API.
- **Never resend a posting after `OutcomeUnknown`.** The posting may have been recorded; check with URSSAF outside the API
  (case manager, urssaf.fr) before any new submission. The exception message never contains the payload.
- After an `OutcomeUnknown` on an enrolment, list the enrolments and look for the SIREN: a resend would at worst be
  refused with 400 "already exists".
- **Confirm a deletion with `GetEnrolment`.** After an `UrssafUnavailable` that may have reached URSSAF, read the
  enrolment: 404 (`UrssafResourceNotFound`) or status `SUPPRIME` means it is deleted; the answer of a second delete is not
  published.
- Reads (`GetEnrolment`, `ListEnrolments`, `GetCertificate`) and 429 answers may be retried.

## Personal data

A `PostingRequest` holds a NIR, names, a birth date and addresses: it is never logged, serialized nor persisted by the
package (ADR [0006](adr/0006-personal-data.md)), and the certificate content is only streamed (ADR
[0005](adr/0005-files.md)). Do not persist them either; keep the identifiers only.
