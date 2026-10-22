:navigation-title: Site sets

..  include:: /Includes.rst.txt
..  _configuration-site-sets:

=========
Site sets
=========

The extension ships two site sets in :path:`EXT:jobboard/Configuration/Sets/`.

..  list-table::
    :header-rows: 1
    :widths: 25 25 50

    *   -   Set name
        -   Label
        -   Purpose
    *   -   :yaml:`jweiland/jobboard`
        -   Jobboard - Main
        -   Main set: TypoScript of the plugin, site settings and page title
            provider. Add this set to your site.
    *   -   :yaml:`jweiland/jobboard-pdf`
        -   Jobboard - PDF Generation
        -   Page type for the PDF version of a job. Loaded automatically as
            dependency of the main set.

..  _configuration-site-sets-dependencies:

Dependencies
============

:yaml:`jweiland/jobboard` depends on :yaml:`jweiland/jobboard-pdf`.

If EXT:maps2 is installed, :yaml:`jweiland/jobboard` also loads the sets
:yaml:`jweiland/maps2-default` and :yaml:`jweiland/maps2-googlemaps`
(optional dependencies). Without EXT:maps2, these sets are skipped.

..  attention::

    With EXT:maps2 installed, the main set always loads the Google Maps set
    of EXT:maps2. See :ref:`installation-maps2`.

The PDF version of a job is rendered with EXT:pdfviewhelpers. Add the set
:yaml:`bithost/pdfviewhelpers` to your site, see
:ref:`installation-site-set` and :ref:`pdf-export`.
