# Testing a host application

`TheBeardedBear\UrssafMobiliteInternationale\Testing\SimulatedMobilityGateway` is an in-memory implementation of the
`MobilityGateway` port: it answers the six operations like the URSSAF API would, without any network. Never use it
outside tests: nothing is sent to URSSAF.

## Production guard

`SimulatedMobilityGateway::forEnvironment(Environment::Production)` throws a `LogicException`, and
`UrssafMobiliteInternationaleBuilder::withGateway()` refuses the production environment (ADR
[0010](adr/0010-symfony-bridge.md)). The Symfony bundle refuses `gateway.simulated` with `environment: production`.

```php
$simulator = SimulatedMobilityGateway::create();   // clock frozen on 2026-01-01T00:00:00Z
// or new SimulatedMobilityGateway($clock) to date the enrolments yourself

$package = UrssafMobiliteInternationaleBuilder::create($transport, $credentials, Environment::Sandbox)
    ->withGateway($simulator)
    ->build();
```

## Behaviour

| Operation | Simulated answer |
|---|---|
| `requestEnrolment` | New enrolment `ENCOURS`, id `00000000-0000-4000-8000-000000000001`, `...002`, and so on. The SIREN of a not deleted enrolment: `UrssafRequestRejected` (400). |
| `listEnrolments` | Creation order, sliced by `offset` / `limit` (`sort` is ignored); the same `InvalidValue` as the HTTP gateway for a negative offset or a limit below 1. |
| `getEnrolment` / `deleteEnrolment` | Unknown id: `UrssafResourceNotFound` (404). A deleted enrolment stays readable with the status `SUPPRIME`. |
| `submitPosting` | Always accepted, answers a new dossier id `00000000-0000-4000-9000-000000000001`, `...002`. The enrolment of the company is not checked (use `SubmitPosting::afterCheckingEnrolment()`). |
| `getCertificate` | New dossier: `CertificateInProgress` (204). Unknown dossier: `CertificateRefusedOrUnknown` (404). |

Enrolments are global: URSSAF identifies the issuer by its credentials only (ADR [0007](adr/0007-tenant-model.md)).

## Driving the scenario

```php
$enrolment = $package->requestEnrolment()(new RequestEnrolment($tenant, $siren, null, EnrolmentCategory::Rg));
$simulator->acceptEnrolment($enrolment->id());      // ENCOURS -> ACCEPTE (or refuseEnrolment() -> REFUSE)

$receipt = $package->submitPosting()(SubmitPosting::afterCheckingEnrolment($tenant, $request, $enrolment->id()));
$dossier = $receipt->first();

$simulator->makeCertificateAvailable($dossier);              // or ($dossier, 'custom PDF bytes')
$simulator->makeCertificateInProgress($dossier);
$simulator->makeCertificateRefused($dossier);                // 404: refused or unknown
```

An available certificate is a fresh `SimulatedBinaryStream` at every `getCertificate` call (default content
`SimulatedMobilityGateway::DEFAULT_CERTIFICATE_CONTENT`); its content is masked in debug output.

Assertions: `calls()` lists every operation called with its tenant, `postings()` the accepted posting requests (personal
data: for assertions only).

## Failures

The simulator never fails on its own beyond the cases above. To test how your code reacts to `OutcomeUnknown`,
`UrssafUnavailable` or `UrssafAuthenticationFailed`, write a small `MobilityGateway` double that throws them, as the
package does in `tests/Unit/Application/Fake/FakeMobilityGateway.php` (one scripted response or throwable per call,
every call recorded). Test the retry policy of your application against it: the package itself never retries.
