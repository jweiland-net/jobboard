:navigation-title: Content element

..  include:: /Includes.rst.txt
..  _configuration-plugin:

===========================
Content element "Job board"
===========================

The extension registers one content element (CType
:php:`jobboard_jobboard`, plugin namespace :code:`tx_jobboard_jobboard`)
with four actions:

:php:`list`
    Default action. Shows the search form, the map and all current jobs.
    Cached.

:php:`search`
    Shows the jobs matching the submitted search form. Not cached, because
    the search criteria are arbitrary.

:php:`detail`
    Shows one job. Cached. The shipped templates render the detail view on
    the same page as the list.

:php:`rememberedJobs`
    Shows the jobs remembered by the current visitor. Not cached, see
    :ref:`remember-jobs-view`.

The cached actions render the same output for every visitor. The
remembered jobs are highlighted by JavaScript, see
:ref:`remember-jobs-caching`.

The options of the content element are in the tab
:guilabel:`Configuration`. See :ref:`editor-content-element` for the
editor's view.

..  _configuration-plugin-general:

Sheet "General"
===============

..  figure:: /Images/Editor/plugin-sheet-general.png
    :alt: Sheet General with the options Job areas, Max entries to show and Show page browser
    :class: with-border with-shadow
    :zoom: lightbox

    Sheet "General" of the content element

..  confval-menu::
    :name: plugin-options-general
    :display: table
    :type:
    :default:

    ..  confval:: Job areas
        :name: plugin-jobareas
        :type: occupational field records
        :default: empty

        FlexForm field :code:`settings.jobAreas`. Shows only jobs that have
        at least one of the selected occupational fields. Empty: jobs of all
        occupational fields are shown.

        The restriction applies to the list and to the search result. The
        select box of occupational fields in the search form and the number
        of jobs per occupational field only consider the selected fields. A
        submitted occupational field outside of the selection is ignored.

    ..  confval:: Max entries to show (0 for all)
        :name: plugin-maxentries
        :type: integer
        :default: :code:`0`

        FlexForm field :code:`settings.maxEntries`. Maximum number of jobs in
        the list and in the search result. The limit is part of the
        database query and applies after all other conditions, for example
        jobs without salary information (see
        :confval:`jobboard-salary-required`), so visitors always get up to
        this number of jobs. :code:`0` shows all jobs.

        A number greater than :code:`0` also switches off the page browser:
        the limited list is shown on one page, independent of
        :confval:`plugin-pagebrowser-enable` and the site setting
        :confval:`jobboard-pagebrowser-enable`.

    ..  confval:: Show page browser
        :name: plugin-pagebrowser-enable
        :type: :code:`''`, :code:`1` or :code:`-1`
        :default: :code:`''` (Use value from TypoScript)

        FlexForm field :code:`settings.pageBrowser.enable`. "Use value from
        TypoScript" uses the site setting
        :confval:`jobboard-pagebrowser-enable`. "Show" (:code:`1`) and "Hide"
        (:code:`-1`) override it for this content element. With "Hide", the
        list and the search result show all jobs on one page.

        Has no effect, if :confval:`plugin-maxentries` is greater than
        :code:`0`. Then the page browser is never shown.

..  _configuration-plugin-search:

Sheet "Search"
==============

..  figure:: /Images/Editor/plugin-sheet-search.png
    :alt: Sheet Search with the options of the search form
    :class: with-border with-shadow
    :zoom: lightbox

    Sheet "Search" of the content element

..  confval-menu::
    :name: plugin-options-search
    :display: table
    :type:
    :default:

    ..  confval:: Show Search
        :name: plugin-showsearch
        :type: boolean
        :default: :code:`1`

        FlexForm field :code:`settings.showSearch`. Shows the search form.
        The map is shown independent of this option.

    ..  confval:: Address search field
        :name: plugin-addresssearchmode
        :type: :code:`zipCity` or :code:`jobLocation`
        :default: :code:`zipCity`

        FlexForm field :code:`settings.addressSearchMode`.

        :code:`zipCity` (Search field with suggestions)
            A text field for a ZIP code or city. After three characters, up
            to five suggestions in the format "12345 - Sample Town" are
            shown, see :ref:`address-search`.

        :code:`jobLocation` (Job location select box)
            A select box with the cities of all listed jobs.

    ..  confval:: Show free text search
        :name: plugin-showfreetextsearch
        :type: boolean
        :default: :code:`1`

        FlexForm field :code:`settings.showFreeTextSearch`. Shows the
        keyword field.

    ..  confval:: Show number of jobs per job area
        :name: plugin-showjobareacount
        :type: :code:`''`, :code:`1` or :code:`-1`
        :default: :code:`''` (Use value from TypoScript)

        FlexForm field :code:`settings.showJobAreaCount`. "Use value from
        TypoScript" uses the site setting
        :confval:`jobboard-showjobareacount`. "Show" (:code:`1`) and "Hide"
        (:code:`-1`) override it for this content element.

..  _configuration-plugin-search-logic:

How the search works
====================

A job is part of the search result if it is currently open (see
:ref:`editor-visibility`) and matches all filled fields of the search form:

Occupational field
    The job has this occupational field among its occupational fields. If
    the content element restricts the occupational fields (see
    :confval:`plugin-jobareas`), only jobs of these fields are found, and a
    submitted field outside of them is ignored.

Position, employment scope
    The job has exactly this position or employment scope.

Location
    An input like "12345 - Sample Town" is split at the hyphen. Jobs match
    if the ZIP code of their address is "12345" **or** the city contains
    "Sample Town". A number only is searched as ZIP code, other input as
    part of the city.

Keyword
    The input is split at spaces. Every word must be found (AND). Each word
    may be found in the title, description, offer, requirements, further
    information, application guidelines, or the street or city of the
    address.

The list and the search result are sorted by application deadline, jobs
without an application deadline first. Jobs with the same application
deadline are sorted by their UID, so every job appears on exactly one page
of the page browser. Remembered jobs keep their place in this order.

The options of the select boxes in the search form (occupational fields
with the number of jobs, positions, employment scopes and job locations)
are calculated in the database with the same conditions as the listed
jobs. With :confval:`plugin-maxentries`, they only contain values of the
listed jobs.

The search form sends these arguments to the action :php:`search`, which
you can also use in own forms:

*   :code:`tx_jobboard_jobboard[jobArea]` (UID of an occupational field)
*   :code:`tx_jobboard_jobboard[jobRole]` (UID of a position)
*   :code:`tx_jobboard_jobboard[jobType]` (UID of an employment scope)
*   :code:`tx_jobboard_jobboard[address]` (ZIP code and/or city)
*   :code:`tx_jobboard_jobboard[searchWord]` (keywords)

..  _configuration-plugin-caching:

Caching of the job list
=======================

The list and the detail view are stored in the page cache of TYPO3. The
page browser loads only the jobs of the current page from the database.

Changed records
    TYPO3 does not know which pages show the jobs of the storage folder.
    Add :typoscript:`TCEMAIN.clearCacheCmd` with the UIDs of all pages with
    the job board to the page TSconfig of the storage folder, see
    :ref:`configuration-page-tsconfig-cache`. Otherwise, changes of editors
    and of the :ref:`job import <import>` appear only when the cache
    expires.

Dates of the jobs
    The list changes without any record being saved: a job appears at its
    publish date and disappears at its expiration date, after its
    application deadline or after its end of employment. The page cache of
    TYPO3 (:typoscript:`config.cache`) only considers the publish and
    expiration dates, not application deadlines or ending dates. The event
    listener
    :php:`JWeiland\Jobboard\EventListener\LimitJobListCacheLifetimeEventListener`
    therefore limits the cache lifetime of pages with the content element
    to the next of these points in time: the next publish or expiration
    date of a job, or the midnight after the earliest upcoming application
    deadline or end of employment. Only jobs of the storage pages of the
    content element are considered.

Salary grades
    Publish and expiration dates of salary grades and steps are applied by
    the daily command :bash:`jobboard:salary:update`. It saves the changed
    jobs with the DataHandler, so :typoscript:`TCEMAIN.clearCacheCmd`
    clears the cache, see :ref:`salary-update`.
