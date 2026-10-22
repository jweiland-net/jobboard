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

..  _installation-maps2:

Optional: show job locations on a map
=====================================

The map is optional. If you want to show the job locations on a map,
install EXT:maps2:

..  code-block:: bash
    :caption: Install EXT:maps2

    composer require jweiland/maps2
    vendor/bin/typo3 extension:setup

If EXT:maps2 is installed, the site set :yaml:`jweiland/jobboard`
automatically loads the sets :yaml:`jweiland/maps2-default` and
:yaml:`jweiland/maps2-googlemaps`. The address records of the job
locations get an additional field for the map location. The map is shown
as soon as at least one listed job has an address with a map location.
Configure EXT:maps2 as described in its
`documentation <https://docs.typo3.org/p/jweiland/maps2/main/en-us/>`__.

..  attention::

    With EXT:maps2, the job board uses **Google Maps**. You need a Google
    Maps API key, and visitors' browsers load the map from Google. Check
    the privacy information and the consent handling of your site.

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
