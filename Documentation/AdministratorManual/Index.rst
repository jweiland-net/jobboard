:navigation-title: Administrator manual

..  include:: /Includes.rst.txt


..  _admin-manual:

====================
Administrator manual
====================

Target group: **Administrators / Integrators**

This manual describes how to install and configure Jobboard: activating the
extension, including its Site Sets, and setting the storage folder and page
IDs the extension needs.


..  _admin-manual-installation:

Installation
============

#.  Require the extension via Composer:

    ..  code-block:: bash

        composer require jweiland/jobboard

#.  Activate the extension, either via the :guilabel:`Extensions` module in
    the TYPO3 backend, or via the command line:

    ..  code-block:: bash

        vendor/bin/typo3 extension:setup

#.  Open the :guilabel:`Site Configuration` module for the site the jobs
    should appear on, and add the Site Set :guilabel:`Jobboard - Main`
    (`jweiland/jobboard`). It automatically pulls in `jweiland/maps2-default`,
    `jweiland/maps2-googlemaps` and `jweiland/jobboard-pdf`.

#.  Create a storage folder page for job, job area and job type records, and
    configure it below.

..  figure:: ../Images/AdministratorManual/ExtensionManager.png
    :width: 500px
    :alt: TYPO3 Extensions module listing installed extensions
    :zoom: lightbox

    The :guilabel:`Extensions` module, where Jobboard can be activated.


..  _admin-manual-settings:

Site settings
=============

The following settings are available once the Site Set has been added to a
site and can be adjusted per site under :guilabel:`Site Configuration >
Settings`:

..  typo3:site-set-settings:: PROJECT:/Configuration/Sets/Jobboard/settings.definitions.yaml
    :name: jobboard-site-settings


..  _admin-manual-address-search-scope:

Address search and page scoping
================================

The city/zip field of the frontend search form queries
:t3ext:`tt_address` for autocomplete suggestions while the visitor types,
through
:php:class:`\JWeiland\Jobboard\Middleware\AddressSearchMiddleware`. This
middleware deliberately never learns which storage pages it may search
from the request itself.

..  important::
    :t3ext:`tt_address` is a shared table. On many installations it also
    holds addresses that have nothing to do with Jobboard, for example an
    internal address book or contact data that counts as personal data.
    Without a page restriction, the autocomplete search would work as an
    unauthenticated lookup across every visible :t3ext:`tt_address`
    record on the site, not just job locations.

That is why the storage pages are never passed through a template or
JavaScript as part of the request. Any value that travels through the
browser can be rewritten by whoever controls the client, regardless of
how it got there. Instead,
:php:class:`\JWeiland\Jobboard\Middleware\AddressSearchMiddleware` reads
`jobboard.storagePid` directly from the current site's Site Settings via
the PSR-7 request
(:php:`$request->getAttribute('site')->getSettings()`), the same setting
configured above under :ref:`admin-manual-settings`. TYPO3's
:php:class:`\TYPO3\CMS\Frontend\Middleware\SiteResolver` runs early in
the request stack, well before Jobboard's own middleware, so the site and
its settings are already available. No extra transfer of the page ID is
needed, and the search scope can not be influenced by the request.

..  note::
    This is also why `jobboard.storagePid` is a comma-separated list of
    page IDs (`type: string`) instead of a single page picker. If
    :t3ext:`tt_address` records for job locations live in more than one
    folder, list every additional page ID in this setting under
    :guilabel:`Site Configuration > Settings`, for example `0,34,67`.
    Extending `plugin.tx_jobboard.persistence.storagePid` via TypoScript
    only narrows down the job list and search, not this middleware. It
    never parses TypoScript, only Site Settings.


..  _admin-manual-list-layout:

Alternative list layout: cards
==============================

List and search render jobs as a table
(:file:`Resources/Private/Partials/Types/Table.html`) by default. A
card-based alternative ships alongside it in
:file:`Resources/Private/Partials/Types/Card.html` - both accept the same
`objects` argument, so switching between them only means changing which
partial :file:`Templates/Jobboard/List.html` and
:file:`Templates/Jobboard/Search.html` render, nothing else in either
template has to change.

To switch to it, add your own `templateRootPaths` on top of Jobboard's,
for example in your site package's TypoScript setup:

..  code-block:: typoscript

    plugin.tx_jobboard.view {
        templateRootPaths.100 = EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Templates/
    }

Then place your own copies of :file:`Templates/Jobboard/List.html` and/or
:file:`Templates/Jobboard/Search.html` at that path, with the
`f:render partial="Types/Table"` line swapped for `Types/Card`:

..  code-block:: html

    <f:if condition="{jobs}">
        <f:then>
            <f:render partial="Types/Card" arguments="{objects: jobs}"/>
        </f:then>
        <f:else>
            <p>{f:translate(key: 'search.no_jobs_found')}</p>
        </f:else>
    </f:if>

..  note::
    Only that one line needs to change - copy the rest of the shipped
    :file:`List.html`/:file:`Search.html` (layout, search form, empty
    result message) as-is.


..  _admin-manual-routing:

Speaking URLs
=============

Jobboard ships a route enhancer for speaking URLs of the job detail view,
for example :samp:`/jobs/web-developer-m-w-d`. It has to be imported in the
site configuration. See :ref:`admin-routing` for setup, the URL segment
field of jobs and the upgrade wizard for existing jobs.


..  _admin-manual-import:

Importing jobs automatically
============================

Jobboard does not ship with a preconfigured job source. To pull job postings
from an external API on a schedule, implement one small PHP class and
register a TYPO3 :guilabel:`Scheduler` task that runs the console command
described in :ref:`admin-api`. That page walks through building such a
class step by step.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Routing/Index
    Api/Index
