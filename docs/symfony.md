# Symfony bundle

Bundle class: `TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle` (alias
`urssaf_mobilite_internationale`), for Symfony 6.4, 7.x and 8.x. The design decisions that differ from the other URSSAF
packages are in [ADR 0010](adr/0010-symfony-bridge.md): every name carries `mi` (console namespace `urssaf:mi:`, service
ids and cache keys `urssaf_mi.`, Monolog channel `urssaf_mi`, variables `URSSAF_MI_*`), so that several URSSAF packages
can be installed in the same application ([Living next to the other URSSAF packages](#living-next-to-the-other-urssaf-packages)).

The package is stateless ([ADR 0001](adr/0001-stateless-sdk.md)): the bundle needs no database, no Messenger, no
Scheduler and no lock. The only state it shares is the cache of the OAuth2 access tokens.

## Installation with Symfony Flex

The recipe lives in `recipe/thebeardedbear/urssaf-mobilite-internationale/<version>/` (manifest and copied files), in the
layout of [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib), where it will be published when the
package is released, with the agreement of the maintainer ([ADR 0010](adr/0010-symfony-bridge.md), decision 7). Until
then, install the bundle by hand (next section), or serve the recipe from your own endpoint:
`bin/build-flex-endpoint <output-dir> <base-url>` (`make flex-endpoint` builds it in `var/flex-endpoint`) writes an
`index.json` and one JSON file per recipe, to serve over HTTPS at `<base-url>` and to declare in `extra.symfony.endpoint`
of the application (`["<base-url>/index.json", "flex://defaults"]`).

`composer require thebeardedbear/urssaf-mobilite-internationale` then registers the bundle, copies a commented
`config/packages/urssaf_mobilite_internationale.yaml` (the simulated gateway in the `test` environment) and adds to
`.env`:

| Variable | Default | Use |
|---|---|---|
| `URSSAF_MI_ENV` | `sandbox` | `sandbox` or `production`. |
| `URSSAF_MI_CLIENT_ID` | empty | OAuth2 client id of the account. |
| `URSSAF_MI_CLIENT_SECRET` | empty | OAuth2 client secret: keep it in the secrets vault or in `.env.local`, never in git. |
| `URSSAF_MI_BASE_URI` | empty | Overrides the API base URI (https only). Empty: the URSSAF host of the environment. |
| `URSSAF_MI_TOKEN_URI` | empty | Overrides the OAuth2 token URI (https only). Empty: the URSSAF host of the environment. |

Check the result with `php bin/console urssaf:mi:check`. A test boots the bundle with the copied configuration (empty,
undefined and defined URI variables), so that the recipe follows the configuration tree. Add a new version directory
when a release changes the recipe (Flex picks the highest version not above the installed package version).

## Manual installation

```sh
composer require thebeardedbear/urssaf-mobilite-internationale
composer require symfony/framework-bundle symfony/console symfony/http-client nyholm/psr7
```

`symfony/http-client` and `nyholm/psr7` provide the default PSR-18 client and the PSR-17 factories; you can use another
PSR-18 client (see `http_client`); the default client is created with `max_redirects: 0`, and a client of your own must
**neither follow redirects nor retry** (a followed 307 / 308 replays the `POST` with the declaration and the secret to
another URL; [ADR 0002](adr/0002-http-client-adaptations.md), Amendments). Add `symfony/translation` to translate the violation messages and the failures
printed by the commands (English and French are shipped). Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    TheBeardedBear\UrssafMobiliteInternationale\Bridge\Symfony\UrssafMobiliteInternationaleBundle::class => ['all' => true],
];
```

Then configure it, for instance in `config/packages/urssaf_mobilite_internationale.yaml`:

```yaml
urssaf_mobilite_internationale:
    environment: '%env(URSSAF_MI_ENV)%'                    # sandbox | production
    base_uri: '%env(default::URSSAF_MI_BASE_URI)%'         # optional, https only
    token_uri: '%env(default::URSSAF_MI_TOKEN_URI)%'       # optional, https only
    credentials:
        default:
            client_id: '%env(URSSAF_MI_CLIENT_ID)%'
            client_secret: '%env(URSSAF_MI_CLIENT_SECRET)%'

when@test:
    urssaf_mobilite_internationale:
        gateway: { simulated: true }
```

## Configuration reference

The configuration tree is defined in `Bridge\Symfony\DependencyInjection\BundleConfiguration`. There is no `messenger`,
`lock`, `scheduler`, `outbox` nor `persistence` key: a key that is not listed is refused.

| Key | Type | Default | Description |
|---|---|---|---|
| `environment` | enum `sandbox` \| `production` | required | Target platform (`https://api-edi.urssaf.fr` / `https://api.urssaf.fr`). Usually `%env(URSSAF_MI_ENV)%`: the value is resolved, and checked, at runtime. |
| `base_uri` | string \| null | `null` | Replaces the API base URI, path included (default: the host of the environment + `/tiersdecl/v1/mobilite`). `https` only, see [Overriding the URIs](#overriding-the-uris). |
| `token_uri` | string \| null | `null` | Replaces the OAuth2 token URI (default: the host of the environment + `/api/oauth/v1/token`; the sandbox token host is not published, [ADR 0002](adr/0002-http-client-adaptations.md)). `https` only. |
| `user_agent` | string | `thebeardedbear-urssaf-mobilite-internationale` | Value of the `User-Agent` header. |
| `headers.request_id` | string \| null | `null` | Name of an optional request id header, whose value is a UUID per attempt. No header is published for this API, so none is sent by default. |
| `token_safety_margin` | int >= 0 | `60` | Seconds before expiry from which an access token is renewed. |
| `http_client` | string \| null | `null` | Service id of a PSR-18 client. Default: a dedicated Symfony HttpClient wrapped in `Psr18Client` (it also provides the PSR-17 factories, whatever the client in use). |
| `http_timeout` | float >= 0.1 | `15.0` | Timeout in seconds of the default HTTP client. |
| `credentials_provider` | string \| null | `null` | Service id of a `CredentialsProvider`. Mutually exclusive with `credentials`. |
| `credentials` | map tenant id => `{client_id, client_secret}` | `[]` | Static credentials by tenant (both keys required, non-empty). The client secret must be an `%env(...)%` placeholder. |
| `tenants` | list of string | keys of `credentials` | Tenants checked by `urssaf:mi:check` and defaulted by the other commands. Set it with `credentials_provider`, which gives no tenant list. |
| `token_store.cache_pool` | string | `cache.app` | Service id of the PSR-6 cache pool that stores the access tokens. Recommended: a **dedicated pool** (see below). |
| `token_store.service` | string \| null | `null` | Service id of a `TokenStore`; takes precedence over the cache pool. |
| `gateway.simulated` | bool | `false` | Tests only: replaces the URSSAF API with the in-memory `Testing\SimulatedMobilityGateway`, exposed as a public service to script the scenarios (see [testing.md](testing.md)). Refused with `production` (see below). |
| `certificate.bad_request_as_in_progress` | bool | `true` | A 400 on the certificate download means "not available yet" (a package choice, [ADR 0003](adr/0003-certificate-result.md)); `false`: it is an `UrssafRequestRejected`. |
| `validation.pdf_telework_rules` | bool | `false` | Also applies the PDF telework rules before every posting ([ADR 0008](adr/0008-local-validation.md)). |
| `rate_limiter.enabled` | bool | `false` | Enforces the published quota (200 requests per minute for the whole API) per tenant, all operations together, in the current process ([ADR 0000](adr/0000-reference-architecture.md) row 14). The quota is global to the API and informational: leave it off unless several workers of one process share it. A 429 stays an `UrssafUnavailable`. |

Validation rules enforced at container build time:

- `credentials` and `credentials_provider` are mutually exclusive, and one of them is required.
- A client secret must be an `%env(...)%` placeholder (or a secret): a literal secret is refused.
- `environment` must be `sandbox` or `production` (an environment variable placeholder is resolved, and checked, at runtime).
- `gateway.simulated` cannot be used with a literal `environment: production`. An `%env()%` placeholder is not resolved
  when the container is compiled, so `SimulatedGatewayFactory` refuses it again at runtime: with
  `environment: '%env(URSSAF_MI_ENV)%'` resolved to `production`, getting the gateway (or any handler) throws a
  `LogicException`. The option belongs in test-only configuration (a `when@test` block, as the recipe does, or
  `config/packages/test/`), never in a configuration that applies to production.
- A literal `base_uri` or `token_uri` must be an absolute `https` URI without user info, query or fragment.
- `token_safety_margin` must not be negative, `http_timeout` must be at least `0.1`, `user_agent` must not be empty.

### Overriding the URIs

`base_uri` and `token_uri` replace the whole URI, host and path. They exist because the sandbox token host is not
published and because a test may point the bundle to a mock HTTP server. Rules ([ADR 0002](adr/0002-http-client-adaptations.md)):

- **`https` only, even for a local host**: `http` is refused, so that the client secret and the token never travel in
  clear. A mock server needs a TLS certificate.
- A literal value is checked when the container is built. A value read from `%env()%` is checked when the
  configuration is first used, and reported by the `configuration` check of `urssaf:mi:check` instead of a stack trace.
- An **empty** value is "not overridden": an empty environment variable resolves to the empty string, and the bundle
  treats it as `null`. `%env(default::URSSAF_MI_BASE_URI)%` also covers a variable that is not defined at all.

### Credentials per tenant

A tenant is an identifier of your application (at most 64 characters) that owns one pair of URSSAF OAuth2 credentials
([ADR 0007](adr/0007-tenant-model.md)). Every call is made on behalf of a tenant: `ListEnrolments::firstPage(TenantId::fromString('tenant-1'))`.
The `credentials` map holds one entry per tenant; `Credentials` is not serialisable, its secret is masked in `var_dump()`
and it is never logged. In the compiled container the secret only exists as a reference to its environment variable.

```yaml
urssaf_mobilite_internationale:
    environment: '%env(URSSAF_MI_ENV)%'
    credentials:
        tenant-1:
            client_id: '%env(URSSAF_MI_CLIENT_ID)%'
            client_secret: '%env(URSSAF_MI_CLIENT_SECRET)%'
```

When the credentials live elsewhere (a vault, a database), implement `Application\Port\CredentialsProvider`
(`forTenant(TenantId): Credentials`, throwing `Application\Exception\CredentialsNotFound` when the tenant is unknown) and
give its service id:

```yaml
urssaf_mobilite_internationale:
    environment: '%env(URSSAF_MI_ENV)%'
    credentials_provider: App\Urssaf\VaultCredentialsProvider
    tenants: [tenant-1, tenant-2]     # no list can be derived from a provider
```

### Dedicated cache pool for the access tokens

The access tokens are stored in the `token_store.cache_pool` pool (`cache.app` by default), under keys prefixed
`urssaf_mi.` ([ADR 0007](adr/0007-tenant-model.md)). Declare a dedicated pool, so that `cache:pool:clear cache.app` (a
routine deployment step) does not force a new token request for every tenant, and so that the tokens live in an adapter
you control (Redis with authentication, for instance). `urssaf:mi:check` warns while the shared pool is used.

```yaml
framework:
    cache:
        pools:
            urssaf_mi.token_cache:
                adapter: cache.adapter.redis
                provider: '%env(REDIS_DSN)%'

urssaf_mobilite_internationale:
    token_store: { cache_pool: urssaf_mi.token_cache }
```

The pool must be shared by every process that calls URSSAF (web, workers, cron): a per-process `array` adapter would
request a token per run. Tokens are secrets: do not expose the pool through a cache dashboard or a shared namespace.
To store the tokens somewhere else, implement `Application\Port\TokenStore` (or wrap a PSR-16 cache in
`Infrastructure\TokenStore\Psr16TokenStore`) in a service and set `token_store.service`.

## Services

The use case handlers are the only public services; each one is stateless:

| Service | Use |
|---|---|
| `Application\Command\RequestEnrolmentHandler` | Requests the enrolment of a company (`RequestEnrolment`). |
| `Application\Query\ListEnrolmentsHandler` | Lists a page of enrolments (`ListEnrolments`). |
| `Application\Query\GetEnrolmentHandler` | Reads one enrolment (`GetEnrolment`). |
| `Application\Command\DeleteEnrolmentHandler` | Deletes an enrolment (`DeleteEnrolment`). |
| `Application\Command\SubmitPostingHandler` | Declares a posting (`SubmitPosting::of()` or `::afterCheckingEnrolment()`), after the local validation policy. |
| `Application\Query\GetCertificateHandler` | Polls and downloads the certificate of a dossier (`GetCertificate`). |
| `Testing\SimulatedMobilityGateway` | With `gateway.simulated` only: the in-memory platform, to script the scenarios. |

Everything else is private, and prefixed `urssaf_mi.` when it has no class or interface to be identified by. Autowire the
ports (`Application\Port\Urssaf\MobilityGateway`, `Application\Port\CredentialsProvider`, `Application\Port\TokenStore`,
`Domain\Posting\PostingValidationPolicy`) when you need them. Inject a handler in your own services:

```php
final readonly class PostingService
{
    public function __construct(private SubmitPostingHandler $submit, private GetCertificateHandler $certificate) {}
}
```

There is no posting command: the request holds a NIR, names and addresses, which would end in shell history and cron
logs ([ADR 0010](adr/0010-symfony-bridge.md), decision 4). Build the `PostingRequest` in your application and call
`SubmitPostingHandler`. Store the receipt (`$receipt->first()`) immediately: nothing lists the postings
([standalone.md](standalone.md#failures-retries-and-uncertain-outcomes-adr-0004)).

## Console commands

Every command is in the `urssaf:mi:` namespace and has a `--tenant` option, optional when exactly one tenant is
configured. Exit codes: `0` success, `1` failure, `2` invalid usage (the arguments or the options are refused, nothing
was sent to URSSAF), `3` (`urssaf:mi:certificate` only) the certificate is not available yet.

| Command | Use |
|---|---|
| `urssaf:mi:check [--tenant] [--skip-remote]` | Installation diagnostics, see below. Exits with 1 when a check fails: a deployment step. |
| `urssaf:mi:enrolment:request <siren> --category=<RG...> [--nic=<nic>]` | Requests an enrolment; prints its id and status. The enrolment is then validated by a human on urssaf.fr: read it with `show` until its status is `ACCEPTE`. |
| `urssaf:mi:enrolment:list [--offset=0] [--limit=20] [--sort=<expr>]` | Prints a page of enrolments and the offset of the next page. The defaults are a package choice ([ADR 0009](adr/0009-enrolments-and-pagination.md)); `--sort` is sent as is (its syntax is not published). |
| `urssaf:mi:enrolment:show <id>` | Prints one enrolment. |
| `urssaf:mi:enrolment:delete <id> [--force]` | Deletes an enrolment after a confirmation; without a terminal the confirmation is impossible and `--force` is required (exit code 2). |
| `urssaf:mi:certificate <dossierId> <output-file> [--force]` | Writes the PDF certificate of a dossier to a file. |

The commands never print a posting, a NIR, a name, an address, a token, a secret nor the content of a certificate
([ADR 0006](adr/0006-personal-data.md)): only the public SIREN and NIC of your own enrolments, identifiers, statuses and
the messages of the package. The `code`, `message` and `description` that URSSAF returns (their semantics are not
published, and they may echo submitted values) are printed only with `--show-urssaf-errors`. Failures are translated
(English and French shipped, `urssaf_mobilite_internationale` translation domain) when `symfony/translation` is
installed.

### The certificate command

`urssaf:mi:certificate` streams the PDF to the file, never to the terminal and never whole in memory
([ADR 0005](adr/0005-files.md)). The file is created with the mode `0600` whatever the umask of the process (it names a
posted worker), and a file that already exists is kept unless `--force` is given, in which case it is replaced by a new
file with the same mode. A partial file is removed when the transfer fails. The exit code follows the
`CertificateResult` ([ADR 0003](adr/0003-certificate-result.md)):

| Result | Exit code | Meaning |
|---|---|---|
| `CertificateAvailable` | `0` | The certificate is written. |
| `CertificateInProgress` | `3` | Not available yet (204, and 400 by default): try again later. Nothing is written. A script polls on this code. |
| `CertificateRefusedOrUnknown` | `1` | The dossier is unknown or its file was refused (404). Do not poll. |
| Failure (`UrssafException`) | `1` | Authentication, quota, outage, rejected request. |
| Invalid usage | `2` | Invalid identifier, existing file, directory that does not exist. |

### Installation diagnostics

`urssaf:mi:check` runs the checks in this order, for every tenant that applies:

| Check | What it verifies |
|---|---|
| `configuration` | The options resolved at runtime (environment, URIs, request id header, safety margin) are valid; prints the resolved URIs, never a credential. An invalid value is a failed check, not a stack trace. |
| `credentials` | Every tenant has credentials in the provider (nothing is sent). |
| `authentication` | URSSAF grants an access token to the tenant (a token request on a token store of its own, so that a cached token never hides refused credentials). `--skip-remote` skips it. With `gateway.simulated`, a `gateway` warning replaces it. |
| `token store` | A probe token can be saved, read and deleted (under its own key `urssaf_mi.check`). A warning when the shared `cache.app` pool is used. |

Tag your own services with `urssaf_mi.installation_check` (autoconfigured when they implement
`Bridge\Symfony\Diagnostic\InstallationCheck`) to add checks: they run after those of the package, unless their tag has
a priority above `1`.

## Logging

The gateway logs on the Monolog channel `urssaf_mi` when a `logger` service exists: method, path, status, duration and
request id only, never a body, a token, a query string or a header value.

## Living next to the other URSSAF packages

The CESU, PAJE, auto-entrepreneur and this package share the `/tiersdecl/v1` gateway of URSSAF and may be installed in
the same application. Nothing is shared between their bundles:

| | This package | Rule for the others |
|---|---|---|
| Bundle alias, configuration root | `urssaf_mobilite_internationale` | their own alias |
| Console commands | `urssaf:mi:*` | never `urssaf:check` nor `urssaf:status` |
| Environment variables | `URSSAF_MI_ENV`, `URSSAF_MI_CLIENT_ID`, `URSSAF_MI_CLIENT_SECRET`, `URSSAF_MI_BASE_URI`, `URSSAF_MI_TOKEN_URI` | their own prefix (`URSSAF_CESU_*`...) |
| Service ids, tag, translation domain | `urssaf_mi.*`, `urssaf_mi.installation_check`, `urssaf_mobilite_internationale` | their own prefix |
| Token cache keys | `urssaf_mi.<sha256>` | their own prefix |
| Monolog channel | `urssaf_mi` | their own channel |
| Locks | not applicable (no lock) | |

Give each package its own credentials, its own token cache pool (`urssaf_mi.token_cache`) and its own variables. The
tokens are not shared between packages: the scope of this API (`ods.dlagd`) is part of the cache key.

### Pointing the bundle to a mock HTTP server

To test an application against a mock of the URSSAF API (WireMock, Prism, a stub server), without the simulator:

```dotenv
# .env.test.local
URSSAF_MI_ENV=sandbox
URSSAF_MI_BASE_URI=https://mock.internal.example/tiersdecl/v1/mobilite
URSSAF_MI_TOKEN_URI=https://mock.internal.example/api/oauth/v1/token
```

Both URIs replace the host and the path, so the mock may serve any path. The mock must serve TLS (`http` is refused);
trust its certificate in your HTTP client (`framework.http_client.default_options.cafile`, or `http_client` pointing to
a client that does). Other URSSAF packages configured in the same application keep their own variables and URIs. Keep
these two variables out of the production environment: an overridden URI is a legitimate setting (proxy, sandbox host),
so the bundle cannot tell a mock from the real platform.

## Testing

`gateway.simulated: true` replaces the whole gateway with the in-memory `Testing\SimulatedMobilityGateway`: nothing
leaves the process, and the six handlers answer like the API ([testing.md](testing.md)). It is refused with
`environment: production`, literal or resolved from an environment variable. The tests of the bundle itself boot a
kernel with a mocked PSR-18 client and run the commands through `CommandTester`; they use synthetic data only.
