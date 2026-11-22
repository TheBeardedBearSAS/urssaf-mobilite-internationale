# URSSAF "API Mobilité internationale" — technical reference

This document is the single source of truth used by the package for enums, validation rules and wire fixtures.
It summarises the official specification:

- API identifier: `mobi` (catalogue), OpenAPI title "API Mobilite internationale"
- Swagger contract: **v1.0.8** (OpenAPI 3.0.3; `docs/openapi/mobi-prd.json` and `docs/openapi/mobi-sb.json`; no
  publication date in the file)
- Technical documentation PDF: **v1.0.0** (2025-10-22, "Rédaction initiale de la documentation"), file name
  `…_v1.0.0.pdf` (`docs/reference/Documentation-API-Mobilite_internationale-v1.0.0.pdf`, 23 pages)
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/mobi
- PDF: https://cedito-medias.urssaf.fr/medias/PortailAPI/files/documentationAPIs/MOBI/Documentation-API-Mobilite_internationale-v1.0.0.pdf

The package constant `SpecVersion::CURRENT` must equal `1.0.0`. Rule shared by the URSSAF packages: `SpecVersion` is
the version in the **file name of the published PDF** (the most detailed source), falling back to the Swagger version
when the PDF is not versioned. Here the PDF (1.0.0) is **older** than the Swagger (1.0.8): the Swagger has evolved after
the PDF was written (the PDF has no later revision history entry), so the contract is the more recent source for
request/response shapes while the PDF is the only source for the functional explanations. Every difference between the
two is listed in §2 (per operation) and §5. Field names, codes and wire values below are reproduced verbatim from the
specification (they are French by design, and ASCII without accents in the Swagger); everything else in the package is
English.

> Status: **implemented** (not released). The six operations are implemented and tested; §4 records the decisions the
> package took (settled by the [ADRs](adr/README.md)) and §5 the points that stay open until the sandbox or URSSAF settles
> them. Architecture and remaining work: [roadmap.md](roadmap.md).

> **Source and rights.** The specification, the field names, the codes and the official JSON examples quoted here come
> from the URSSAF documents listed above and remain URSSAF's property. They are reproduced only so the package can
> interoperate with the API, and they are not covered by this package's MIT license. In case of discrepancy, the
> official URSSAF documentation prevails. The official documents themselves are not versioned: download them into
> `docs/reference/`, a local git-ignored folder. This package is not affiliated with URSSAF.

---

## 1. General

| Topic | Value |
|---|---|
| Purpose | Submit posting requests (*demandes de détachement*) within the European Economic Area (*EEE*) directly, without filling in the international mobility form (*formulaire de demande de mobilité internationale*); follow the enrolment of the issuing company; download the mobility certificate. Swagger title: "API Mobilite internationale". |
| Access | Restricted offer (PDF §2.1): access request validated by ACOSS, through the URSSAF API portal. Credentials (client id / client secret) issued after approval. |
| Prerequisite | The issuer (*émetteur*) must first enrol the company (*entreprise destinatrice*) with `POST /enrolements`; the enrolment must then be **validated by a user of the enrolled SIRET** from their dashboard on https://www.urssaf.fr, section « Travail à l'étranger » (PDF §3). Only then can postings be declared for that company. |
| Security | OAuth2 **client credentials** (PDF §2.2). The Swagger `securitySchemes.OAuthUrssaf` is typed `oauth2` but declares the `implicit` flow with `authorizationUrl` = token URL: contract oddity, see §5. |
| Token endpoint | `POST https://api.urssaf.fr/api/oauth/v1/token` (form-urlencoded: `grant_type=client_credentials`, `client_id`, `client_secret`, `scope`). In both Swagger files the URL is the production one; the sandbox token host is not published (`https://api-edi.urssaf.fr/api/oauth/v1/token` by analogy with the other URSSAF APIs, see §5). |
| Scope | `ods.dlagd` ("Acces a l'API Mobilite internationale") |
| Production base URL | `https://api.urssaf.fr/tiersdecl/v1/mobilite` (`mobi-prd.json` `servers[0].url`) |
| Sandbox base URL | `https://api-edi.urssaf.fr/tiersdecl/v1/mobilite` (`mobi-sb.json` `servers[0].url`). The two Swagger files are **identical except for this URL** (checked on the whole document). |
| Package configuration | `Infrastructure\Urssaf\Environment` maps `Sandbox` → `https://api-edi.urssaf.fr` and `Production` → `https://api.urssaf.fr`; `Infrastructure\Urssaf\UrssafEndpoints` holds `TOKEN_PATH` = `/api/oauth/v1/token`, `TOKEN_SCOPE` = `ods.dlagd`, `API_BASE_PATH` = `/tiersdecl/v1/mobilite`. The host must stay overridable by configuration. |
| Gateway | The base path `/tiersdecl/v1` is shared with the other URSSAF "tiers déclarant" APIs (CESU, PAJE, auto-entrepreneur); only the last segment (`/mobilite`) and the scope differ. |
| Content type | `application/json` (request and response), except `GET /certificat/{id}` which returns `application/pdf` on success. |
| Headers | None declared in the Swagger (no header parameter, no required header). Besides `Authorization: Bearer <token>`, the package sends no other header by default; an optional request id header (name configurable, value a UUID per attempt) can be enabled, but no such header is **published** for this API. |
| HTTP errors | 400 (functional), 401, 403 (declaration only, PDF), 404, 429 (quota, PDF), 500. The two sources do not agree per operation, see §2. |

### 1.1 Quotas (HTTP 429 when exceeded)

PDF §2.5: default quota of **200 requests per minute in production**, for the whole API (not per operation). Two levels:
a maximum quota per API (service availability) and a quota per API client. The sandbox quota is **not published**. HTTP 429
is documented by the PDF only (no 429 response in the Swagger). No `Retry-After` header is documented.

### 1.2 Known limitations (official)

- Access is restricted and enrolment must be validated by the enrolled company before any declaration (PDF §3,
  sequence diagrams §3.2): an enrolment that is refused, or **refused after having been validated**, forbids any further
  declaration for that company (`POST /declaration/detachement/UE` then answers 403 according to the PDF).
- Only the posting for a country under the legislation of the European Union / EEA is exposed (`/declaration/detachement/UE`);
  no other mobility case is available.
- The PDF is incomplete (v1.0.0): the request-field cross references of §4.5.2 are broken ("Erreur ! Source du renvoi
  introuvable."), §4.5.2.3 (independent worker) is empty, the 200 responses of §4.3 / §4.4 are empty, and the 200 text of
  §4.6.3 is copied from the enrolment deletion. See §5.
- No error code catalogue is published (only HTTP status classes and the `code` / `message` / `description` envelope).
- Fields specific to an employee or to a self-employed worker are not distinguished by the contract: **only the
  required-ness shown in the Swagger is enforced by the schema**; the functional rules ("champ obligatoire en cas de
  télétravail", "dans le cas d'une activité salariée…") are conditional and checked server-side.

### 1.3 Error payload

Functional errors share a three-field envelope (`components.responses.FunctionalError`, `UnauthorizedError`, `NotFound`,
`InternalError`):

| Field | Type | Swagger description (verbatim) |
|---|---|---|
| `code` | string | "classe d'anomalie ou d'erreur" |
| `message` | string | "libelle" |
| `description` | string | "code de l'anomalie ou de l'erreur" |

The descriptions of `code` and `description` look swapped compared to the other URSSAF APIs (where `code` is the error
code); the actual semantics are **not published** (no example, no code list).

Shape depends on the operation: the **400 of `POST /declaration/detachement/UE` is an array** of this object, the 400/401/404/500 of
every other operation is a **single object**; the 401 and 500 of `POST /declaration/detachement/UE` are declared without body ("Unauthorized",
"Internal Server Error"). The package parser must accept an object, a list of objects and an empty body.

---

## 2. Operations

Six operations (the six `method + path` of the contract). Swagger tags: `partenaire` (enrolments, certificate) and
`declarations` (posting); the declared global tag `GrandDeclarant` ("Operations dediees aux grands declarants") is used by
no operation. No official payload example (JSON) exists in the Swagger (the only `examples` key is an empty object on the
400 of the declaration) nor in the PDF (tables only): **no wire fixture can be taken from the sources** (see "Fixtures"
below and §5). Only per-field `example` values are published; they are quoted in the field tables.

Fixtures: none published; `tests/Fixtures/wire/` stays empty until the sandbox contract tests (roadmap) record real
payloads (to be reviewed for personal data before being committed).

### 2.1 Request an enrolment — `POST /enrolements`

`operationId`: `post-enrolement`. PDF §4.1. Summary: "Ajouter un enrolement"; description: "Service de demande d'enrolement
pour une entreprise ou un travailleur independant." Submits an enrolment request for a company or a self-employed worker.
The enrolment is then validated (or refused) by a user of the SIRET from urssaf.fr (PDF §3); the issuer polls
`GET /enrolements/{id}` for the status.

Body (`application/json`, `components.schemas.EnrolementRequest`):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `siren` | string | yes | exactly 9 chars (`minLength` = `maxLength` = 9); no pattern in this schema (the response example is `123456789`) |
| `nic` | string | PDF: not starred / Swagger: no | exactly 5 chars (`minLength` = `maxLength` = 5); no pattern (example `12345`). PDF glossary: *Numéro Interne de Classement*, 5 characters. Absent = enrolment of the whole company (*inferred*, not stated) |
| `categorie` | string | PDF: yes (starred) / Swagger: no | enum, see §3.1 |

Responses:

| Code | Source | Body | Meaning |
|---|---|---|---|
| 201 | Swagger + PDF | `Enrolement` (below), description "Partenaire enregistre" | enrolment created; `idEnrolement` identifies it (to quote to the support) |
| 400 | Swagger + PDF | error object (§1.3) | PDF: enrolment already exists for the company, unknown company, invalid JSON |
| 401 | Swagger + PDF | error object | PDF: token missing or expired |
| 404 | Swagger only | error object | "La ressource n'existe pas" (meaning unclear on a creation) |
| 429 | PDF only | not specified | quota exceeded (§1.1) |
| 500 | Swagger + PDF | error object | technical error |

`Enrolement` (response of 201, 200 of `GET /enrolements/{id}`, items of `GET /enrolements`):

| Field | Type | Rules |
|---|---|---|
| `idEnrolement` | string, `format: uuid` | enrolment identifier |
| `siren` | string | 9 chars, example `123456789` |
| `nic` | string | 5 chars, example `12345` |
| `categorie` | string | enum, §3.1 |
| `status` | string | enum `ACCEPTE`, `ENCOURS`, `REFUSE`, `SUPPRIME`, §3.2 (the PDF never mentions this field) |
| `dateDemande` | string, `format: date-time` | "Date au format <yyyy-MM-dd'T'HH:mm:ssZZZZ> (ISO8601 date extended)." |
| `dateMiseAJour` | string, `format: date-time` | same format |

No field of `Enrolement` is declared required. Fixtures: none published.

### 2.2 List enrolments — `GET /enrolements`

`operationId`: `get-enrolement-list`. PDF §4.4 (titled "Obtenir les détails d'un enrôlement", same title as §4.3).
Summary: "Service de recuperation de la liste detaillee des enrolements". PDF: returns "les informations de tous vos
enrôlements actifs ou en attente de validation" (the Swagger status enum also contains `REFUSE` and `SUPPRIME`: whether
those are listed is not published).

Query parameter (single parameter `pageable`, `components.parameters.Pageable`, `style: form`, `explode: true`,
`required: false`, description "minimal Pageable query parameters ex sort (ASC|DESC)"):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `offset` | integer | yes inside `pageable` (PDF: parameter listed) | "Décalage du premier enregistrement retourné (pagination)" (PDF) |
| `limit` | integer | yes inside `pageable` (PDF: parameter listed) | "Nombre d'enregistrements retournés (pagination)" (PDF). Maximum, default and minimum are **not published** |
| `sort` | string | no | Swagger only; description mentions `ASC` / `DESC`; sort field and exact syntax not published |

With `explode: true` the object serialises as flat query parameters: `GET /enrolements?offset=0&limit=20` (the PDF
shows `?offset=XXX&limit=XXX`). The `pageable` parameter itself is optional but `offset` and `limit` are listed as required
inside it: whether the call without any parameter is accepted is **not published**.

Responses:

| Code | Source | Body | Meaning |
|---|---|---|---|
| 200 | Swagger + PDF (empty description) | array of `Enrolement` (§2.1) | list of enrolments; ordering and total count are not published (no pagination envelope) |
| 400 | Swagger + PDF | error object | PDF: "Erreurs sur les paramètres d'appel" |
| 401 | Swagger + PDF | error object | token missing or expired |
| 404 | Swagger only | error object | "La ressource n'existe pas" |
| 429 | PDF only | not specified | quota exceeded |
| 500 | Swagger + PDF | error object | technical error |

Fixtures: none published.

### 2.3 Get one enrolment — `GET /enrolements/{id}`

`operationId`: `get-enrolement`. PDF §4.3. Summary: "Service de recuperation du detail d'un enrolement".

Path parameter:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `id` | string, `format: uuid` | yes | enrolment identifier (`idEnrolement`) |

Responses:

| Code | Source | Body | Meaning |
|---|---|---|---|
| 200 | Swagger ("Partenaire enregistre") + PDF (empty description) | `Enrolement` (§2.1) | enrolment detail, including `status` |
| 400 | Swagger only | error object | "Erreur Fonctionnelle" |
| 401 | Swagger + PDF | error object | token missing or expired |
| 404 | Swagger + PDF | error object | PDF: the enrolment does not exist |
| 429 | PDF only | not specified | quota exceeded |
| 500 | Swagger + PDF | error object | technical error |

Fixtures: none published.

### 2.4 Delete an enrolment — `DELETE /enrolements/{id}`

`operationId`: `delete-enrolement`. PDF §4.2. Summary: "Service de suppression d'un enrolement".

Path parameter:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `id` | string, `format: uuid` | yes | enrolment identifier to delete |

Responses:

| Code | Source | Body | Meaning |
|---|---|---|---|
| 200 | Swagger ("OK") + PDF | none | PDF: enrolment deleted; **no further declaration is possible for the company linked to it** |
| 400 | Swagger only | error object | "Erreur Fonctionnelle" |
| 401 | Swagger + PDF | error object | token missing or expired |
| 404 | Swagger + PDF | error object | PDF: the enrolment does not exist |
| 429 | PDF only | not specified | quota exceeded |
| 500 | Swagger + PDF | error object | technical error |

`status` value `SUPPRIME` (§3.2) presumably describes a deleted enrolment (*inferred*, the sources do not link them).
Fixtures: none published.

### 2.5 Submit a posting request (EU) — `POST /declaration/detachement/UE`

`operationId`: `post-declaration-grands-comptes`. Swagger tag `declarations`; summary "Effectuer une demande de mobilite
internationale"; description "Effectuer une demande de detachement de mobilite internationale pour l'UE". PDF §4.5 declares
a posting for "un pays sous la législation de l'Union Européenne". **The PDF gives the URL
`POST https://api.urssaf.fr/tiersdecl/v1/mobilite/detachement/UE` (without `/declaration`)** whereas the Swagger and the
PDF's own sequence diagram (§3.2.3: `POST /declaration/detachement/UE`) use `/declaration/detachement/UE`: the Swagger
path is authoritative (§5).

Requires a validated enrolment for the company in `declaration.referenceEntreprise` (PDF §3 and §4.5.3: 403 otherwise).

Body (`application/json`): schema title `DeclarationWithDeclarantDetachementSimplifie` (`components.schemas.DeclarationDetachementSimplifie`,
identical to the inline schema). No field is required at the root: `declaration` ("Donnees de la demande de
detachement.") and `declarant` are optional in the schema, although the PDF treats `declarant` as mandatory in practice
(its fields are starred in §3.3.1). Inside `declaration`, four children are required; inside `demande`, none (nor is
`personne` required).

Root:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `declaration` | object | no (Swagger) | the request data, below |
| `declarant` | object | no (Swagger) | `Declarant`: "Informations concernant l'entreprise deposant la demande ILASS" (PDF §3.3.1: "Informations sur l'utilisateur de l'API"). Sibling of `declaration`, not nested in it |

`declarant` (`Declarant`):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `nom` | string | yes | max 255; PDF: "Raison sociale de l'entreprise"; example `Nom exemple` |
| `email` | string, `format: email` | yes | contact email; example `test@test.com` |
| `tel1` | string | yes | 7–30 chars (`Telephone`); no pattern |
| `tel2` | string | no | 7–30 chars; alternate number |

`declaration`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `entrepriseDeclarant` | `Entreprise` | yes | "Informations sur l'entreprise pour laquelle est faite la demande de detachement" (PDF §3.3.2) |
| `referenceEntreprise` | `ReferenceEntreprise` | yes | administrative identification of that company |
| `signature` | object | yes | legal statements, below |
| `demande` | object | yes | the posting request, below. Schema title "Demande de detachement simplifiee" |

`Entreprise` (also used for `activitesDeclarees.entreprise` / `activitesActuelles[].entreprise`):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `raisonSociale` | string | yes | max 255 |
| `adresse` | `Adresse` | no | ignored when `adresseConnue` is `false` |
| `adresseConnue` | boolean | yes | "Si false, alors le champs adresse est ignore" (PDF: "Si l'adresse est complète, veuillez passer ce champ à "true"") |
| `email` | string, `format: email` | yes | company email (example `test@test.com`) |

`Adresse`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `adresse` | string | yes (when the address object is sent) | max 255 |
| `adresseCplt` | string | no | max 255 |
| `ville` | string | no | max 255 |
| `codePostal` | string | no | max 15 (foreign postal codes) |
| `pays` | string | yes (when the address object is sent) | enum `Pays`, §3.3 (example `ITALIE`) |

`ReferenceEntreprise`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `siren` | string | yes | exactly 9 chars, pattern `[0-9A-Z]{9}` (example `404833048`) |
| `nic` | string | yes | max 5 chars, pattern `[0-9]*`, **no `minLength`** (example `00022`); PDF glossary: 5 characters |
| `numeroCompteExterne` | string | no | exactly 18 chars, pattern `[0-9]{18}` (example `447001202020374795`); PDF: "Numéro de compte externe. Donnée facultative" |

`signature`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `autorisationDonneesPerso` | boolean | yes | "Declaration d'autorisation d'utilisation des donnees personnelles. Les donnees ne sont utilisees que dans le traitement du dossier."; `default: false` |
| `declarationHonneur` | boolean | yes | "Declaration sur l'honneur affirmant que les donnees envoyees sont correctes."; `default: false` |

`demande` (no field is required inside `demande` in the Swagger; the requirements below are conditional or PDF-only):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `personne` | `Personne` | no (Swagger) | person in international mobility (PDF §3.3.3) |
| `dateDebut` | string, `format: date` | no | posting start; "Date au format <yyyy-MM-dd> (ISO8601 date extended)."; example `2017-12-31` |
| `dateFin` | string, `format: date` | no | posting end; same format |
| `teletravail` | object | no | telework, below |
| `adresseAccueil` | object | no | address of the worker during the posting, below |
| `memeDomaineActivite` | string | no | enum `NC` / `OUI` / `NON` (§3.4): "Valeurs Oui / Non / Non Concerne"; PDF: is the mission in the same sector as the person's activity in France |
| `infraPerenne` | string | no | enum `NC` / `OUI` / `NON`; "Champs a remplir si l'infratructure de l'entreprise permet le retour du salarie" |
| `activiteSubstentielle` | object | no | substantial activity in France, below (spelling `Substentielle` is verbatim) |
| `activitesDeclarees` | `Activite` (a single **object**) | no | the activity in international mobility (PDF §3.3.4) |
| `activitesActuelles` | array of `Activite` | no | PDF §3.3.5: for a self-employed worker, the other activities carried out in France |
| `commentaire` | string | no | no maximum; "Commentaire du declarant a destination du gestionnaire" (PDF: not printed on the certificate) |

`Personne` (title "Personne", `components.schemas.Personne`):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `nir` | string | yes | exactly 13 chars, pattern `[0-9]{6}[0-9AaBb][0-9]{6}`; "nir de la personne partant travailler a l etranger. Nir sur 13 caracteres, sans la cle"; example `2690549588157` |
| `cleNir` | string | no | exactly 2 digits, pattern `[0-9]{2}` (example `80`); PDF: "Le calcul de la clé nir valide le nir renseigné" |
| `nomUsage` | string | yes | max 255; PDF: "Nom matrimonial ou nom d'usage" |
| `nomNaissance` | string | no | max 255 |
| `prenom` | string | yes | max 255 (PDF describes it as "Nom de naissance du salarie": typo) |
| `dateNaissance` | string, `format: date` | yes | `yyyy-MM-dd` (example `2017-12-31`) |
| `lieuNaissance` | string | no | max 255; city of birth (example `Argeles-Gazost`) |
| `paysNaissance` | string | no | enum `Pays`, §3.3 |
| `nationalite` | string | yes | enum `Pays`, §3.3 (the nationality is expressed as a country name) |
| `adresse` | `Adresse` | no | personal address |
| `email` | string, `format: email` | no | may equal the employer's email |
| `tel1` | string | no | 7–30 chars |
| `tel2` | string | no | 7–30 chars |

`teletravail`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `teletravail` | boolean | yes | "true si le declare effectuera du teletravail" |
| `accordSalarie` | boolean | no (Swagger) / PDF: mandatory when teleworking | "true si Le salarie a donne son accord pour teletravailler" |
| `pays` | string | no (Swagger) / PDF: mandatory when teleworking | enum `Pays`; country the telework is done from |
| `nombreJours` | integer (`int32`) | conditional | 1–31; "Nombre de jours de teletravail par mois. Obligatoire si le booleen teletravail est a true" (PDF: if the mission lasts less than a month, number of telework days); example `2` |

`adresseAccueil`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `adresseHabituelle` | boolean | yes | "true si le salarie partant travailler a l'etranger garde son domicile comme adresse d'accueil (ex : pour les frontaliers)"; when `true` the `adresse` below is **ignored** |
| `adresse` | `Adresse` | no | host address |

`activiteSubstentielle`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `activiteSubstentielle` | string | yes | enum `NC` / `OUI` / `NON` (§3.4): whether the activity in France is more than 25 % of the company activity (PDF) |
| `paysInteret` | string | no | enum `Pays`; "pays d'intérêt si l'activité est < 25 % en France" (PDF) |
| `critereDActivite` | string | no | enum `CA`, `TempsTravail`, `Prestations`, `Remuneration` (§3.5); chosen activity criterion |

`Activite` (items of `activitesActuelles`, value of `activitesDeclarees`; `components.schemas.Activite`):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `nature` | string | yes | enum, §3.6 (14 values) |
| `pays` | string | yes | enum `Pays`, §3.3 |
| `entreprise` | `Entreprise` | yes | company concerned by this activity |
| `type` | string | no | enum with the single value `AUTRE`; no description |
| `natureAutre` | string | no | PDF: to fill when `nature` is `AUTRE` |
| `natureBaseAffectation` | string | no | enum `Pays`; PDF: for a sailor or crew member, the home base (country) |
| `activiteFrancaise` | boolean | no | PDF: `false` for an international mobility activity, `true` when declaring another activity carried out in France |
| `activiteSup25` | boolean | no | PDF: is at least 25 % of the company activity carried out in France |
| `factureEmise` | boolean | no | PDF: for a non-employed activity, will an invoice be issued |
| `contratSigne` | boolean | no | PDF: for an employed activity, will a new employer contract be signed |
| `activiteDeclareeCplt` | object | no | complementary information, below |

`activiteDeclareeCplt` (all optional booleans; PDF wording in parentheses):

| Field | PDF meaning |
|---|---|
| `salarieRemplacant` | does the employee replace another employee at the end of their posting (includes people outside the company) |
| `affiliationSecuriteSociale` | was the employee affiliated to the French social security during the 30 days before the posting start date |
| `remunerationModifiable` | can the host-country company unilaterally change the base remuneration |
| `licenciementSalarie` | can the host-country company dismiss the employee |
| `sanctionsDisciplinairesSalarie` | can the host-country company impose disciplinary sanctions |
| `contratTravailEstEtranger` | did the employee sign an additional work contract with the host foreign company |

Per-profile rules (employee vs self-employed worker): PDF §4.5.2.1 / §4.5.2.2 / §4.5.2.3 should list the mandatory
fields for every request, for an employee, and for an independent worker, but the references are broken and the
independent-worker part is empty. **Not published** (§5).

Responses:

| Code | Source | Body | Meaning |
|---|---|---|---|
| 200 | Swagger ("Declaration enregistree") + PDF ("La déclaration a été prise en compte") | array of `EnteteDeclaration` = `{ "id": <uuid> }` | request recorded. The array shape (and thus one `id` per element) is unexplained; the `id` is presumably the **dossier number** to pass to `GET /certificat/{id}` (*inferred*: that endpoint describes its `id` as "le numero de dossier") |
| 400 | Swagger (array) + PDF | array of error objects | PDF: mandatory data missing, invalid input… |
| 401 | Swagger ("Unauthorized", no body) + PDF | none | token missing or expired |
| 403 | PDF only | not specified | "Vous n'êtes pas habilité à effectuer une déclaration pour cette entreprise" (no validated enrolment) |
| 429 | PDF only | not specified | quota exceeded |
| 500 | Swagger ("Internal Server Error", no body) + PDF | none | technical error |

Fixtures: none published (a complete request must be recorded on the sandbox).

### 2.6 Get the certificate — `GET /certificat/{id}`

`operationId`: `get-certificat`. PDF §4.6. Summary: "Service de recuperation du certificat"; description: "Recuperation du
telechargement du certificat de mobilite internationale. Le parametre id correspond au numero de dossier." PDF:
returns the mobility certificate; "Si le certificat n'est pas disponible, le retour permet de connaitre le statut du
dossier (en cours de traitement ou refusé)".

Path parameter:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `id` | string, `format: uuid` | yes | Swagger: "numero de dossier"; PDF: "Identifiant de la demande" |

Responses (the two sources **disagree** on the statuses):

| Code | Source | Body | Meaning |
|---|---|---|---|
| 200 | Swagger ("Certificat pdf") | `application/pdf`, `type: string`, `format: binary` | the certificate. The PDF §4.6.3 200 text is copied from the enrolment deletion ("L'enrôlement a été supprimé…"): copy-paste error, ignored |
| 204 | Swagger only | none | "Le code 204 est retourne si le dossier existe et est en cours d'instruction." |
| 400 | Swagger ("Erreur Fonctionnelle") + PDF | error object | PDF: **no downloadable certificate yet**, the file may be under review, call again later to follow its progress |
| 401 | Swagger + PDF | error object | token missing or expired |
| 404 | Swagger ("La ressource n'existe pas") + PDF | error object | PDF: **the file was refused**, no certificate will be issued, contact your case manager. Swagger: resource does not exist |
| 429 | PDF only | not specified | quota exceeded |
| 500 | Swagger + PDF | error object | technical error |

Consequence: "in progress" is 204 (Swagger) or 400 (PDF); "refused" is 404 (PDF) which the Swagger reads as "not found". The
package must expose a result type distinguishing *available / in progress / refused-or-unknown* and keep the raw status
(§4). The response `Content-Disposition` / file name is not published. Fixtures: none published (binary PDF).

---

## 3. Reference values

All values are verbatim from the Swagger (`components.schemas`) unless stated.

### 3.1 `categorie` (enrolment category) — schema `Categorie`

`AA`, `AE`, `RG`, `ACT`, `RGACT`, `TIAC`, `TIPL`, `TIAE`, `TIPM`, `AUTRE` (10 values).

PDF glossary (§1.2): `AA` Artiste Auteur; `ACT` Associations et Collectivités Territoriales; `AE` Auto-Entrepreneur;
`RG` Régime général; `RGACT` Régime général ou Association et Collectivités Territoriales; `TIAC` Travailleur indépendant
– Artisan et commerçant; `TIPL` Travailleur indépendant – Profession Libérale. `TIAE` and `TIPM` have an **empty or
truncated** definition ("Travailleur indépendant -" for `TIPM`); `AUTRE` is not in the glossary. Labels above are indicative.

### 3.2 `status` (enrolment) — schema `StatusEnrolement`

`ACCEPTE`, `ENCOURS`, `REFUSE`, `SUPPRIME`. Semantics are not published; *inferred* from the PDF sequence diagrams (§3.2):
`ENCOURS` after `POST /enrolements` until the company validates, `ACCEPTE` once validated (declarations allowed), `REFUSE`
when refused, `SUPPRIME` after deletion. The wire value `ENCOURS` has no underscore, unlike `EN_COURS` of the unused enum
`Etat` (§3.7).

### 3.3 `pays` — schema `Pays` (226 values)

Used by `adresse.pays`, `paysNaissance`, `nationalite`, `teletravail.pays`, `paysInteret`, `Activite.pays`,
`Activite.natureBaseAffectation`. The list contains the **empty string `""`** as its first value, and apparent
duplicates / aliases (`REPUBLIQUE DE COREE` and `COREE DU SUD`; `REPUBLIQUE POPULAIRE DEMOCRATIQUE DE COREE` and `COREE
DU NORD`; `QUEBEC`, `AURIGNY`, `HERM`, `JETHOU`, …); all 226 values are distinct. Names are upper-case, without accents. The
ordering (33 alphabetical values `ALLEMAGNE` … `SUISSE`, then 14 values `ANDORRE` … `URUGUAY`, then the rest alphabetical
from `AFGHANISTAN`) suggests EEA/Switzerland, then partner countries, then others (*inferred*, not stated). Reproduced
verbatim, in contract order:

```text
"", "ALLEMAGNE", "AURIGNY", "AUTRICHE", "BELGIQUE", "BULGARIE"
"CHYPRE", "CROATIE", "DANEMARK", "ESPAGNE", "ESTONIE", "FINLANDE"
"FRANCE", "GRECE", "HONGRIE", "IRLANDE", "ISLANDE", "ITALIE"
"LETTONIE", "LIECHTENSTEIN", "LITUANIE", "LUXEMBOURG", "MALTE", "NORVEGE"
"PAYS-BAS", "POLOGNE", "PORTUGAL", "REPUBLIQUE TCHEQUE", "ROUMANIE", "ROYAUME-UNI"
"SLOVAQUIE", "SLOVENIE", "SUEDE", "SUISSE", "ANDORRE", "ARGENTINE"
"BRESIL", "CANADA", "CHILI", "REPUBLIQUE DE COREE", "COREE DU SUD", "ETATS-UNIS"
"INDE", "JAPON", "MAROC", "MONACO", "TUNISIE", "URUGUAY"
"AFGHANISTAN", "AFRIQUE DU SUD", "ALBANIE", "ALGERIE", "ANGOLA", "ANTIGUA-ET-BARBUDA"
"ARABIE SAOUDITE", "ARMENIE", "AUSTRALIE", "AZERBAIDJAN", "BAHAMAS", "BAHREIN"
"BANGLADESH", "BARBADE", "BELIZE", "BENIN", "BHOUTAN", "BIELORUSSIE"
"BIRMANIE", "BOLIVIE", "BOSNIE-HERZEGOVINE", "BOTSWANA", "BRUNEI", "BURKINA"
"BURUNDI", "CAMBODGE", "CAMEROUN", "CAP-VERT", "CENTRAFRIQUE", "CHINE"
"ILE CHRISTMAS", "ILES COCOS OU KEELING", "COLOMBIE", "COMORES", "CONGO", "REPUBLIQUE DEMOCRATIQUE DU CONGO"
"ILES COOK", "REPUBLIQUE POPULAIRE DEMOCRATIQUE DE COREE", "COREE DU NORD", "COSTA RICA", "COTE D'IVOIRE", "CUBA"
"DJIBOUTI", "REPUBLIQUE DOMINICAINE", "DOMINIQUE", "EGYPTE", "EMIRATS ARABES UNIS", "EQUATEUR"
"ERYTHREE", "ESWATINI", "ETHIOPIE", "FIDJI", "GABON", "GAMBIE"
"GEORGIE", "GHANA", "GRENADE", "GUAM", "GUATEMALA", "GUERNESEY"
"GUINEE", "GUINEE EQUATORIALE", "GUINEE-BISSAO", "GUYANA", "HAITI", "ILES HEARD ET MACDONALD"
"HERM", "HONDURAS", "HONG-KONG", "INDONESIE", "IRAQ", "IRAN"
"ISRAEL", "JAMAIQUE", "JORDANIE", "KAZAKHSTAN", "KENYA", "KIRGHIZSTAN"
"KIRIBATI", "KOSOVO", "KOWEIT", "LAOS", "LESOTHO", "LIBAN"
"LIBERIA", "LIBYE", "MACAO", "MACEDOINE DU NORD", "MADAGASCAR", "MALAISIE"
"MALAWI", "MALDIVES", "MALI", "ILES MARIANNES DU NORD", "ILES MARSHALL", "JERSEY"
"JETHOU", "MAURICE", "MAURITANIE", "MEXIQUE", "MICRONESIE", "MOLDAVIE"
"MONGOLIE", "MONTENEGRO", "MOZAMBIQUE", "NAMIBIE", "NAURU", "NEPAL"
"NICARAGUA", "NIGER", "NIGERIA", "NIUE", "ILE NORFOLK", "NOUVELLE-CALEDONIE"
"NOUVELLE-ZELANDE", "OMAN", "OUGANDA", "OUZBEKISTAN", "PAKISTAN", "PALAOS"
"PALESTINE", "PANAMA", "PAPOUASIE-NOUVELLE-GUINEE", "PARAGUAY", "PEROU", "PHILIPPINES"
"POLYNESIE FRANCAISE", "PORTO RICO", "QATAR", "QUEBEC", "RUSSIE", "RWANDA"
"SAHARA OCCIDENTAL", "SAINT-BARTHELEMY", "SAINT-CHRISTOPHE-ET-NIEVES", "SAINTE-LUCIE", "SAINT-MARIN", "SAINT-MARTIN"
"SAINT-PIERRE-ET-MIQUELON", "SAINT-VINCENT-ET-LES-GRENADINES", "SALOMON", "SALVADOR", "SAMOA", "SAMOA AMERICAINES"
"SAO TOME-ET-PRINCIPE", "SENEGAL", "SERBIE", "SEYCHELLES", "SIERRA LEONE", "SINGAPOUR"
"SOMALIE", "SOUDAN", "SOUDAN DU SUD", "SRI LANKA", "SURINAME", "SYRIE"
"TADJIKISTAN", "TAIWAN", "TANZANIE", "TCHAD", "THAILANDE", "TIMOR ORIENTAL"
"TOGO", "TOKELAU", "TONGA", "TRINITE-ET-TOBAGO", "TURKMENISTAN", "TURQUIE"
"TUVALU", "UKRAINE", "VANUATU", "VATICAN", "VENEZUELA", "ILES VIERGES DES ETATS UNIS"
"VIETNAM", "YEMEN", "ZAMBIE", "ZIMBABWE"
```

### 3.4 `NC` / `OUI` / `NON`

Enum of `memeDomaineActivite`, `infraPerenne` and `activiteSubstentielle.activiteSubstentielle`. Wire order in the
contract: `NC`, `OUI`, `NON`. PDF wording: "Oui / Non / Non Concerné" (`NC` = *Non Concerné*).

### 3.5 `critereDActivite` — schema `CritereDActivite`

`CA`, `TempsTravail`, `Prestations`, `Remuneration` (mixed case, verbatim).

### 3.6 `nature` (activity) — schema `Nature`

`TRAVAILLEUR_INDEPENDANT`, `SALARIE`, `FONCTIONNAIRE`, `MEMBRE_D_EQUIPAGE`, `AGENT_CONTRACTUEL`, `SALARIE_INTERMITTENT`,
`DEMANDEUR_EMPLOI`, `ARTISTE_AUTEUR`, `SALARIE_NON_SALARIE`, `NON_SALARIE`, `PENSIONNE`, `ETUDIANT`, `AUTRE`, `TOUS`
(14 values). `TOUS` ("all") is unusual for the nature of one activity; meaning not published. `AUTRE` goes with
`natureAutre`.

### 3.7 Defined but not referenced by any operation

`components.schemas` contains 30 schemas; those below (or their fields) are **not used** by any operation and carry no
published meaning. They must not drive the model (kept here for completeness and for a future contract diff):

| Schema | Content |
|---|---|
| `Etat` | `EN_COURS`, `VALIDE`, `INVALIDE` |
| `Fonction` | `TI`, `EMPLOYE`, `RESPONSABLE`, `DIRECTEUR` |
| `EnteteDemande` | `personne` (`Personne`), `paysDestinataire` (array of `Pays`), `dateDebut`, `dateFin` (`DateJour`), `typeDemande` (`pattern: DETACHEMENT_SIMPLE`) — hints that other request types may exist |
| `DetachementSalarie` | `dateDebut`, `dateFin` (`DateJour`) |
| `Erreur` | the `code` / `message` / `description` envelope (§1.3), not referenced (responses use `components.responses`) |
| `DateHeure` | `date-time`, "<yyyy-MM-dd'T'HH:mm:ssZZZZ>" (the `Enrolement` dates re-declare it inline) |
| `DetachementSimplifie`, `DemandeDetachementSimplifiee` | duplicates of the inline `declaration` / `demande` schemas |

Also declared and unused: the global tag `GrandDeclarant`; `components.responses` `FunctionalError`, `UnauthorizedError`,
`NotFound`, `InternalError`, `Enrolement` (the operations repeat the same schemas inline).

### 3.8 Formats

| Name | Format |
|---|---|
| Date (`dateDebut`, `dateFin`, `dateNaissance`) | `yyyy-MM-dd` (ISO 8601 extended date), e.g. `2017-12-31` |
| Date-time (`dateDemande`, `dateMiseAJour`) | `yyyy-MM-dd'T'HH:mm:ssZZZZ` per the description (ISO 8601); the time-zone notation (`Z` vs `+01:00` vs `+0100`) is **not published** |
| Identifiers | `idEnrolement`, path `id`, response `id`: UUID |
| SIREN / NIC | 9 / 5 characters (patterns: §2.5) |
| NIR | 13 characters without key (`cleNir`: 2 digits, separate) |
| Phone | 7–30 characters, no pattern |

---

## 4. Package decisions derived from the specification

1. **`SpecVersion::CURRENT` = `1.0.0`** (PDF file name), although the Swagger is 1.0.8 (see header). Re-check both on every
   contract diff; the Swagger wins for shapes and codes, the PDF for functional meaning.
2. **Contract is authoritative for paths.** Use `/declaration/detachement/UE` (Swagger + PDF diagram), not the PDF §4.5
   URL. TBD: confirm on the sandbox (§5).
3. **Statuses of `GET /certificat/{id}`** ([ADR 0003](adr/0003-certificate-result.md)). The result is a sum type
   `CertificateAvailable` (200, PDF streamed), `CertificateInProgress` (204), `CertificateRefusedOrUnknown` (404).
   **400 is "in progress" by default**: a package choice, not a spec rule (the PDF says so, the Swagger does not), settable
   with `certificate.bad_request_as_in_progress: false` (bundle) or `GatewayOptions::withCertificateBadRequestAsInProgress()`,
   in which case a 400 is an `UrssafRequestRejected`. The default stays until the sandbox shows what a 400 means (§5.5);
   changing it comes with a changelog entry. The raw HTTP status and the parsed error body are always kept.
4. **Enrolment lifecycle.** Domain `Enrolment` with `EnrolmentStatus` (`ACCEPTE`, `ENCOURS`, `REFUSE`, `SUPPRIME`,
   unknown values preserved raw). `Category` is an enum with a raw fallback; the PDF considers `categorie` mandatory so
   the package **requires it** on creation even though the Swagger does not.
5. **Declaration only after acceptance** ([ADR 0009](adr/0009-enrolments-and-pagination.md)). The server rule stays (403);
   the package adds an **optional guard**, off by default: `SubmitPosting::afterCheckingEnrolment()` reads the enrolment
   first and throws `EnrolmentNotAccepted` unless its status is `ACCEPTE` and its SIREN equals the `referenceEntreprise`
   SIREN (the NIC is not compared). `SubmitPosting::of()` never checks.
6. **Pagination** ([ADR 0009](adr/0009-enrolments-and-pagination.md)). `listEnrolments(offset, limit, sort?)` serialises
   `offset` / `limit` / `sort` as flat query parameters; `offset` ≥ 0 and `limit` ≥ 1 are always sent, no maximum is
   enforced, `sort` is sent verbatim. `ListEnrolments::firstPage()` and `urssaf:mi:enrolment:list` use **offset 0, limit
   20: a package choice**, not a server default (none is published, §5.20). No total count is returned, so iteration
   continues until a short page (`EnrolmentPage::isLastPage()` / `nextOffset()`).
7. **Country values.** `Country` value object / enum over the 226 values (including `""` rejected locally); mapping to
   ISO codes is **not** published and is not done by the package.
8. **Dates.** `date` fields as `DateTimeImmutable` formatted `Y-m-d`; `dateDemande` / `dateMiseAJour` parsed as ISO 8601
   with offset, tolerant of `Z`.
9. **Tri-state answers.** `NC` / `OUI` / `NON` modelled as an enum `Answer` (`NotApplicable`, `Yes`, `No`); never mapped
   to a boolean.
10. **Conditional rules are not guessed** ([ADR 0008](adr/0008-local-validation.md)). Local validation covers only what
    the Swagger states (required keys, lengths, patterns, enums, `nombreJours` 1–31, `nombreJours` required when
    `teletravail` is true; `adresse` is omitted from the wire, without a violation, when `adresseConnue` is false or
    `adresseHabituelle` is true). Every violation is reported at once (`ValidationFailed`). `declaration` and `declarant`
    are typed as required although the Swagger leaves them optional (§5.13). The PDF-only telework rule (`accordSalarie`
    and `pays` required when teleworking, §5.7) is **opt-in** (`PostingValidationPolicy::withPdfTeleworkRules()`, bundle
    `validation.pdf_telework_rules`). Employee / self-employed rules, NIR key, SIREN Luhn, `dateDebut` ≤ `dateFin` and the
    other rules the sources do not state are left to the server until published (§5.22).
11. **Error parsing.** Accept an object, a list of objects, or an empty body (401 / 500 of the declaration). Keep `code`,
    `message`, `description` raw; no error code enum can be built (none published).
12. **Personal data.** `nir`, `cleNir`, names, birth date and place, addresses, email, phone numbers are personal data:
    never logged, never put in exception messages or fixtures.
13. **PDF download.** `GET /certificat/{id}` is binary: the HTTP layer does not decode it as JSON; the body is exposed as a
    `BinaryStream` with the `Content-Type` and `Content-Disposition` when present ([ADR 0005](adr/0005-files.md)). The size
    is not published and not capped by the package: set a response size limit on your PSR-18 client.
14. **Quota** ([ADR 0000](adr/0000-reference-architecture.md) row 14). A 429 is an `UrssafUnavailable`
    (`isQuotaExceeded()`, `Retry-After` kept when present) that is safe to repeat; the package itself never retries. The
    200 requests/minute figure is global to the API and informational: the client-side limiter (`RateLimiter` port,
    `SlidingWindowRateLimiter`, per tenant and operation, in the current process) is **opt-in** and off by default
    (`rate_limiter.enabled`, `GatewayOptions::withRateLimiter()`); the sandbox quota is not published.

---

## 5. Open points

None of these points has been confirmed against the real sandbox yet (opt-in contract tests exist, `make test-contract`).
Where the package had to choose to work around a point, the choice is recorded in §4 and in the ADR it names; **the
point stays open until URSSAF or the sandbox settles it**, and the choice is revisited then:

| Point | Package choice while open | Where |
|---|---|---|
| 2 declaration path | The Swagger path `/declaration/detachement/UE` | §4.2 |
| 3 `categorie` | Required by the package on creation | §4.4 |
| 4, 20 pagination | Flat `offset` / `limit` / `sort`; `firstPage()` and the console command default to offset 0, limit 20 | §4.6, ADR 0009 |
| 5 certificate statuses | 204 in progress, 404 refused or unknown, 400 in progress by default (configurable) | §4.3, ADR 0003 |
| 7 telework | Swagger rule by default, PDF rule opt-in | §4.10, ADR 0008 |
| 9 quota | Informational; opt-in client-side limiter | §4.14 |
| 10 token host | Overridable `token_uri`; sandbox host assumed `https://api-edi.urssaf.fr/api/oauth/v1/token` | ADR 0002 |
| 13 required-ness | `declaration` and `declarant` typed as required | §4.10 |
| 22 per-profile rules | Not validated locally, left to the server | §4.10 |

Swagger / PDF discrepancies (also noted in §2):

1. **Version gap:** Swagger v1.0.8 vs PDF v1.0.0 (2025-10-22), the PDF has no later revision; changes between 1.0.0 and 1.0.8
   are unknown.
2. **Declaration path:** PDF §4.5 `/mobilite/detachement/UE` vs Swagger and PDF diagram `/declaration/detachement/UE`.
3. **`categorie`** required in PDF §3.1.1, optional in the Swagger (`required: [siren]`).
4. **`GET /enrolements` parameters:** flat `offset` / `limit` (PDF) vs object `pageable` (+ `sort`) (Swagger); PDF title duplicates
   §4.3; "actifs ou en attente" vs a four-value `status`; the 200 bodies of PDF §4.3 / §4.4 are empty.
5. **Certificate statuses:** Swagger 204 = in progress / 404 = not found; PDF 400 = in progress / 404 = refused; the PDF 200
   text is copied from the deletion; the PDF says the response gives the dossier status, no body is specified.
6. **Status codes only in one source:** 429 everywhere and 403 on the declaration in the PDF only; 404 on `POST` and `GET`
   `/enrolements`, and 400 on `GET` / `DELETE /enrolements/{id}` in the Swagger only.
7. **Telework:** PDF makes `accordSalarie`, `pays` and `nombreJours` mandatory when teleworking; the Swagger says so for
   `nombreJours` only and requires none of them in the schema.
8. **PDF gaps:** §4.5.2 references broken, §4.5.2.3 empty (per-profile mandatory fields not published); glossary of `TIAE` /
   `TIPM` incomplete, `AUTRE` missing; `prenom` described as "Nom de naissance"; PDF `activitesDeclarees` described as
   one block while `activitesActuelles` is a list (consistent with the Swagger, not stated by the PDF).
9. **Quota:** 200 requests/minute in production (PDF only); sandbox quota not published.

Contract-internal oddities:

10. **Security scheme** declared `implicit` with the token URL as `authorizationUrl`, while the text and the PDF say client
    credentials; `mobi-sb.json` still points to the production token URL: **sandbox token host not published** (to confirm,
    assumed `https://api-edi.urssaf.fr/api/oauth/v1/token`).
11. **Error envelope:** shape differs per operation (array on the declaration 400, object elsewhere, empty 401 / 500 on the
    declaration); `code` / `description` descriptions look swapped; no code catalogue, no example.
12. **Declaration 200:** array of `{id}`; link `id` → `/certificat/{id}` ("numéro de dossier") inferred; cardinality of the array
    unexplained.
13. **Required-ness:** `declaration`, `declarant`, `demande`, `personne` are not required by the schema (only their children
    are); `activitesDeclarees` is an object, `activitesActuelles` an array.
14. **`siren` / `nic` constraints** differ: enrolment `siren` / `nic` fixed length without pattern; declaration `siren`
    `[0-9A-Z]{9}`, `nic` max 5 without minimum.
15. **Enums:** `Pays` includes `""` and aliases; `type` is `["AUTRE"]` only; `Nature` includes `TOUS`; `Etat`, `Fonction`,
    `EnteteDemande` (`typeDemande`), `DetachementSalarie` are unreferenced; tag `GrandDeclarant` unused (operations use
    `partenaire` and `declarations`); the description says "EEE" while the path and PDF say "UE" (country list is global).
16. **No header** declared (no request id, no application id).
17. `prd` vs `sb` contracts differ **only** by `servers[0].url`.

Missing information:

18. **No official payload example** (request or response, success or error) in the Swagger or the PDF: no wire fixture;
    sandbox recording required (without personal data).
19. Error codes and messages (none published); `Retry-After` and rate-limit headers.
20. Pagination: default and maximum `limit`, ordering, `sort` syntax, behaviour without parameter, whether `REFUSE` /
    `SUPPRIME` enrolments are listed.
21. Certificate: file name / `Content-Disposition`, size, behaviour after expiry; what is stored under `id` for refused cases.
22. Employee vs self-employed mandatory fields, and rules between `activitesDeclarees`, `activitesActuelles` and `nature`
    (`TOUS`, `SALARIE_NON_SALARIE`, …).
23. Formats not specified: phone numbers, `adresse.codePostal` per country, date-time offset notation, `id` semantics of
    the declaration.
24. Sandbox: availability, test SIREN / enrolment auto-validation, test data (the enrolment normally needs a human
    validation on urssaf.fr).

Found while implementing (not in the sources, so nothing is assumed):

25. **Repeated deletion:** the answer of `DELETE /enrolements/{id}` on an enrolment already deleted (404, 400 or 200) is not
    published. The package never resends a delete by itself and tells the caller to confirm with `GetEnrolment` (404 or
    status `SUPPRIME`, [ADR 0004](adr/0004-unknown-outcome-and-retry.md)).
26. **`Retry-After` and 429 body:** neither the header nor the body of a 429 is documented (§1.1). The package reads
    `Retry-After` only as a number of seconds and ignores any other form ([ADR 0002](adr/0002-http-client-adaptations.md)).
27. **Idempotency:** no operation has an idempotency key and none lists postings, so a posting whose answer is lost
    cannot be reconciled through the API (`OutcomeUnknown`, [ADR 0004](adr/0004-unknown-outcome-and-retry.md)).
28. **Request id header:** no header is published (§5.16). The package sends none by default; `headers.request_id`
    (bundle) names an optional header carrying a UUID per attempt.
