# AI Development Guidelines for `jweiland/jobfair2`

This file defines technical context and constraints for Claude (or any AI assistant) working on this
repository. It is scoped to this single extension. If this repository is ever consumed as part of a larger
TYPO3 project that ships its own `CLAUDE.md`, that project-level file takes precedence for anything not
covered here.

## 1. What this extension is

`jobfair2` is a standalone TYPO3 extension that renders a searchable job listing ("job fair") on the
frontend. It combines three building blocks:

- **Job data** (`tx_jobfair2_domain_model_job`) - title, description, dates, reference number, PDF files, etc.
- **Location data** - reuses `tt_address` (via `FriendsOfTYPO3\TtAddress`) and extends it with a relation to
  `EXT:maps2` (`tx_maps2_uid`) so job locations can be shown on a Google Map.
  Additionally, an `import_key` column is added to `tt_address` to make imported addresses idempotent.
- **Automated import** - a console command pulls job postings from external XML API endpoints (currently
  several endpoints of "MHM HR") and writes/updates/deletes `tx_jobfair2_domain_model_job` and `tt_address`
  records via TYPO3's `DataHandler`.

## 2. Origin and migration history - read this before renaming anything

This extension was extracted from another (older) TYPO3 project where it was pinned to a fixed version and
lived alongside project-specific extensions. It was copied as-is into this repository to become an
independent, Composer-installable package on its own GitHub repository
(`git@github.com:jweiland-net/jobfair2.git`).

**Goal of the ongoing work:** decouple `jobfair2` from that older project so it can live and evolve
independently, *without breaking the older project*, which will keep using this extension too.

Because of that, the following identifiers are effectively a public API/contract and must **not** be
renamed or restructured without explicit confirmation, since the older project relies on them staying
identical:

- Extension key `jobfair2` and namespace `JWeiland\Jobfair2`.
- Database table names (`tx_jobfair2_domain_model_job`, `tx_jobfair2_domain_model_jobarea`,
  `tx_jobfair2_domain_model_jobtype`) and their column names.
- The `tt_address` column `import_key` (used to match imported addresses on re-import).
- Extbase plugin signature `Jobfair2` / `Jobfair` and the `CType` `jobfair2_jobfair`
  (see `Classes/Updates/JWeilandJobfair2CTypeMigration.php` - a former `list_type` was migrated to this
  `CType`; both old and new project must resolve to the same value).
- TypoScript constants under `jobfair2.*` (`Configuration/Sets/Jobfair/settings.definitions.yaml`).
- The CLI command name `jobfair:import:jobs:mhm`.

**Known leftover coupling to the old project** (needs a decision before/while decoupling):

- `Configuration/Sets/Jobfair/setup.typoscript` includes a CSS file from a project-specific extension:
  `EXT:jw5160001drsportal/Resources/Public/Css/jobfair.css`. This extension key does not exist in this
  standalone package and will not resolve here. Do not remove this line silently - the old project may
  depend on it being present in this exact Site Set. Ask before touching it.

When in doubt, prefer additive, backward-compatible changes over renames, and flag anything that looks like
a breaking change for the old project explicitly.

## 3. Tech stack

- **TYPO3:** 13.4 LTS only (see `composer.json` / `ext_emconf.php` constraints).
- **PHP:** 8.2+, constructor property promotion used throughout.
- **Hard dependencies:** `jweiland/maps2`, `friendsoftypo3/tt-address`, `bithost-gmbh/pdfviewhelpers`
  (PDF export uses `EXT:pdfviewhelpers`, see `Configuration/Sets/Pdf/`).
- **Site Sets:** this extension ships two TYPO3 Site Sets (`jweiland/jobfair2` main set,
  `jweiland/jobfair2-pdf` for the PDF variant) instead of classic static TypoScript templates.
- **State:** `ext_emconf.php` declares `'state' => 'alpha'`, version `0.0.1`. Treat the extension as not yet
  API-stable.

## 4. Architecture overview

```
Command (CLI)                       Frontend
  ImportJobsMhm                       JobfairController (list / search / detail)
       |                                   |
       v                                   v
  ImportService  <---uses--- ApiModelInterface (tagged 'api.model', DI-autowired, shared:false)
       |                       - (no implementation ships in this public package, see below)
       |
       +-- XmlClient (Guzzle via RequestFactory) -> fetches XML, wraps nodes in JobModel/LocationModel
       +-- JobService / JobAreaService / JobTypeService / TtAddressService (raw QueryBuilder, no Extbase)
       +-- DataHandler (via DataHandlerTrait) -> writes tx_jobfair2_domain_model_job + tt_address
```

**No `ApiModelInterface` implementation ships with this package.** The former MHM-specific
adapter classes (`MhmDrsApiModel`, `MhmBischoeflichesJugendamtApiModel`, `MhmOrdinariatHaVApiModel`,
`MhmOrdinariatRottenburgApiModel`) were removed from `Classes/ApiModel/` on purpose, because they
contained real, project-specific API endpoints and mapping details from the original (non-public) project
and must not live in this public GitHub repository. Only the generic building blocks
(`AbstractModel`, `ApiMapping`, `ApiModelInterface`, `ApiModelTrait`, `JobModel`, `LocationModel`) remain.
As shipped, the import command therefore has nothing to import until a consuming project adds its own
tagged `ApiModelInterface` class - see
`Documentation/AdministratorManual/Api/Index.rst` for a full, sanitized walkthrough of how to build and
register one (with a genericized copy of one of the removed classes as the example). Do not re-add the
concrete MHM classes here; they belong in a private, project-specific extension.

Key points:

- **Import path deliberately avoids Extbase persistence.** All lookup/write services
  (`JobService`, `JobAreaService`, `JobTypeService`, `TtAddressService`) use `ConnectionPool`/`QueryBuilder`
  directly and write through TYPO3's `DataHandler`, not through repositories. This is intentional (see
  docblocks: "Do not migrate content to Extbase Repository as this service will be called via Command") -
  it allows other extensions to hook into `DataHandler` (e.g. Solr indexing) on every import.
- **Frontend path uses Extbase/Fluid as usual.** `JobfairController` + `JobRepository` (Extbase) serve the
  `list`, `search`, and `detail` actions. `JobRepository::findBySearchCriteria()` implements the actual
  search logic (job area, job type, zip/city).
- **`ApiModelInterface` implementations are pluggable data-source adapters.** Each class only defines a
  `NAME`, `API_ENDPOINT`, and a `MAPPING` array (API XPath -> DB column, with optional date-cast and
  prefix). New job sources are added by creating a new class tagged `api.model` via
  `#[Autoconfigure(tags: ['api.model'], shared: false)]` - no changes to `ImportService` are needed.
  `ApiMapping`/`ApiModelTrait`/`AbstractModel` provide the shared plumbing (XPath lookup with type casting).
- **Vacancy IDs are namespaced per source.** `vacancy_id` is prefixed with the API model's `NAME`
  (e.g. `drs_12345`) to avoid collisions between different job sources sharing one storage folder.
- **Import is idempotent and self-cleaning.** `ImportService::import()` snapshots existing jobs for the
  storage PID, marks each as "seen" while importing, and deletes (via `DataHandler` cmdmap) everything that
  was not seen in this run - i.e. jobs removed from the source API are removed locally too.
- **`AddressSearchMiddleware`** answers city/zip autocomplete requests (custom header
  `jobfair2-address-search`) against `tt_address` for the frontend search form; registered to run after
  `typo3/cms-frontend/authentication` because it needs the Frontend restriction context.
- **`JobPoiCollectionViewHelper`** bridges `Job` -> `tt_address` -> `maps2` `PoiCollection` so a job list can
  be rendered as map markers.
- **`Address` domain model** extends `FriendsOfTYPO3\TtAddress\Domain\Model\Address` purely to add the
  `maps2` relation (`txMaps2Uid`). Keep this model in sync if `tt_address`'s own model changes upstream.

## 5. Coding conventions actually used here

- `declare(strict_types=1);` in every PHP file, one blank line after the opening tag.
- Constructor property promotion everywhere; `readonly class` for stateless services
  (`ImportService`, `JobService`, `TtAddressService`, `ApiMapping`).
- Symfony DI attributes (`#[Autoconfigure(...)]`, `#[AsCommand(...)]`) are used directly on classes instead
  of (or in addition to) `Services.yaml` entries. `Services.yaml` still explicitly marks the API model
  classes and `Domain/Model/*` exclusions.
- Small reusable traits instead of a shared abstract base class: `ConnectionPoolTrait` (QueryBuilder with
  frontend restrictions applied), `DataHandlerTrait` (DataHandler instance), `ApiModelTrait` (interface
  boilerplate for API model classes).
- Errors from `DataHandler` are logged via the injected PSR-3 `LoggerInterface` (channel configured in
  `ext_localconf.php` under `TYPO3_CONF_VARS.LOG.JWeiland.Jobfair2`), not thrown further - a failed record
  is skipped, not fatal for the whole import run.

Follow standard TYPO3/PER-CS conventions for anything not called out above: alphabetically sorted `use`
statements, no FQCN in the method body (except global-namespace classes like `\DateTime`, `\Exception`,
`\SimpleXMLElement`, used with a leading backslash as already done in this codebase), explicit `: void`
return types, `private` visibility by default for new Events/Listeners/Middlewares/Commands unless XClass
support is explicitly required.

## 6. Testing & QA - currently missing

Unlike other `jweiland/*` extensions, this repository currently has **no** `Tests/` directory, no
`Build/Scripts/runTests.sh`, no PHPStan/php-cs-fixer configuration, and no `.gitignore`. If asked to add
quality tooling, mirror the conventions used across other `jweiland/*` extensions (PHPUnit via
`typo3/cms-testing-framework`, `Tests/Unit/` + `Tests/Functional/`, config under `Build/`) rather than
inventing a new structure - but do not add this speculatively unless requested.

## 7. Operating the import (for context, not to be changed lightly)

```bash
vendor/bin/typo3 jobfair:import:jobs:mhm <storagePid>
```

- `<storagePid>` is the page ID where imported `tx_jobfair2_domain_model_job` and `tt_address` records are
  stored. Typically triggered by the TYPO3 Scheduler.
- The command bootstraps backend authentication (`Bootstrap::initializeBackendAuthentication()`) because
  `DataHandler` requires a backend user context.
- Adding a new job source = adding a new `ApiModelInterface` implementation tagged `api.model`; it will be
  picked up automatically via `ImportService`'s tagged iterator (`Configuration/Services.yaml`:
  `$apiModels: !tagged api.model`).
