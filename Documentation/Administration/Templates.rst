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
    *   -   :file:`Templates/Jobboard/RememberedJobs.html`
        -   View of the remembered jobs of the visitor, see
            :ref:`templates-remembered-jobs`
    *   -   :file:`Templates/Jobboard/Detail.html`
        -   Detail view. One :html:`<f:section>` per group of fields, for
            example :code:`renderSalary` or :code:`renderBenefits`.
    *   -   :file:`Partials/JobListAssets.html`
        -   CSS and JavaScript of list, search and the view of the
            remembered jobs, see :ref:`templates-assets`
    *   -   :file:`Partials/RememberedJobs/Link.html`
        -   Link to the view of the remembered jobs with the count badge
    *   -   :file:`Partials/RememberedJobs/Feedback.html`
        -   Status for screen readers and toast with error messages of
            :file:`remember-jobs.js`
    *   -   :file:`Partials/SearchForm.html`
        -   Search form and map
    *   -   :file:`Templates/Map/InfoWindow.html`
        -   Info window of a job location on the map (EXT:maps2 only), see
            :ref:`templates-info-window`
    *   -   :file:`Partials/Job/Search.html`
        -   The search form itself. Provides the URL for the location
            suggestions, see :ref:`address-search-override`.
    *   -   :file:`Partials/Types/Table.html`
        -   Default list rendering as table
    *   -   :file:`Partials/Pagination.html`
        -   Page browser below the list, see :ref:`templates-pagination`
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
It has no "Remember" checkbox. :file:`remember-jobs.js` highlights the
cards of jobs remembered in the table layout.

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

..  _templates-pagination:

Page browser
============

List and search result show :confval:`jobboard-pagebrowser-itemsperpage`
jobs per page. The partial :file:`Partials/Pagination.html` renders the
links to the first, previous, next and last page and the range of the
shown jobs. The links of a search result keep the search criteria.

The page browser is added by the PSR-14 event listener
:php:`JWeiland\Jobboard\EventListener\AddPaginatorEventListener`, see
:ref:`developer-extensibility`. It provides these variables:

:html:`{jobs}`
    The jobs of the current page only. The map above the list shows the
    locations of all jobs of the list or search result, see
    :ref:`templates-map-markers`.

:html:`{paginator}`
    :php:`TYPO3\CMS\Extbase\Pagination\QueryResultPaginator`, for
    example :html:`{paginator.currentPageNumber}`. It loads only the jobs
    of the current page from the database.

:html:`{pagination}`
    :php:`JWeiland\Jobboard\Pagination\GetPostPagination`, for example
    :html:`{pagination.lastPageNumber}` or
    :html:`{pagination.nextPageArguments}`.

:html:`{numberOfJobs}`
    Number of all jobs of the list or search result.

If the page browser is disabled (see :confval:`jobboard-pagebrowser-enable`
and :confval:`plugin-pagebrowser-enable`) or the content element limits the
list with :confval:`plugin-maxentries`, the event listener adds nothing:
:html:`{jobs}` contains all jobs of the list or search result (a query
result of :php:`JWeiland\Jobboard\Domain\Model\Job` objects), and the
variables :html:`{paginator}`, :html:`{pagination}`,
:html:`{numberOfJobs}` and :html:`{actionName}` are not assigned. The
shipped partial :file:`Partials/Pagination.html` then renders nothing.
Check these variables with :html:`<f:if>` before you use them in own
templates.

The options of the search form are built from all jobs of the list or
search result, not only from the jobs of the current page.

..  _templates-remembered-jobs:

View of the remembered jobs
===========================

:file:`Templates/Jobboard/RememberedJobs.html` renders the action
:php:`rememberedJobs`, see :ref:`remember-jobs-view`. This action is not
cached and has no page browser. It provides these variables:

:html:`{jobs}`
    The remembered jobs of the current visitor that are still listed in
    the frontend, in the order of the list. Empty if the visitor has not
    remembered any job.

:html:`{rememberedJobs}`
    The UIDs of the remembered jobs as keys. Passed to
    :file:`Partials/Types/Table.html` to render the checkboxes checked.

Never pass :html:`{rememberedJobs}` in :file:`Templates/Jobboard/List.html`
or :file:`Templates/Jobboard/Search.html`: their output must be the same
for all visitors, see :ref:`remember-jobs-override`.

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

*   :file:`Css/style.css`
*   :file:`JavaScript/remember-jobs.js` (remembered jobs)
*   :file:`JavaScript/address-suggestions.js` (suggestions of the location
    field)

Both JavaScript files are plain JavaScript. :file:`remember-jobs.js`
reads the remembered jobs of the visitor after the page is loaded,
highlights them in the cached list and fills the count badge of the link
to the remembered jobs. It sends a ticked or unticked "Remember" checkbox
to the server and updates the highlight, see :ref:`remember-jobs`. Neither
file needs a library.
:file:`address-suggestions.js` shows the suggestions of the location field
as a native :html:`<datalist>`, see :ref:`address-search`.

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

:code:`map.noGeoPosition.header`, :code:`map.noGeoPosition`
    Message instead of the map, if no listed job has an address with a map
    location (EXT:maps2 only).

:code:`map.infoWindow.numberOfJobs.singular`, :code:`map.infoWindow.numberOfJobs.plural`
    Number of jobs in the info window of a job location. The plural label
    contains :code:`%d` for the number.

:code:`rememberedJobs.link`, :code:`rememberedJobs.header`
    Text of the link to the remembered jobs and heading of their view.

:code:`rememberedJobs.error.limitReached` and further error labels
    Messages of :file:`remember-jobs.js` if a job cannot be remembered or
    forgotten (:code:`rememberedJobs.error.unknownJob`,
    :code:`rememberedJobs.error.failed`).

..  _templates-map-markers:

Markers of the map
==================

List and search result assign the variable :html:`{poiCollections}`: the
map locations (EXT:maps2) of all jobs of the list or search result, one
marker per location, even if several jobs or addresses share the same
location. The page browser does not reduce it, only the search form and the
settings of the content element do. Hidden addresses and addresses without
a map location are skipped. Without EXT:maps2, it is empty and no map is
rendered.

The markers are built before the template is rendered, by the service
:php:`JWeiland\Jobboard\Service\MapPoiCollectionServiceInterface`. No job is
loaded for the map. :file:`Partials/SearchForm.html` passes
:html:`{poiCollections}` to the map partial of EXT:maps2.

The map locations of EXT:maps2 are passed unchanged. The title of a marker
is the title of the map location, which EXT:maps2 takes from the address,
see :ref:`editor-addresses-map`. The info window is loaded when a visitor
clicks the marker, see :ref:`templates-info-window`.

..  _templates-info-window:

Info window of a job location
=============================

When a visitor clicks a marker, EXT:maps2 loads the content of the info
window with an AJAX request. For markers of job locations, the job board
renders :file:`Templates/Map/InfoWindow.html`. It shows:

*   The company of the address, otherwise first and last name
*   ZIP code and city
*   The number of jobs, for example "3 jobs at this location"
*   A link to each open job at this location

The jobs are selected with the same rules as the job list: open jobs (see
:ref:`editor-visibility`) in the storage folder of
:confval:`jobboard-storagepid` and its subpages, in the language of the
page. They are sorted alphabetically by the title shown to the visitor:
in a translated language by the translated title, according to the rules
of the locale of the site language and without regard to upper and lower
case. Jobs with the same title keep the order of their creation. The
links lead to the detail view on the page
:confval:`jobboard-pidofdetailpage` or, if it is not set, on the current
page. Jobs with an external link (filled by an import only) link to this
URL in a new tab, like in the list.

..  note::

    The info window always lists all open jobs at the location. It is not
    limited to the jobs of the current search, the current page of the page
    browser or the :confval:`plugin-jobareas` of the content element:
    EXT:maps2 only sends the map location to the server, not the content
    element or the search.

Override the template like the other templates, see
:ref:`templates-override`. The template is searched in the template root
paths of :typoscript:`plugin.tx_jobboard.view`. If the TypoScript setup is
not available in the AJAX request (cached page), the site settings
:confval:`jobboard-templaterootpath`, :confval:`jobboard-partialrootpath`
and :confval:`jobboard-layoutrootpath` are used. Place the file in
:file:`Map/InfoWindow.html` of your template folder.

The template is rendered without an Extbase request. Use the attribute
:html:`extensionName: 'jobboard'` in :html:`f:translate` and the prepared
URIs of the job links instead of :html:`f:link.action`.

The template gets one variable:

:html:`{jobLocation}`
    :php:`JWeiland\Jobboard\Domain\Model\JobLocation` with these
    properties:

    :html:`{jobLocation.name}`
        Company of the address, otherwise first and last name. May be
        empty.

    :html:`{jobLocation.street}`
        Street and house number of the address (field :sql:`address` of
        :sql:`tt_address`). May be empty.

    :html:`{jobLocation.zip}`, :html:`{jobLocation.city}`
        ZIP code and city of the address.

    :html:`{jobLocation.numberOfJobs}`
        Number of open jobs at this location. Always at least 1.

    :html:`{jobLocation.jobLinks}`
        List of :php:`JWeiland\Jobboard\Domain\Model\JobLink` objects
        with :html:`{jobLink.uid}`, :html:`{jobLink.title}`,
        :html:`{jobLink.uri}` and :html:`{jobLink.isExternal}`. The URI
        is empty if no link could be built.

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Templates/Map/InfoWindow.html

    <strong>{jobLocation.name}</strong>
    <ul>
        <f:for each="{jobLocation.jobLinks}" as="jobLink">
            <li><a href="{jobLink.uri}">{jobLink.title}</a></li>
        </f:for>
    </ul>

The labels :code:`map.infoWindow.numberOfJobs.singular` and
:code:`map.infoWindow.numberOfJobs.plural` contain the text of the number
of jobs, see :ref:`templates-labels`.

To render the info window with PHP instead, see
:ref:`developer-extensibility-info-window`.
