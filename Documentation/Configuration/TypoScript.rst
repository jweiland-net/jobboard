:navigation-title: TypoScript

..  include:: /Includes.rst.txt
..  _configuration-typoscript:

==========
TypoScript
==========

The site set :yaml:`jweiland/jobboard` provides this TypoScript setup. You
normally configure the job board with the
:ref:`site settings <configuration-site-settings>` and only need
TypoScript for special cases.

..  literalinclude:: _snippets/_setup.typoscript
    :language: typoscript
    :caption: EXT:jobboard/Configuration/Sets/Jobboard/setup.typoscript

..  _configuration-typoscript-view:

View paths
==========

The template, partial and layout paths of index :typoscript:`1` come from
the site settings. The partial path of index :typoscript:`2` contains the
partial :file:`Maps2/PoiCollection` of EXT:maps2, which renders the map.
Without EXT:maps2, this path is ignored. See :ref:`templates` for
overriding templates.

..  _configuration-typoscript-persistence:

Storage folder
==============

:typoscript:`persistence.storagePid` is set from the site setting
:confval:`jobboard-storagepid`. :typoscript:`persistence.recursive = 3`
finds jobs in up to three levels of subpages of the storage folders.

The content element "Job board" has no field "Record Storage Page". The
storage folder is configured for the whole site.

..  _configuration-typoscript-settings:

Settings in Fluid
=================

These values are available as :html:`{settings.<name>}` in all templates
of the plugin, together with the options of the content element
(see :ref:`configuration-plugin`):

*   :typoscript:`settings.pidOfDetailPage`
*   :typoscript:`settings.pidOfApplicationForm`
*   :typoscript:`settings.showJobAreaCount`
*   :typoscript:`settings.pageBrowser.itemsPerPage`

..  _configuration-typoscript-pagebrowser:

Page browser
============

:typoscript:`settings.pageBrowser.class` is the class that builds the
links of the page browser. It must implement
:php:`TYPO3\CMS\Core\Pagination\PaginationInterface`, otherwise
:php:`JWeiland\Jobboard\Pagination\GetPostPagination` is used. Its
constructor gets the paginator and the arguments of the current request.
:typoscript:`settings.pageBrowser.itemsPerPage` comes from the site
setting :confval:`jobboard-pagebrowser-itemsperpage`.

..  _configuration-typoscript-ignoreflexform:

Empty content element options
=============================

:typoscript:`ignoreFlexFormSettingsIfEmpty = showJobAreaCount` lets an
empty option "Show number of jobs per job area" of the content element
fall back to the TypoScript value. Extbase treats an empty string and
:code:`0` as empty. That is why the option "Hide" of the content element
stores the value :code:`-1`. Only the value :code:`1` shows the number of
jobs.

..  _configuration-typoscript-pagetitle:

Page title provider
===================

:typoscript:`config.pageTitleProviders.jobboard` registers the page title
provider of the detail view. See :ref:`configuration-page-title`.
