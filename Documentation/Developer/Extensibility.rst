:navigation-title: Extensibility

..  include:: /Includes.rst.txt
..  _developer-extensibility:

=======================
Extending the job board
=======================

These extension points exist:

PSR-14 event :php:`PostProcessFluidVariablesEvent`
    :php:`JWeiland\Jobboard\Event\PostProcessFluidVariablesEvent` is
    dispatched by the actions :php:`list` and :php:`search` right before
    the variables are assigned to the view. Listeners can read the
    request and the settings and add or replace variables with
    :php:`addFluidVariable()`. The variable :html:`jobs` is a query result
    (:php:`TYPO3\CMS\Extbase\Persistence\QueryResultInterface`) that is
    not executed yet, so a listener can limit it in the database. The page
    browser is built this way, see
    :php:`JWeiland\Jobboard\EventListener\AddPaginatorEventListener`
    and :ref:`templates-pagination`. The options of the search form are
    built before the event, with aggregate queries of
    :php:`JWeiland\Jobboard\Service\JobSearchOptionsService`.

Templates
    All templates, partials and layouts can be overridden, see
    :ref:`templates`. This includes the info window of the map, see
    :ref:`templates-info-window`.

Info window of the map
    With EXT:maps2, the job board renders the info window of job locations
    in a listener of the PSR-14 event
    :php:`JWeiland\Maps2\Event\RenderInfoWindowContentEvent`, see
    :ref:`developer-extensibility-info-window`.

Job sources
    New import sources are classes tagged with :yaml:`api.model`, see
    :ref:`developer-import-api`.

DataHandler hooks and events
    The import writes all records with the DataHandler, so the hooks and
    events of the TYPO3 Core are triggered, for example for search
    indexing. The job board itself uses the DataHandler hook
    :php:`JWeiland\Jobboard\Hook\UpdateEffectiveSalaryHook` to
    calculate the effective salary of jobs, see :ref:`salary-update`.
    Write jobs and salary records with the DataHandler as well, or run
    :bash:`vendor/bin/typo3 jobboard:salary:update --all` afterwards.

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

..  _developer-extensibility-info-window:

Info window of job locations
============================

When a visitor clicks a marker, EXT:maps2 requests the info window content
with an AJAX request and dispatches the PSR-14 event
:php:`JWeiland\Maps2\Event\RenderInfoWindowContentEvent` with the record
of the map location (:php:`getPoiCollectionRecord()`) and the request.

The job board listens to this event with
:php:`JWeiland\Jobboard\EventListener\RenderJobLocationInfoWindowContentEventListener`
(identifier :code:`jobboard/renderJobLocationInfoWindowContent`). The
listener is registered in :file:`EXT:jobboard/Configuration/Services.php`
only if EXT:maps2 is installed. It works like this:

*   If another listener has already set content
    (:php:`getInfoWindowContent()` is not empty), it does nothing.
*   It searches the addresses with this map location and their open jobs,
    see :ref:`templates-info-window`. If there is none, it does nothing.
    Markers of other extensions are therefore not affected, EXT:maps2
    renders their info window as usual.
*   Otherwise, it renders :file:`Templates/Map/InfoWindow.html` and sets
    the result with :php:`setInfoWindowContent()`.

To change the HTML of the info window, override the template, see
:ref:`templates-info-window`.

To replace the info window with your own PHP code, register a listener of
the same event that runs before the listener of the job board. If your
listener sets content, the job board keeps it:

..  literalinclude:: _snippets/MyJobLocationInfoWindowEventListener.php
    :language: php
    :caption: EXT:my_sitepackage/Classes/EventListener/MyJobLocationInfoWindowEventListener.php

A listener that runs after the listener of the job board can read and
change the content set by the job board with
:php:`getInfoWindowContent()` and :php:`setInfoWindowContent()`.

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

:html:`{job.effectiveSalaryMin}`, :html:`{job.effectiveSalaryMax}`
    The stored effective salary, see :ref:`salary-update`.
    :html:`{job.hasEffectiveSalaryInformation}` is :html:`true` if one of
    them is greater than 0. The list and the detail view use it to decide
    whether a job is shown.

:html:`{job.jobAreas}`, :html:`{job.salaryGrades}`, :html:`{job.benefits}`
    All related records. :html:`{job.jobArea}` and :html:`{job.salaryGrade}`
    return the first record only.

File properties
    :html:`{job.employerLogo}`, :html:`{job.headerLogos}`,
    :html:`{job.tenderFile}` and :html:`{job.pdfFiles}` are file
    references. They are always lists, also :html:`{benefit.image}`.
    Use :html:`<f:for>` to render them.

:html:`{job.isImport}`, :html:`{salaryGrade.hasSteps}`
    Boolean values.
