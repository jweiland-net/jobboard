:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS (:composer:`typo3/cms-core` 13.4.34 or higher)
*   PHP 8.2 or higher with the PHP extension :code:`intl`
*   :composer:`friendsoftypo3/tt-address`: job locations are addresses
    (required dependency, installed automatically)
*   :composer:`bithost-gmbh/pdfviewhelpers`: renders the PDF version of a
    job (required dependency, installed automatically)
*   Optional: :composer:`jweiland/maps2` shows the job locations on a map

The extension can only be installed with Composer.

..  _installation-composer:

Install the extension with Composer
===================================

..  code-block:: bash
    :caption: Install the development version

    composer require jweiland/jobboard:dev-main

..  note::

    There is no tagged release of the extension yet. Until the first
    release, install the branch :code:`dev-main`. As soon as a release is
    available, use :bash:`composer require jweiland/jobboard`.

Then set up the extension. This creates the database tables of the job
board and adds the column :sql:`import_key` to the table :sql:`tt_address`:

..  code-block:: bash
    :caption: Set up the extension and its database tables

    vendor/bin/typo3 extension:setup

..  _installation-site-set:

Add the site sets
=================

The extension does not provide static TypoScript templates. Add the site
set :guilabel:`Jobboard - Main` (:yaml:`jweiland/jobboard`) to your site,
either in the backend module :guilabel:`Site Management > Sites` (tab
:guilabel:`Sets`) or in the site configuration file.

The PDF export of a job uses the TypoScript of EXT:pdfviewhelpers. Add its
site set :guilabel:`EXT:pdfviewhelpers` (:yaml:`bithost/pdfviewhelpers`)
as well:

..  literalinclude:: _snippets/_site-config.yaml
    :language: yaml
    :caption: config/sites/<site-identifier>/config.yaml

The set :yaml:`jweiland/jobboard` loads the set
:yaml:`jweiland/jobboard-pdf` automatically. See
:ref:`configuration-site-sets` for details.

..  _installation-storage-folder:

Create a storage folder
=======================

#.  Create a page of type :guilabel:`Folder` in the page tree, for example
    "Job board". All records of the job board are stored here: jobs,
    occupational fields, positions, salary grades, benefits and the
    addresses of the job locations.

#.  Set the site setting :confval:`jobboard-storagepid` to the UID of this
    folder (:guilabel:`Site Management > Settings`, category
    :guilabel:`Jobboard > Pages`):

    ..  literalinclude:: _snippets/_settings.yaml
        :language: yaml
        :caption: config/sites/<site-identifier>/settings.yaml

    The default value :yaml:`'0'` means that no jobs are found.

..  _installation-routing:

Import the route enhancer
=========================

For speaking URLs of the detail view, import the shipped route enhancer in
your site configuration. See :ref:`configuration-routing`.

..  _installation-clear-cache:

Clear the cache of the job board pages
======================================

List and detail view are cached. Add the UIDs of all pages with the job
board to the page TSconfig of the storage folder, so that changes in the
folder clear the cache of these pages:

..  code-block:: typoscript
    :caption: Page TSconfig of the storage folder

    TCEMAIN.clearCacheCmd = 42

See :ref:`configuration-page-tsconfig-cache` for details.

..  _installation-salary-update:

Schedule the daily salary update
================================

The validity of salary grades and steps decides which jobs are listed.
Create a task :guilabel:`Execute console commands` in the backend module
:guilabel:`System > Scheduler`, select :code:`jobboard:salary:update` and
let it run once a day, preferably at night. See :ref:`salary-update`.

..  _installation-maps2:

Optional: show job locations on a map
=====================================

The map is optional. If you want to show the job locations on a map,
install EXT:maps2:

..  code-block:: bash
    :caption: Install EXT:maps2

    composer require jweiland/maps2
    vendor/bin/typo3 extension:setup

The job board does not select a map provider. Add one of the provider
sets of EXT:maps2 to your site, either in the backend module
:guilabel:`Site Management > Sites` (tab :guilabel:`Sets`) or in the site
configuration file:

:yaml:`jweiland/maps2-openstreetmap`
    Label "Maps2 - OpenStreetMap". Needs no API key.

:yaml:`jweiland/maps2-googlemaps`
    Label "Maps2 - Google Maps".

Both sets load the set :yaml:`jweiland/maps2-default` automatically.

..  literalinclude:: _snippets/_site-config-maps2.yaml
    :language: yaml
    :caption: config/sites/<site-identifier>/config.yaml

..  important::

    If EXT:maps2 is installed, the site must contain one of its provider
    sets. Without such a set, EXT:maps2 has neither TypoScript nor site
    settings on this site and cannot render the map.

..  attention::

    If you use Google Maps, you need Google Maps API keys, and the
    browsers of the visitors load the map from Google. Check the privacy
    information and the consent handling of your site. See the
    documentation of EXT:maps2 for the API keys and the options to ask
    visitors for consent before a map is loaded.

Configure EXT:maps2 as described in its
`documentation <https://docs.typo3.org/p/jweiland/maps2/main/en-us/>`__.
Its extension configuration (option :code:`mapProvider`) decides which
map providers are available for the map locations in the backend. Choose
a provider set that matches this option.

The address records of the job locations get an additional field for the
map location. EXT:maps2 determines the position when an address is saved
in the backend, see :ref:`editor-addresses-map`. The map shows the
locations of the jobs on the current page of the list, one marker per
address. A click on a marker opens an info window with links to the open
jobs at this location, see :ref:`templates-info-window`.

The map is shown as soon as at least one listed job has an address with a
map location. If none of the listed jobs has one, the job board shows the
message "The map cannot be shown" instead of the map. Save the addresses
in the backend, so that EXT:maps2 determines their positions.

Without EXT:maps2, the job board works the same, but shows no map and the
addresses have no map location field.

..  _installation-first-steps:

First steps
===========

#.  Flush all caches: :bash:`vendor/bin/typo3 cache:flush`.
#.  Create the records in the storage folder, see :ref:`editor`.
#.  Insert the content element "Job board" on a page, see
    :ref:`editor-content-element`.
#.  If you migrate from EXT:jobfair2, run the upgrade wizards, see
    :ref:`upgrade`.
#.  If the storage folder already contains jobs, for example after a
    migration or a direct database import, calculate their salary once:
    :bash:`vendor/bin/typo3 jobboard:salary:update --all`.
