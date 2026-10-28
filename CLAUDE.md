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
- **Location data** - reuses `tt_address` (via `FriendsOfTYPO3\TtAddress`) and, only if the optional `EXT:maps2`
  is installed, extends it with a relation to `EXT:maps2` (`tx_maps2_uid`) so job locations can be shown on a map.
  Additionally, an `import_key` column is added to `tt_address` to make imported addresses idempotent.
- **Automated import** - a console command pulls job postings from external XML API endpoints (currently
  several endpoints of "MHM HR") and writes/updates/deletes `tx_jobboard_domain_model_job` and `tt_address`
  records via TYPO3's `DataHandler`.

## 2. Tech stack

- **TYPO3:** 13.4 LTS only (see `composer.json` / `ext_emconf.php` constraints).
- **PHP:** 8.2+, constructor property promotion used throughout.
- **Hard dependencies:** `friendsoftypo3/tt-address`, `bithost-gmbh/pdfviewhelpers`
  (PDF export uses `EXT:pdfviewhelpers`, see `Configuration/Sets/Pdf/`).
- **Optional:** `jweiland/maps2` (composer `suggest`; the set declares no maps2 dependency, integrators add a
  maps2 provider set like `jweiland/maps2-openstreetmap` or `jweiland/maps2-googlemaps` to their site). Every maps2 code path must
  stay guarded: the `tx_maps2_uid` TCA column is only added if maps2 is loaded, the map partial is only
  rendered if a job has a `PoiCollection`, and `Address::setTxMaps2Uid()` must not get a class type for its
  parameter (Extbase's `ClassSchema` reflects parameter class types and fails if the class does not exist).
  The property `Address::$txMaps2Uid` therefore needs its `@var PoiCollection|null` annotation: Extbase's
  PhpDocExtractor runs before the ReflectionExtractor, which would take the untyped setter (`?object`) - without
  the annotation the column map is `Relation::NONE` and the PoiCollection is never loaded.
  Code using maps2 classes in signatures must not be autowired without maps2: such classes are excluded in
  `Services.yaml` and registered conditionally in `Configuration/Services.php` (see "Map" in section 4).
  Functional tests run without maps2, except the map tests (`MapPoiCollectionServiceTest`, `SearchFormMapTest`,
  `RenderJobLocationInfoWindowContentEventListenerTest`).
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
  all minimal one-field (`title`) entities, each with their own TCA table. `Job` references `JobType`,
  `JobRole`, `ContractType`, and `TenderType` as single (`selectSingle`) relations, but `jobAreas` and
  `benefits` are genuine many-to-many relations (`selectMultipleSideBySide` + MM tables
  `tx_jobboard_job_jobarea_mm` / `tx_jobboard_job_benefit_mm`) - a job can have several job areas and
  benefits, unlike the other, singular, lookup relations. `Job::getJobArea()` only returns the first
  selected job area, for templates rendering a single one; the frontend search still filters by one job
  area and matches every job having it among its job areas (`contains`). `Benefit` additionally has `color` (TYPO3's native colorpicker, `type=input` /
  `renderType=color`, with a `valuePicker` offering a fixed set of 6 pastel colors as quick-pick swatches -
  editors can still choose any color freely) and a plain-text `description`. `ColorElement` does not
  resolve `LLL:` references for `valuePicker.items` labels (unlike `InputTextElement`/`NumberElement`/
  `EmailElement`/`LinkElement`), so those swatch names are intentionally untranslated plain text.
- **Salary**: `Job::salaryMode` (TCA `type` field) picks between two shapes: `0` = reference one or more
  `SalaryGrade`s (`Job::$salaryGrades`, `selectMultipleSideBySide` + MM table
  `tx_jobboard_job_salarygrade_mm`; each grade is either a flat `flatAmount` or a set of `SalaryStep`
  children, optionally grouped under a `SalaryTable`), or `1` = free-text `salaryMin`/`salaryMax`.
  `Job::getSalaryGrade()` only returns the first selected grade, for templates rendering a single grade.
  With several grades, the salary range spans all of them: `SalaryRange::fromSalaryGrades()` (pure
  `final readonly` value object in `Domain/Model/`) takes the lowest and highest positive amount of all
  steps/flat amounts of all grades (`SalaryGrade::getAmounts()`), exposed as `Job::getSalaryGradesRange()`. Use `Job::getSalaryRangeMin()`,
  `getSalaryRangeMax()`, `getHasSalaryRange()`, and `getHasSalaryInformation()` to read the effective salary
  regardless of mode - don't branch on `salaryMode` outside of `Job` itself, these methods already do it.
  `salary_mode`, `salary_min`, `salary_max` and `salary_grades` are `l10n_mode = exclude` +
  `l10n_display = defaultAsReadonly`: translations always carry the salary of the default language.
  Known gap: `SalaryGrade::$salaryTable` is never hydrated by Extbase, because `salary_table` is not
  declared as a relation column in `tx_jobboard_domain_model_salarygrade`'s own TCA - it only exists
  implicitly as the inverse `foreign_field` of `salarytable.salary_grades`.
  `SalaryGrade` has no `ctrl.sortby` on purpose, so its `default_sortby` (`title ASC`) is effective in the
  list module and in select fields. Its `sorting` column is still declared in `ext_tables.sql`, because
  `salarytable.salary_grades` uses it as `foreign_sortby` - don't drop it.
- **Effective salary (precomputed)**: `tx_jobboard_domain_model_job.effective_salary_min` /
  `effective_salary_max` (decimal 10,2) and `effective_salary_valid_until` (int, 0 = no upcoming change) are
  shown read-only as a control for editors (palette `effective_salary` at the end of the first tab, labels
  and a description in `locallang_db.xlf`; DataHandler still writes `readOnly` fields) with
  `l10n_mode = exclude`, so DataHandler copies them into the translations and the SQL filter works in every
  language. They are the single source for "has salary information" in the frontend
  (`JobRepository::buildAndConstraintForOpenJobs()`: min > 0 OR max > 0; `Job::getHasEffectiveSalaryInformation()`
  for the detail guard) - but only applied if the site requires it, see "Salary requirement" in section 4.
  They are always computed, whatever the setting. Rules (`EffectiveSalaryCalculator`, `final readonly`, behind
  `EffectiveSalaryCalculatorInterface`, plain QueryBuilder, 4 queries per batch): salary_mode 1 = `salary_min` to
  `salary_max` (or `salary_min`, if `salary_max` is 0); salary_mode 0 = lowest/highest positive amount of all *valid* selected
  grades (flat amount or valid steps). Valid = not deleted, not hidden, `starttime <= now`, and `endtime = 0`
  or `endtime > now + LOOK_AHEAD` (`EffectiveSalaryCalculatorInterface::LOOK_AHEAD`, 24 h: a grade/step
  expiring within 24 h counts as invalid already, so editors have time and a daily recalculation suffices;
  no look-ahead for starttime). `effective_salary_valid_until` = next not yet applied starttime (> now) or
  endtime (> now + LOOK_AHEAD) of the selected grades and all their steps. `EffectiveSalary` is the pure value
  object (`final readonly`, `Domain/Model/`) incl. `toFieldArray()` / `equals()` with DB precision.
  - `EffectiveSalaryUpdater` (`final readonly`, behind `EffectiveSalaryUpdaterInterface`) recalculates in
    chunks of 100 default-language jobs, compares with the stored values of the job *and* its translations
    and writes only changed jobs through DataHandler (never raw SQL: cache tags + `TCEMAIN.clearCacheCmd` of
    the storage folder must fire). The run bypasses record/page/language access checks (`admin = true`,
    `bypassAccessCheckForRecords`) because the values are derived, not an edit of the current user, and
    disables `sys_log` (`enableLogging = false`); `sys_history` is still written by DataHandler (no flag in
    TYPO3 13), which documents for editors why the effective salary changed. `EffectiveSalaryJobFinder` resolves affected jobs
    (translation -> default uid, step -> grade, salary table -> grades, grade -> jobs via MM; deleted
    sources included) and the due/all job lists.
  - `Hook\UpdateEffectiveSalaryHook` (`processDatamap_afterAllOperations` / `processCmdmap_afterFinish`,
    registered in `ext_localconf.php` under the key `jobboard`) handles jobs, salary grades, steps and
    tables, incl. NEW ids (`substNEWwithIDs`), copies/localizations (`copyMappingArray_merged`), and the
    import (`ImportService` saves via DataHandler, no change needed there). Live workspace only. The nested
    write's data map only contains the effective fields (+ `l10n_state`) and is ignored by the hook (no
    recursion); its cache clearing is processed by the outermost DataHandler instance.
  - `Command\UpdateEffectiveSalaryCommand` (`jobboard:salary:update`, schedulable, meant to run once a day,
    e.g. nightly) recalculates due jobs (`effective_salary_valid_until > 0 AND <= now + LOOK_AHEAD`);
    `--all` recalculates every job (needed once for existing data). Bootstraps backend authentication.
- **File relations** (`employerLogo`, `headerLogo`, `tenderFile`, `pdfFiles` on `Job`; `image` on
  `Benefit`) are all `ObjectStorage<FileReference>`, never a single `FileReference` - none of the underlying
  TCA `type=file` columns actually restrict `maxitems` to 1 (even `Benefit::image`, which IS limited to 1
  via `maxitems`, still uses `ObjectStorage` for consistency with the rest of the model). Each has
  `add`/`remove` methods in addition to `get`/`set` (e.g. `addPdfFile()`/`removePdfFile()`, singularized
  from the plural property name where applicable).
- **Boolean getters and Fluid**: if a property name already starts with `is`/`has` (e.g. `isImport`,
  `hasSteps`), Fluid's `{job.isImport}` still resolves via a `get`-prefixed method
  (`getIsImport()`), never a bare `isImport()`/`hasSteps()`. A bare method is invisible to the Fluid/Extbase
  object accessor and either silently resolves to `null` or throws "Cannot access protected property" once
  it falls through to direct property access. Every boolean getter in this codebase is named accordingly
  (`getIsImport()`, `getHasSteps()`) - keep this in mind for any new boolean property,
  it will not surface as a PHP error until the property is actually used from a Fluid template.
- **`select` fields with an `MM` table keep their local column** (e.g. `Job::$benefits`'s underlying
  `benefits` int column) - TYPO3's `DataHandler` writes the relation *count* into it on every save, it is
  not dead weight to be dropped. Both the MM table and this local counter column are plain, TCA-derivable
  int schemas and therefore never need an `ext_tables.sql` entry.

## 4. Architecture overview

```
Command (CLI)                       Frontend
  ImportJobsMhm                       JobboardController (list / search / detail / rememberedJobs)
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
  `list`, `search`, `detail` and `rememberedJobs` actions. `JobRepository::findBySettings()` /
  `findBySearch()` (job area, job role, job type, zip/city, search word) return an unexecuted
  `QueryResultInterface`; filtering (salary, open jobs), `maxEntries` (query limit) and pagination all happen
  in the database. Default ordering: `applicationDeadline` ASC, then `uid` ASC (unique, stable pages).
  `list` and `detail` are cached, `search` (POST criteria) and `rememberedJobs` (session) are not.
  Language pitfalls (site language 1 uses `fallbackType: fallback` = `OVERLAYS_MIXED`, so Extbase queries the
  *translated* job rows, which reference the default UIDs of their relations):
  - Never compare a relation with a domain object (`equals('jobType', $jobType)`):
    `Typo3DbQueryParser::createTypedNamedParameter()` binds `_localizedUid` of a translated object (e.g. 7 instead
    of 3) and nothing matches. `buildAndConstraintForRelations()` uses `in()` with the default UID plus the localized
    UID (`getUidsOfRelatedObject()`), job areas use `contains()` with the plain default UID.
  - `findByUids()` (default-language UIDs) keeps Extbase's normal language statement
    (`Typo3DbQueryParser::getLanguageStatement()`, same as `findBySettings()`) and matches
    `uid IN (uids) OR l10n_parent IN (uids)`: the language statement picks at most one row per job (translation
    or untranslated default row, depending on the fallback type), Extbase overlays it and the object's UID is the
    default UID again. So it returns exactly the given jobs the list shows in the current language: "fallback"
    (`OVERLAYS_MIXED`) translations + untranslated defaults, "strict" (`OVERLAYS_ON_WITH_FLOATING`, also
    `OVERLAYS_ON`) translations only, "free" (`OVERLAYS_OFF`) the rows of the language. Open-job/salary rules are
    checked on the selected (translated) row, like the list. Don't switch `respectSysLanguage` off or map overlay
    types again: that selected the default rows and returned untranslated jobs in strict/free. Empty/invalid UIDs
    match nothing (`l10n_parent IN (0)` would match every default row).
- **Salary requirement (site setting `jobboard.salary.required`, bool, default `true`)**: whether a job needs
  salary information (effective salary min or max > 0) to be listed, searched, shown (detail 404 otherwise),
  remembered, linked as related job and placed on the map (EU pay transparency directive 2023/970, not yet
  transposed in every country). Site setting only, category `jobboard.salary` - never TypoScript/FlexForm, so
  every entry point applies the same rule and one content element cannot override it.
  - Single reader: `Domain/Model/SalaryRequirement` (`final readonly`, `fromRequest()` reads the `site` request
    attribute; no site / `NullSite` / missing setting = required). `JobboardController::isSalaryRequired()` for
    Extbase, the PSR-7 request in `RememberJobMiddleware` and `JobLocationService`.
  - Only a plain bool goes further down: `JobRepository::findBySettings()` / `findBySearch()` / `findByUids()`
    (last parameter `$salaryRequired = true`) -> `buildAndConstraintForOpenJobs()`, and
    `OpenJobRestriction(int $today, bool $salaryRequired = true)` / `forToday(bool)`. Both add the salary
    constraint only if required - keep them in sync. `JobSearchOptionsService` inherits it from the action query.
  - `detailAction()` throws the 404 only if required. Related jobs: the controller assigns `relatedJobs`
    (`getRelatedJobsToLink()`, without salary-less jobs if required); `Detail.html` renders `relatedJobs`, never
    `job.relatedJobs`. Only the salary rule is applied there, expired related jobs are still linked.
  - Not affected: `LimitJobListCacheLifetimeEventListener` (looks at all jobs of the storage anyway), the
    effective salary hook/command/updater, the import. Templates guard salary output with
    `{job.hasSalaryInformation}`, so a job without salary renders no salary section and no JSON-LD `baseSalary`.
  - Functional tests that write `settings.yaml` must flush the `core` cache in `setUp()`: TYPO3 caches site
    settings by the hash of `config.yaml` only, otherwise settings leak between tests.
- **Salary format (one site setting `jobboard.salary.currency`, ISO 4217, default `EUR`)**: display only - stored
  salaries, `EffectiveSalary` and JSON-LD amounts (`f:format.json`, plain numbers) stay untouched.
  `Domain/Model/SalaryNumberFormat` (`final readonly`, `fromSettings()` + `format()`) = `\NumberFormatter::CURRENCY` with
  the full locale of the site language (`SiteLanguage::getLocale()->getName()`, e.g. `de-CH`, not only the language);
  separators, decimal places and currency position come from ICU. Deliberately no format modes, decimals, separator or
  position settings and no `currency` ViewHelper argument (reviewer decision, PR #49): number formats differ per
  country, and own formats belong into an overridden template with `f:format.number` + own language keys. Invalid
  currency falls back to `EUR`. Templates format every salary amount with `jobboard:format.salary`
  (`ViewHelpers/Format/SalaryViewHelper`, only the amount), never `f:format.currency`; JSON-LD takes the code from
  `jobboard:salaryCurrency()` (`ViewHelpers/SalaryCurrencyViewHelper`). Tests: the de-CH thousands separator differs
  between ICU versions (`’` vs `'`), normalize it; avoid fr (narrow NBSP changed in CLDR).
  The backend record titles (`UserFunc/SalaryStepTitleFormatter`) keep the backend user's locale.
- **Search options** (`jobAreas` incl. optional count `showJobAreaCount`, `jobRoles`, `jobTypes`,
  `jobLocations`) come from `JobSearchOptionsService` (`final readonly`, behind
  `JobSearchOptionsServiceInterface`): it converts the action's Extbase query with Extbase's (internal)
  `Typo3DbQueryParser` into a QueryBuilder - so storage pids, enable fields, language, plugin job areas,
  salary, open-job and search constraints are exactly the same - and replaces only SELECT/GROUP BY
  (`COUNT(DISTINCT job.uid)` per job area via the MM table, `GROUP BY` for role/type/address). With
  `maxEntries`, it restricts to the UIDs of the limited jobs (raw `execute(true)`). Titles/cities are loaded
  with frontend restrictions and translated via `PageRepository::getLanguageOverlay()`. Same array shapes as
  the templates expect (uid => title, city => city); sorting is done in Fluid (`sortByOptionLabel`).
- **Page cache lifetime**: `LimitJobListCacheLifetimeEventListener` (`ModifyCacheLifetimeForPageEvent`)
  limits the lifetime of pages with a `jobboard_jobboard` content element to the next change of the
  listed jobs: next future job starttime/endtime and the midnight after the earliest upcoming
  `application_deadline` / `ending_date` (jobs are listed through the end of that day). One `tt_content`
  query plus one conditional `MIN()` query over the plugin's storage pages (`pages`/`recursive`, else
  site setting `jobboard.storagePid` with depth 3). `effective_salary_valid_until` is deliberately not
  considered: salary changes are written by the daily command through DataHandler, which clears the cache.
- **`ApiModelInterface` implementations are pluggable data-source adapters.** Each class only defines a
  `NAME`, `API_ENDPOINT`, and a `MAPPING` array (API XPath -> DB column, with optional date-cast and
  prefix). New job sources are added by creating a new class tagged `api.model` via
  `#[Autoconfigure(tags: ['api.model'], shared: false)]` - no changes to `ImportService` are needed.
  `ApiMapping`/`ApiModelTrait`/`AbstractModel` provide the shared plumbing (XPath lookup with type casting).
- **Job areas and job types of the import are resolved by title** (`JobService::RELATION_COLUMNS`: `job_areas`,
  `job_type`, titles from the API model's mapping or the default MHM paths `custom_select_4/de` /
  `custom_select_2/de`; a mapping of these columns is never written as it is). `TitleRecordService` finds the
  record by title in any language (hidden ones too, `DeletedRestriction` only) and returns the default-language
  UID; unknown titles are created via DataHandler in the default language on the storage page of the import.
- **Vacancy IDs are namespaced per source.** `vacancy_id` is prefixed with the API model's `NAME`
  (e.g. `drs_12345`) to avoid collisions between different job sources sharing one storage folder.
- **Import is idempotent and self-cleaning.** `ImportService::import()` snapshots existing jobs for the
  storage PID, marks each as "seen" while importing, and deletes (via `DataHandler` cmdmap) everything that
  was not seen in this run - i.e. jobs removed from the source API are removed locally too.
- **`AddressSearchMiddleware`** answers city/zip autocomplete requests (custom header
  `jobboard-address-search`) against `tt_address` for the frontend search form; registered to run after
  `typo3/cms-frontend/authentication` because it needs the Frontend restriction context.
- **Pagination**: `JobboardController` dispatches `PostProcessFluidVariablesEvent` in `list`/`search` right
  before assigning the view variables; `jobs` is a `QueryResultInterface`. `AddPaginatorEventListener`
  replaces `jobs` with the jobs of the current page (`QueryResultPaginator`: COUNT query + LIMIT/OFFSET) and
  adds `paginator`, `pagination`, `numberOfJobs` and `actionName`. Search options are built before the event
  from all matching jobs.
  `GetPostPagination` keeps the (GET merged with POST) plugin arguments in the page links, so search
  criteria survive paging. Items per page: site setting `jobboard.pageBrowser.itemsPerPage`. The page
  browser is switched off by site setting `jobboard.pageBrowser.enable` (FlexForm `settings.pageBrowser.enable`
  overrides it: `''` = site setting via `ignoreFlexFormSettingsIfEmpty`, `1` = show, `-1` = hide) and
  always, if the content element sets `maxEntries` - a limited list (e.g. a teaser) is never paginated.
- **Remembered jobs** live in the TYPO3 frontend user session (also for anonymous visitors), key
  `tx_jobboard_remembered_jobs`, value a plain `int[]` of default-language job UIDs. Only
  `RememberedJobsService` (`final readonly`, behind `RememberedJobsServiceInterface`) reads/writes it, via
  the request attribute `frontend.user` (`getKey('ses', ...)` / `setAndSaveSessionData()`; an empty list
  stores `null`, so TYPO3 drops an otherwise empty anonymous session and its `fe_typo_user` cookie).
  `RememberedJobs` (pure `final readonly` value object in `Domain/Model/`) sanitizes untrusted session
  data and caps the list at `RememberedJobs::MAXIMUM`. Never repeat its value anywhere else (messages,
  labels, JS, docs) - reference the constant.
  - `RememberJobMiddleware` (header `jobboard-remember-job`, POST only, fields `job` = digits only,
    `remember` = `1`/`0`) answers `{"rememberedJobs": [...]}` plus `error` on 400/404/409. A non-POST
    request is rejected before the session is read and answers `{"error": "methodNotAllowed"}` (405)
    only. A POST with the header and an *empty* body is a read-only request: it answers
    `{"rememberedJobs": [...]}` (200) and never creates or writes a session - the cached list's JavaScript
    uses it to check/highlight remembered jobs. All JSON answers (also 405) send
    `Cache-Control: no-store`. It runs after `typo3/cms-frontend/authentication` and before
    `typo3/cms-frontend/page-resolver` (no page, no TypoScript needed). No Extbase: it checks visibility with the `ConnectionPoolTrait` QueryBuilder
    (frontend restrictions, pid in site setting `jobboard.storagePid` expanded 3 levels like
    `persistence.recursive` via `StoragePidResolver`, open job + salary rules via `OpenJobRestriction`) and the
    language of the request's `SiteLanguage` via `Database/Query/JobLanguageConstraint` - the QueryBuilder twin of
    `Typo3DbQueryParser::getLanguageStatement()` (same sub-selects with enable fields only, keep both in sync),
    combined with `uid IN OR l10n_parent IN` and mapped back to the default UID (`getDefaultLanguageUid()`), i.e.
    exactly the jobs `JobRepository::findByUids()` returns in that language. Without `language` attribute the
    default language is used. Consequence: a job remembered in another language that is not listed in the current
    one (strict/free, untranslated) is not counted there, and a *writing* request in that language prunes it. The read-only request
    and "forget" answer only still listed jobs, so the count badge matches the view (read-only never writes; a
    forget request stores the pruned list). It writes the session only if the list changed,
    so rejected/no-op requests never create a session. CSRF stance: custom header + POST (forces a CORS
    preflight cross-origin) + `SameSite` session cookie, no request token - see
    `Documentation/Administration/RememberJobs.rst`.
  - `JobboardController::rememberedJobsAction()` (uncached, route `/remembered`) assigns `jobs` (the
    visitor's remembered jobs via `JobRepository::findByUids()` with the same open-job/salary constraints,
    ordered like the list; `[]` without remembered jobs) and `rememberedJobs` (UIDs as keys). `list` and
    `search` never render anything visitor-specific: highlighting/checkboxes in the list are set by
    JavaScript from the read-only middleware request. Never pass `rememberedJobs` to
    `Partials/Types/Table.html` (or `Card.html`) from `List.html`/`Search.html`.
  - Templates: `Templates/Jobboard/RememberedJobs.html` (wrapper `data-jobboard-remembered-view`, renders
    `Types/Table` with `rememberedJobs`), `Partials/RememberedJobs/Link.html` (link to the action with the
    count badge `data-jobboard-remembered-count`, invisible until filled; carries its own
    `data-jobboard-remember-url`, so the read request also works without a job table) and
    `Partials/RememberedJobs/Feedback.html` (visually hidden `role="status"` live region + Bootstrap toast
    with the error messages as `data-jobboard-message-*` attributes). `List.html` and `Search.html` render
    both partials. The limit message (`rememberedJobs.error.limitReached`) must not contain a number.
- **Frontend JavaScript** in `Resources/Public/JavaScript/` is plain JavaScript without libraries, one file
  per feature, both added in `Partials/JobListAssets.html` (also rendered by `RememberedJobs.html`):
  - `remember-jobs.js`: on load (and on `pageshow` from the back/forward cache) it sends the read-only
    request (POST, header `jobboard-remember-job`, empty body) to the nearest/first
    `data-jobboard-remember-url` (fallback `window.location.pathname`), if the page has a `remember_job`
    checkbox or a count badge. The answer checks the checkboxes and toggles `highlight-row` on every
    `[data-jobboard-uid]` element (table rows and cards) and fills the badges. Inside
    `[data-jobboard-remembered-view]` it toggles `jobboard-removed` (greyed out, struck through) on
    unchecked rows instead, which stay until the next page load. Checkbox changes post `job`/`remember`,
    sync state from the JSON answer, show the toast for `error` codes (`limitReached`, `unknownJob`,
    otherwise `failed`) and announce the new count in the live region; on network errors/invalid answers
    the checkbox is reverted. A toggle counter discards a slow initial read answer after a toggle. Rows
    are never moved.
  - `address-suggestions.js`: zip/city suggestions of `AddressSearchMiddleware` as native `<datalist>`.
- **Map (optional EXT:maps2)**: one marker per job location (`tt_address`), not per job.
  - The map in `Partials/SearchForm.html` shows the locations of *all* jobs of the list/search result, never only
    the current page: `list`/`search` assign `poiCollections` from `MapPoiCollectionService` (`final readonly`,
    behind interface, called by the controller *before* the page browser replaces `jobs`). It gets the address
    UIDs from `JobSearchOptionsService::getAddressUids()` (same converted Extbase query as the search options,
    `GROUP BY address`: search criteria, plugin settings and `maxEntries` apply), reads `tx_maps2_uid` of the
    visible addresses with one QueryBuilder query (frontend restrictions) and loads only these PoiCollections via
    `PersistenceManager::getObjectByIdentifier()` (hidden ones skipped), ascending by UID. Without maps2 (no
    `tt_address.tx_maps2_uid` in TCA) it returns an empty `ObjectStorage` without any query. No job is loaded for
    the map. The "no geo position" alert checks `{jobs}`.
  - **No data access in ViewHelpers or templates**: no DB queries, repositories or PersistenceManager in
    ViewHelpers (the former `JobPoiCollectionViewHelper` was removed for that reason). Prepare data in the
    controller/services and pass it to the view.
  - PoiCollections are never changed: maps2 synchronizes their title from the address on every save
    (`Configuration/TCA/Overrides/tt_address.php`, `synchronizeColumns`: company, otherwise first + last name,
    otherwise city). `tt_address.tx_maps2_uid` is `l10n_mode = exclude` (+ `defaultAsReadonly`); maps2 writes the
    relation of a *new* PoiCollection into the default address only (raw SQL), so for a localized address without
    relation `MapPoiCollectionService` falls back to the PoiCollection of the default address (`l10n_parent`).
  - Info window: maps2 POSTs `{"poiCollection": uid}` (header `ext-maps2: infoWindowContent`) to the current page;
    its middleware dispatches `RenderInfoWindowContentEvent` for *every* POI of the site.
    `RenderJobLocationInfoWindowContentEventListener` (`final readonly`) only sets content if
    `JobLocationService` finds a visible default-language address with `tx_maps2_uid` = POI UID *and* open jobs
    - otherwise maps2 renders its default (POIs of events2, yellowpages2, ... stay untouched).
  - DI without maps2: the listener is excluded in `Services.yaml` and registered in `Configuration/Services.php`
    only if `$containerBuilder->hasDefinition(InfoWindowContentService::class)` (maps2 is a suggestion, so it is
    loaded before this extension). `EMU::isLoaded()` is not available while the container is built and
    `class_exists()` is true in functional tests without maps2 (maps2 is in `require-dev`).
  - `JobLocationService` (`final readonly`, behind interface, QueryBuilder only, no Extbase): jobs with the same
    visibility as the list - `StoragePidResolver` (site setting `jobboard.storagePid` + 3 levels, shared with
    `RememberJobMiddleware`), frontend restrictions and `OpenJobRestriction` (`Database/Query/Restriction/`, the
    QueryBuilder twin of `JobRepository::buildAndConstraintForOpenJobs()` - keep both in sync; title, effective
    salary min/max > 0 if `jobboard.salary.required`, deadline/ending date >= today). Language: from the
    request's `SiteLanguage` (`LanguageAspectFactory`), job titles and address overlaid with
    `PageRepository::getLanguageOverlay()` (jobs without translation are skipped in strict mode; without overlays
    the jobs of the language are selected). The address is overlaid like Extbase relations
    (`DataMapper::getPreparedQuery()`): with `OVERLAYS_OFF` ("free") it uses `OVERLAYS_MIXED`, so the translated
    company is shown if a translated address exists, else the default one.
    Known difference to the list: in fallback/strict mode, `JobLocationService` selects the default rows and checks
    `OpenJobRestriction` on them before the overlay, Extbase checks it on the translation (relevant only if a
    translation's own title/dates differ, salary and dates are usually identical). Order: by the *overlaid* title in PHP after the overlay (SQL would sort
    by the default-language title), `\Collator` with the site language locale (`posixFormatted()`), strength
    `SECONDARY` (case-insensitive) + `NUMERIC_COLLATION`, tie-breaker `uid` ASC; SQL only orders by `uid`
    (ext-intl is a hard requirement, no fallback). Results are the value objects `JobLocation` / `JobLink`
    (`Domain/Model/`).
  - Links: `JobUriBuilder` builds detail URIs with the site router (`generateUri()`, absolute path, cHash and
    route enhancer apply), target page = site setting `jobboard.pidOfDetailPage` if > 0, else the page of the
    request (`routing`). Jobs with an external http(s) `link` link there (`target="_blank" rel="noopener"`).
    The `link` column is TCA `type=link` (external URLs only, read-only, shown for imported jobs in the
    "import" palette), so TYPO3 derives the column from TCA. Never make it `passthrough` again: TYPO3 creates
    no column for passthrough and every job insert of the import fails ("Unknown column 'link'").
    `JobLocationService` selects `*` and reads `link` optionally.
  - Markup: `Templates/Map/InfoWindow.html` (variable `jobLocation`, `f:spaceless`, Bootstrap 5, `h3.h6`, count
    labels `map.infoWindow.numberOfJobs.singular|plural`), rendered by `JobLocationInfoWindowRenderer` via
    `ViewFactoryInterface` with `plugin.tx_jobboard.view.*RootPaths` - or, on a cached page without TypoScript
    setup, the extension defaults + site settings `jobboard.templateRootPath` etc. No Extbase request there:
    `f:translate` needs `extensionName`, no `f:link.action`.
- **`Address` domain model** extends `FriendsOfTYPO3\TtAddress\Domain\Model\Address` purely to add the
  `maps2` relation (`txMaps2Uid`). Keep this model in sync if `tt_address`'s own model changes upstream.

## 5. Upgrade wizards

- **`JobfairToJobboardMigration`** copies rows from the pre-rename `tx_jobfair2_domain_model_*` tables into
  the current `tx_jobboard_domain_model_*` tables, preserving every row's original `uid` (so foreign keys
  between these tables stay valid). It copies columns generically by diffing the *actual* schema of the old and new table
  (`getCommonColumns()`), not via a hardcoded field list - adding or removing a plain `Job` column normally needs no wizard change. FAL
  fields are the exception: `sys_file_reference.tablenames` is rewritten only for the columns listed in
  `JOB_FAL_FIELDS` (`employer_logo`, `header_logo`, `tender_file`, `pdf_files`), so a *new* FAL column that
  already existed under the old `tx_jobfair2_*` table name must be added to that constant, or its file
  relations will silently point at the old, no-longer-existing table after migration.
- **`JobAreaToJobAreasMigration`** / **`SalaryGradeToSalaryGradesMigration`** move the former single job
  area (int column `job_area`) / salary grade (text column `salary_grade`) of
  `tx_jobboard_domain_model_job` into the MM relations `job_areas` / `salary_grades`. Both legacy columns are
  no longer declared anywhere; the wizards only migrate our existing test environments and must run before
  the database compare drops the old columns (without them, they are silently not necessary). Their
  functional tests create the legacy column themselves in `setUp()`. As `JobfairToJobboardMigration` only
  copies common columns, `job_area`/`salary_grade` are no longer copied from the `tx_jobfair2_*` tables.
- **`JobPathSegmentUpdate`** (`jweilandJobboardJobPathSegmentUpdate`) fills empty `path_segment` slugs of
  jobs via `SlugHelper` (table-wide unique, as `PersistedAliasMapper` in `Configuration/Routes/Default.yaml`
  resolves slugs table-wide). New jobs, also imported ones, get their slug from `DataHandler` automatically.
- **`JobfairToJobboardCTypeMigration`** migrates `tt_content.CType` and `be_groups` explicit-allow/deny
  permissions from `jobfair2_jobfair` to `jobboard_jobboard`. Unrelated to FAL/domain data.

## 6. Coding conventions actually used here

- `declare(strict_types=1);` in every PHP file, one blank line after the opening tag.
- Constructor property promotion everywhere; `readonly class` for stateless services
  (`ImportService`, `JobService`, `TtAddressService`, `ApiMapping`).
- Symfony DI attributes (`#[Autoconfigure(...)]`, `#[AsCommand(...)]`) are used directly on classes instead
  of (or in addition to) `Services.yaml` entries. `Services.yaml` still explicitly marks the API model
  classes and excludes `Domain/Model/*`, `Database/Query/*` (restrictions and `JobLanguageConstraint`, created with `new`) and the maps2
  listener (registered by `Configuration/Services.php`). Flush caches after constructor changes (stale DI cache).
- TCA access via `TcaSchemaFactory` (constructor DI, read lazily in methods): ctrl columns (language field,
  translation parent, enable columns) come from schema capabilities (`TcaSchemaCapability::Language`,
  `RestrictionStartTime`/`RestrictionEndTime`), never as literals; field config via `getField()->getConfiguration()`,
  optional maps2 column via `hasField('tx_maps2_uid')`. Capabilities are optional: never `getCapability()` without
  `hasCapability()` - `Database/Query/TcaControlFields::fromSchema()` (pure value object) resolves them to `?string`
  field names. A missing capability means *no condition*: no WHERE/MIN()/PHP check for it, no exception (no start
  time = always started, no end time = never ends, no language capability = no language constraint, all rows count,
  only `uid` identifies a record, like Extbase without `languageField`). Select only existing fields, use real field
  names (no aliases). `JobPathSegmentUpdate` is "not necessary" without `path_segment` in TCA. Classes created with
  `new` get the schema from their creator (`JobLanguageConstraint(LanguageAspect, TcaSchema)`,
  `getDefaultLanguageUid()` is an instance method, `getTranslationParentField()` is nullable). `JobRepository` derives
  its unmapped `l10nParent` property from `transOrigPointerField` (only `uid IN` without it). Functional tests remove
  capabilities via `Tests/Functional/Traits/RemoveTcaCapabilityTrait` (`TcaSchemaFactory::rebuild()`, `restoreTca()` in
  `tearDown()`). Exceptions: `Configuration/TCA/Overrides/*` (TCA is being built, no schema yet) and `l10n_state` /
  MM `sorting` (fixed column names in DataHandler, not ctrl).
- Small reusable traits instead of a shared abstract base class: `ConnectionPoolTrait` (QueryBuilder with
  frontend restrictions applied), `DataHandlerTrait` (DataHandler instance), `ApiModelTrait` (interface
  boilerplate for API model classes).
- Errors from `DataHandler` are logged via the injected PSR-3 `LoggerInterface` (channel configured in
  `ext_localconf.php` under `TYPO3_CONF_VARS.LOG.JWeiland.Jobboard`), not thrown further - a failed record
  is skipped, not fatal for the whole import run. `ImportService::import()` returns the value object
  `Domain/Model/ImportResult` (job UIDs, number of failed jobs and API models, number of skipped jobs);
  `ImportJobsMhm` prints the numbers and returns `Command::FAILURE` only if a job could not be saved or an API
  model could not be fetched. Data problems of the source are no failure: jobs without vacancy ID or primary
  location are skipped with a warning (`Command::SUCCESS`, skipped count as console warning), and
  `ImportRecordValidator` empties values DataHandler would reject (invalid email, invalid URL of a `link`
  field with `allowedTypes` `['url']`) with a warning before saving.

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
