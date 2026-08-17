# AI Development Guidelines for `jweiland/jobfair2`

This file defines technical context and constraints for Claude (or any AI assistant) working on this
repository. It is scoped to this single extension. If this repository is ever consumed as part of a larger
TYPO3 project that ships its own `CLAUDE.md`, that project-level file takes precedence for anything not
covered here.

## 1. What this extension is

`jobfair2` (internally renamed to "Jobboard", see section 2) is a standalone TYPO3 extension that renders a
searchable job listing ("job board") on the frontend. It combines three building blocks:

- **Job data** (`tx_jobboard_domain_model_job`) - title, description, dates, reference number, PDF files, etc.
- **Location data** - reuses `tt_address` (via `FriendsOfTYPO3\TtAddress`) and extends it with a relation to
  `EXT:maps2` (`tx_maps2_uid`) so job locations can be shown on a Google Map.
  Additionally, an `import_key` column is added to `tt_address` to make imported addresses idempotent.
- **Automated import** - a console command pulls job postings from external XML API endpoints (currently
  several endpoints of "MHM HR") and writes/updates/deletes `tx_jobboard_domain_model_job` and `tt_address`
  records via TYPO3's `DataHandler`.

## 2. Origin and internal rename history - read this before renaming anything again

This extension was extracted from another (older) TYPO3 project where it was pinned to a fixed version and
lived alongside project-specific extensions. It was copied as-is into this repository to become an
independent, Composer-installable package on its own GitHub repository
(`git@github.com:jweiland-net/jobfair2.git`).

**The wording "Jobfair"/"job fair" was renamed to "Jobboard"/"job board" throughout the codebase**, because
customers found "job fair" confusing as a product name for a job listing/search feature. This rename
covers the PHP namespace (`JWeiland\Jobfair2` -> `JWeiland\Jobboard`), all database table names
(`tx_jobfair2_domain_model_*` -> `tx_jobboard_domain_model_*`), the CType/Extbase plugin signature
(`jobfair2_jobfair` -> `jobboard_jobboard`), the TypoScript constants namespace (`jobfair2.*` ->
`jobboard.*`), the Site Sets (`jweiland/jobfair2`/`jweiland/jobfair2-pdf` -> `jweiland/jobboard`/
`jweiland/jobboard-pdf`), the CLI command (`jobfair:import:jobs:mhm` -> `jobboard:import:jobs:mhm`), the
LOG channel (`JWeiland.Jobfair2` -> `JWeiland.Jobboard`), and all `EXT:jobfair2/...` path references
(-> `EXT:jobboard/...`), plus documentation, comments and JS/CSS identifiers.

**Deliberately excluded from this rename** (still `jobfair2`), because they are wired into the Composer
setup and a separate GitHub repository rename has not happened yet:

- The physical extension directory/Extension Key (`$_EXTKEY`, `ext_emconf.php`) - still `jobfair2`.
- The `composer.json` package `name` (`jweiland/jobfair2`) and its `support.issues`/`support.source` URLs.
- The `:composer:`/`:ext:` documentation roles and GitHub URLs in `Documentation/Links.rst` and
  `Documentation/guides.xml` - they still point at the real, not-yet-renamed repository.

Because the Extension Key stays `jobfair2` while `composer.json`'s `extra.typo3/cms.extension-key` was
already changed to `jobboard` (and all `EXT:jobboard/...` path references assume that), **this extension is
intentionally not runnable until the physical directory/repository is renamed to `jobboard` to match** -
that final step is a separate, manual action by the maintainer (rename the GitHub repository, then update
the path this package is checked out under). Do not "fix" this mismatch by reverting the Extension Key or
the `EXT:jobboard/...` paths back to `jobfair2`.

**Backwards compatibility for existing installations:** Because an older, still-active project
(`5160001-drs-jobs`) has data in the *old* `tx_jobfair2_domain_model_*` tables and CType `jobfair2_jobfair`,
two TYPO3 Upgrade Wizards ship in `Classes/Updates/` to migrate existing installations onto the renamed
structure without data loss:

- `JobfairToJobboardMigration` - copies all rows from the old `tx_jobfair2_domain_model_*` tables into the
  new `tx_jobboard_domain_model_*` tables (preserving `uid` values for foreign key/localStorage
  compatibility), including FAL file reference reassignment and a safe `salary_mode` default for legacy job
  records that predate the salary feature.
- `JobfairToJobboardCTypeMigration` - migrates `tt_content.CType` and backend user group permissions from
  `jobfair2_jobfair` to `jobboard_jobboard`.
- `JWeilandJobfair2CTypeMigration` (pre-existing) is kept as-is (only its namespace was updated) - it is a
  historical `list_type` -> `CType` migration for very old installations and must not be repurposed for the
  Jobfair2 -> Jobboard rename.

**Known leftover coupling to the old project** (unrelated to the rename, still present):

- `Configuration/Sets/Jobboard/setup.typoscript` includes a CSS file from a project-specific extension:
  `EXT:jw5160001drsportal/Resources/Public/Css/jobfair.css`. This extension key does not exist in this
  standalone package and will not resolve here. The filename `jobfair.css` is intentionally NOT renamed to
  `jobboard.css`, since it points at a real file in that foreign extension which was not part of this
  rename. Do not remove this line silently - the old project may depend on it being present in this exact
  Site Set. Ask before touching it.

When in doubt, prefer additive, backward-compatible changes over further renames, and flag anything that
looks like a breaking change for the old project explicitly.

## 3. Tech stack

- **TYPO3:** 13.4 LTS only (see `composer.json` / `ext_emconf.php` constraints).
- **PHP:** 8.2+, constructor property promotion used throughout.
- **Hard dependencies:** `jweiland/maps2`, `friendsoftypo3/tt-address`, `bithost-gmbh/pdfviewhelpers`
  (PDF export uses `EXT:pdfviewhelpers`, see `Configuration/Sets/Pdf/`).
- **Site Sets:** this extension ships two TYPO3 Site Sets (`jweiland/jobboard` main set,
  `jweiland/jobboard-pdf` for the PDF variant) instead of classic static TypoScript templates.
- **State:** `ext_emconf.php` declares `'state' => 'alpha'`, version `0.0.1`. Treat the extension as not yet
  API-stable.

## 4. Architecture overview

```
Command (CLI)                       Frontend
  ImportJobsMhm                       JobboardController (list / search / detail)
       |                                   |
       v                                   v
  ImportService  <---uses--- ApiModelInterface (tagged 'api.model', DI-autowired, shared:false)
       |                       - (no implementation ships in this public package, see below)
       |
       +-- XmlClient (Guzzle via RequestFactory) -> fetches XML, wraps nodes in JobModel/LocationModel
       +-- JobService / JobAreaService / JobTypeService / TtAddressService (raw QueryBuilder, no Extbase)
       +-- DataHandler (via DataHandlerTrait) -> writes tx_jobboard_domain_model_job + tt_address
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
- **Frontend path uses Extbase/Fluid as usual.** `JobboardController` + `JobRepository` (Extbase) serve the
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
  `jobboard-address-search`) against `tt_address` for the frontend search form; registered to run after
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
  `ext_localconf.php` under `TYPO3_CONF_VARS.LOG.JWeiland.Jobboard`), not thrown further - a failed record
  is skipped, not fatal for the whole import run.

Follow standard TYPO3/PER-CS conventions for anything not called out above: alphabetically sorted `use`
statements, no FQCN in the method body (except global-namespace classes like `\DateTime`, `\Exception`,
`\SimpleXMLElement`, used with a leading backslash as already done in this codebase), explicit `: void`
return types, `private` visibility by default for new Events/Listeners/Middlewares/Commands unless XClass
support is explicitly required.

## 6. Testing & QA

`Tests/Unit/` and `Tests/Functional/` exist (PHPUnit via `typo3/cms-testing-framework`), as does
`Build/Scripts/runTests.sh` and a php-cs-fixer configuration (`Build/cgl/config.php`). There is currently
**no** PHPStan configuration. If asked to add it, mirror the conventions used across other `jweiland/*`
extensions rather than inventing a new structure.

## 7. Operating the import (for context, not to be changed lightly)

```bash
vendor/bin/typo3 jobboard:import:jobs:mhm <storagePid>
```

- `<storagePid>` is the page ID where imported `tx_jobboard_domain_model_job` and `tt_address` records are
  stored. Typically triggered by the TYPO3 Scheduler.
- The command bootstraps backend authentication (`Bootstrap::initializeBackendAuthentication()`) because
  `DataHandler` requires a backend user context.
- Adding a new job source = adding a new `ApiModelInterface` implementation tagged `api.model`; it will be
  picked up automatically via `ImportService`'s tagged iterator (`Configuration/Services.yaml`:
  `$apiModels: !tagged api.model`).
