# AI Development Guidelines for `jweiland/jobboard`

This file defines technical context and constraints for Claude (or any AI assistant) working on this
repository. It is scoped to this single extension. If this repository is ever consumed as part of a larger
TYPO3 project that ships its own `CLAUDE.md`, that project-level file takes precedence for anything not
covered here.

## 1. What this extension is

`jobboard` is a standalone TYPO3 extension that renders a searchable job listing ("job board") on the frontend. It
combines three building blocks:

- **Job data** (`tx_jobboard_domain_model_job`) - title, description, dates, reference number, salary
  information, contact person, media attachments, and relations to several taxonomy tables (job area, job
  type, job role, contract type, tender type, benefits). See "3. Domain model" below for the full shape.
- **Location data** - reuses `tt_address` (via `FriendsOfTYPO3\TtAddress`) and extends it with a relation to
  `EXT:maps2` (`tx_maps2_uid`) so job locations can be shown on a Google Map.
  Additionally, an `import_key` column is added to `tt_address` to make imported addresses idempotent.
- **Automated import** - a console command pulls job postings from external XML API endpoints (currently
  several endpoints of "MHM HR") and writes/updates/deletes `tx_jobboard_domain_model_job` and `tt_address`
  records via TYPO3's `DataHandler`.

## 2. Tech stack

- **TYPO3:** 13.4 LTS only (see `composer.json` / `ext_emconf.php` constraints).
- **PHP:** 8.2+, constructor property promotion used throughout.
- **Hard dependencies:** `jweiland/maps2`, `friendsoftypo3/tt-address`, `bithost-gmbh/pdfviewhelpers`
  (PDF export uses `EXT:pdfviewhelpers`, see `Configuration/Sets/Pdf/`).
- **Site Sets:** this extension ships two TYPO3 Site Sets (`jweiland/jobboard` main set,
  `jweiland/jobboard-pdf` for the PDF variant) instead of classic static TypoScript templates.
- **State:** `ext_emconf.php` declares `'state' => 'alpha'`, version `0.0.1`. Treat the extension as not yet
  API-stable.

## 3. Domain model

`Job` is the central entity. Its properties (and the matching Fluid template sections in
`Resources/Private/Templates/Jobboard/Detail.html`) are grouped the same way the TCA groups them into
palettes/tabs: job details, job description, import, address, job information, salary, benefit, employer,
contact person, application information, media, relations. `Detail.html` has one `f:section` per group
(`renderDetails`, `renderDescription`, `renderSalary`, `renderBenefits`, ...), each rendered from `main` via
`f:render section="..." arguments="{job: job}"` - keep new properties/output grouped the same way instead of
adding ad-hoc fields elsewhere in the template.

- **Taxonomy/lookup tables**: `JobArea`, `JobType`, `JobRole`, `ContractType`, `TenderType`, `Benefit` are
  all minimal one-field (`title`) entities, each with their own TCA table. `Job` references `JobArea`,
  `JobType`, `JobRole`, `ContractType`, and `TenderType` as single (`selectSingle`) relations, but
  `benefits` is a genuine many-to-many relation (`selectMultipleSideBySide` + MM table
  `tx_jobboard_job_benefit_mm`) - a job can have several benefits, unlike the other, singular, lookup
  relations. `Benefit` additionally has `color` (TYPO3's native colorpicker, `type=input` /
  `renderType=color`, with a `valuePicker` offering a fixed set of 6 pastel colors as quick-pick swatches -
  editors can still choose any color freely) and a plain-text `description`. `ColorElement` does not
  resolve `LLL:` references for `valuePicker.items` labels (unlike `InputTextElement`/`NumberElement`/
  `EmailElement`/`LinkElement`), so those swatch names are intentionally untranslated plain text.
- **Salary**: `Job::salaryMode` (TCA `type` field) picks between two shapes: `0` = reference a
  `SalaryGrade` (itself either a flat `flatAmount` or a set of `SalaryStep` children, optionally grouped
  under a `SalaryTable`), or `1` = free-text `salaryMin`/`salaryMax`. Use `Job::getSalaryRangeMin()`,
  `getSalaryRangeMax()`, `getHasSalaryRange()`, and `getHasSalaryInformation()` to read the effective salary
  regardless of mode - don't branch on `salaryMode` outside of `Job` itself, these methods already do it.
  Known gap: `SalaryGrade::$salaryTable` is never hydrated by Extbase, because `salary_table` is not
  declared as a relation column in `tx_jobboard_domain_model_salarygrade`'s own TCA - it only exists
  implicitly as the inverse `foreign_field` of `salarytable.salary_grades`.
- **File relations** (`employerLogo`, `headerLogo`, `tenderFile`, `pdfFiles` on `Job`; `image` on
  `Benefit`) are all `ObjectStorage<FileReference>`, never a single `FileReference` - none of the underlying
  TCA `type=file` columns actually restrict `maxitems` to 1 (even `Benefit::image`, which IS limited to 1
  via `maxitems`, still uses `ObjectStorage` for consistency with the rest of the model). Each has
  `add`/`remove` methods in addition to `get`/`set` (e.g. `addPdfFile()`/`removePdfFile()`, singularized
  from the plural property name where applicable).
- **Boolean getters and Fluid**: if a property name already starts with `is`/`has` (e.g. `isImport`,
  `isInternal`, `hasSteps`), Fluid's `{job.isImport}` still resolves via a `get`-prefixed method
  (`getIsImport()`), never a bare `isImport()`/`hasSteps()`. A bare method is invisible to the Fluid/Extbase
  object accessor and either silently resolves to `null` or throws "Cannot access protected property" once
  it falls through to direct property access. Every boolean getter in this codebase is named accordingly
  (`getIsImport()`, `getIsInternal()`, `getHasSteps()`) - keep this in mind for any new boolean property,
  it will not surface as a PHP error until the property is actually used from a Fluid template.
- **`select` fields with an `MM` table keep their local column** (e.g. `Job::$benefits`'s underlying
  `benefits` int column) - TYPO3's `DataHandler` writes the relation *count* into it on every save, it is
  not dead weight to be dropped. Both the MM table and this local counter column are plain, TCA-derivable
  int schemas and therefore never need an `ext_tables.sql` entry.

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

## 5. Upgrade wizards

- **`JobfairToJobboardMigration`** copies rows from the pre-rename `tx_jobfair2_domain_model_*` tables into
  the current `tx_jobboard_domain_model_*` tables, preserving every row's original `uid` (so foreign keys
  between these tables, and client-side "remembered jobs" in `localStorage`, stay valid). It copies columns
  generically by diffing the *actual* schema of the old and new table (`getCommonColumns()`), not via a
  hardcoded field list - adding or removing a plain `Job` column normally needs no wizard change. FAL
  fields are the exception: `sys_file_reference.tablenames` is rewritten only for the columns listed in
  `JOB_FAL_FIELDS` (`employer_logo`, `header_logo`, `tender_file`, `pdf_files`), so a *new* FAL column that
  already existed under the old `tx_jobfair2_*` table name must be added to that constant, or its file
  relations will silently point at the old, no-longer-existing table after migration.
- **`JobfairToJobboardCTypeMigration`** migrates `tt_content.CType` and `be_groups` explicit-allow/deny
  permissions from `jobfair2_jobfair` to `jobboard_jobboard`. Unrelated to FAL/domain data.

## 6. Coding conventions actually used here

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

## 7. Testing & QA

`Tests/Unit/` and `Tests/Functional/` exist (PHPUnit via `typo3/cms-testing-framework`), as does
`Build/Scripts/runTests.sh` and a php-cs-fixer configuration (`Build/cgl/config.php`). There is currently
**no** PHPStan configuration. If asked to add it, mirror the conventions used across other `jweiland/*`
extensions rather than inventing a new structure.

Unit tests for domain models follow one fixed pattern per property: `get<Prop>InitiallyReturns<Default>()` /
`set<Prop>Sets<Prop>()`, plus `add<Prop>Adds<Prop>()` / `remove<Prop>Removes<Prop>()` for `ObjectStorage`
properties. Properties/tests are ordered to match the TCA `showitem`/palette order of the table, not
alphabetically or by insertion order.

## 8. Operating the import (for context, not to be changed lightly)

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
