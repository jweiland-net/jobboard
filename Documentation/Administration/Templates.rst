:navigation-title: Templates

..  include:: /Includes.rst.txt
..  _templates:

=============================
Templates and frontend assets
=============================

The output of the job board is rendered with Fluid. Copy the files you
want to change into your site package and register its paths.

..  _templates-override:

Override templates
==================

Set the site settings :confval:`jobboard-templaterootpath`,
:confval:`jobboard-partialrootpath` and :confval:`jobboard-layoutrootpath`
to the folders of your site package. Files that you do not copy are taken
from the extension.

..  code-block:: yaml
    :caption: config/sites/<site-identifier>/settings.yaml

    jobboard:
      templateRootPath: 'EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Templates/'
      partialRootPath: 'EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Partials/'
      layoutRootPath: 'EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Layouts/'

Alternatively, add paths in TypoScript, see
:ref:`configuration-typoscript-view`.

..  _templates-files:

Template files
==============

All files are in :path:`EXT:jobboard/Resources/Private/`.

..  list-table::
    :header-rows: 1
    :widths: 45 55

    *   -   File
        -   Purpose
    *   -   :file:`Layouts/Default.html`
        -   Wrapper :html:`<div class="tx-jobboard">`
    *   -   :file:`Templates/Jobboard/List.html`
        -   List: assets, search form, map and list of jobs
    *   -   :file:`Templates/Jobboard/Search.html`
        -   Search result, same structure as the list
    *   -   :file:`Templates/Jobboard/Detail.html`
        -   Detail view. One :html:`<f:section>` per group of fields, for
            example :code:`renderSalary` or :code:`renderBenefits`.
    *   -   :file:`Partials/JobListAssets.html`
        -   CSS and JavaScript of list and search, see
            :ref:`templates-assets`
    *   -   :file:`Partials/SearchForm.html`
        -   Search form and map
    *   -   :file:`Partials/Job/Search.html`
        -   The search form itself
    *   -   :file:`Partials/Types/Table.html`
        -   Default list rendering as table
    *   -   :file:`Partials/Types/Card.html`
        -   Alternative list rendering as cards, see
            :ref:`templates-card-layout`
    *   -   :file:`Partials/Job/TitleLink.html`
        -   Table cell with the link to the job
    *   -   :file:`Partials/Job/JobAreas.html`
        -   Occupational fields of a job, see :ref:`templates-job-areas`
    *   -   :file:`Partials/Job/JsonLd.html`
        -   Structured data of the detail view, see
            :ref:`templates-json-ld`
    *   -   :file:`Templates/PluginPreview/Jobboard.html`
        -   Preview in the backend page module
    *   -   :file:`Pdf/Jobboard/Detail.html`
        -   PDF version of a job, see :ref:`pdf-export`

If a job has an external link (filled by an import only), the list links
to this URL in a new window instead of the detail view.

..  _templates-card-layout:

Card layout
===========

The partial :file:`Partials/Types/Card.html` renders each job as a card
with occupational fields, position, application deadline, title, a
shortened description, a "Read more" button and, if
:confval:`jobboard-pidofapplicationform` is set, an "Apply online" button.
It has no "Remember?" checkbox.

To use it, override :file:`Templates/Jobboard/List.html` and
:file:`Templates/Jobboard/Search.html` and replace the table partial:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Templates/Jobboard/List.html

    <f:render partial="Types/Card" arguments="{objects: jobs}"/>

..  _templates-job-areas:

Partial "Job/JobAreas"
======================

Renders all occupational fields of a job, each in a
:html:`<span class="job-area">`.

:html:`job`
    The job (:php:`JWeiland\Jobboard\Domain\Model\Job`). Required.

:html:`separator`
    Text between the occupational fields. Optional, default :html:`', '`.
    Use :html:`''` if the spacing is done with CSS.

..  code-block:: html
    :caption: Render the occupational fields separated by a pipe

    <f:render partial="Job/JobAreas" arguments="{job: job, separator: ' | '}"/>

..  _templates-json-ld:

Structured data (JSON-LD)
=========================

The detail view adds a :code:`schema.org/JobPosting` as
:html:`<script type="application/ld+json">` with these properties: title,
description, identifier (employer and reference number), start date,
:code:`validThrough` (application deadline), hiring organization with
logo, job location, base salary (monthly, in EUR) and :code:`directApply`
(true if the job has an email address).

Override :file:`Partials/Job/JsonLd.html` to change the properties. Follow
the rules in the comment of this partial to keep the output valid JSON:
render every dynamic value with the tag syntax
:html:`<f:format.json value="{...}"/>`.

..  _templates-assets:

JavaScript and CSS
==================

The partial :file:`Partials/JobListAssets.html` adds these files with the
asset collector of TYPO3:

*   :file:`Css/jquery-ui.min.css`, :file:`Css/style.css`
*   :file:`JavaScript/jquery-3.7.1.min.js` (jQuery)
*   :file:`JavaScript/jquery-ui.min.js` (suggestions of the location field)
*   :file:`JavaScript/datatables.min.js` (sortable table)
*   :file:`JavaScript/js.cookie-2.2.1.min.js` (cookie of remembered jobs)
*   :file:`JavaScript/script.js` (job board logic)

The job board ships its own jQuery. :file:`script.js` releases it with
:js:`jQuery.noConflict(true)` after jQuery UI and DataTables have been
attached to it. A jQuery that your site loads itself stays untouched.

The texts of the table (DataTables) come from
:file:`JavaScript/dataTables/i18n/de_DE.json`.

To use other libraries or files, override the partial
:file:`Partials/JobListAssets.html`.

..  _templates-labels:

Labels
======

Frontend labels are in
:file:`EXT:jobboard/Resources/Private/Language/locallang.xlf` (English)
and :file:`de.locallang.xlf` (German). Override single labels in your site
package, see :ref:`t3coreapi:xliff-translating-custom`.

Some useful label keys:

:code:`dateFormat`
    Date format of the list, the cards, the detail view and the PDF
    version, for example :code:`Y-m-d` (English) or :code:`d.m.Y` (German).

:code:`job.applyOnline`
    Text of the "Apply online" link.

:code:`job.downloadPdf`, :code:`job.downloadPdf.addition`
    Text of the link to the PDF version. The addition is hidden on small
    screens.

:code:`search.no_jobs_found`
    Text if no job matches.

..  _templates-viewhelper:

ViewHelper for the map
======================

:file:`Partials/SearchForm.html` uses the ViewHelper
:php:`JWeiland\Jobboard\ViewHelpers\JobPoiCollectionViewHelper` to build the
map markers of all listed jobs. Each marker gets the job title and, if the
job has an application deadline and an employment scope, an info window
with both.

..  code-block:: html
    :caption: Use the ViewHelper in an own template

    <html xmlns:jobboard="http://typo3.org/ns/JWeiland/Jobboard/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <f:variable name="poiCollections" value="{jobboard:jobPoiCollection(jobs: jobs)}"/>

Without EXT:maps2, the ViewHelper returns an empty list and no map is
rendered.
