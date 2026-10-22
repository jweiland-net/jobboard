:navigation-title: Extensibility

..  include:: /Includes.rst.txt
..  _developer-extensibility:

=======================
Extending the job board
=======================

The extension does not dispatch own PSR-14 events yet. These extension
points exist:

Templates
    All templates, partials and layouts can be overridden, see
    :ref:`templates`.

Job sources
    New import sources are classes tagged with :yaml:`api.model`, see
    :ref:`developer-import-api`.

DataHandler hooks and events
    The import writes all records with the DataHandler, so the hooks and
    events of the TYPO3 Core are triggered, for example for search
    indexing.

Page title
    The page title provider can be replaced with TypoScript, see
    :ref:`configuration-page-title`.

Routing
    The route enhancer can be copied into your site configuration and
    changed, see :ref:`configuration-routing`.

Logging
    All messages of the extension use the log channel
    :php:`JWeiland.Jobboard`. Configure own writers in
    :php:`$GLOBALS['TYPO3_CONF_VARS']['LOG']['JWeiland']['Jobboard']`.

The address model :php:`JWeiland\Jobboard\Domain\Model\Address` extends
the model of EXT:tt_address and adds the map location of EXT:maps2. It is
mapped to the table :sql:`tt_address` in
:file:`EXT:jobboard/Configuration/Extbase/Persistence/Classes.php`.

..  _developer-domain-model:

Job properties for templates
============================

Useful properties of :html:`{job}` in Fluid:

:html:`{job.salaryRangeMin}`, :html:`{job.salaryRangeMax}`
    Lowest and highest salary, for both salary modes. In salary mode
    "Salary grade", these are the lowest and highest positive amount of all
    steps or fixed amounts of all valid selected grades. In salary mode
    "Free salary entry", the maximum falls back to the minimum if it is
    empty. Use these properties instead of evaluating
    :html:`{job.salaryMode}`.

:html:`{job.hasSalaryRange}`
    :html:`true` if minimum and maximum differ.

:html:`{job.hasSalaryInformation}`
    :html:`true` if the job has any salary information.

:html:`{job.jobAreas}`, :html:`{job.salaryGrades}`, :html:`{job.benefits}`
    All related records. :html:`{job.jobArea}` and :html:`{job.salaryGrade}`
    return the first record only.

File properties
    :html:`{job.employerLogo}`, :html:`{job.headerLogo}`,
    :html:`{job.tenderFile}` and :html:`{job.pdfFiles}` are file references. They are always lists, also :html:`{benefit.image}`.
    Use :html:`<f:for>` to render them.

:html:`{job.isImport}`, :html:`{salaryGrade.hasSteps}`
    Boolean values.
