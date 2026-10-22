:navigation-title: Job board

..  include:: /Includes.rst.txt
..  _start:

=========
Job board
=========

:Extension key:
    jobboard

:Package name:
    jweiland/jobboard

:Version:
    |release|

:Language:
    en

:Author:
    Stefan Froemken

:License:
    This extension documentation is published under the
    `CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>`__
    (Creative Commons) license. The extension itself is licensed under
    GPL-2.0-or-later.

:Rendered:
    |today|

----

The extension shows a searchable list of job offers on your TYPO3 website.
Visitors filter the list by occupational field, position, employment scope,
location and keyword, open a detail view with all facts including the
salary, and can export a job as PDF. Job locations are maintained as
addresses of EXT:tt_address and can optionally be shown on a map with
EXT:maps2. A console command can import jobs from external XML sources.

..  warning::

    This extension is in **alpha** state (version 0.0.1) and supports
    TYPO3 13.4 LTS only. PHP classes, template variables and settings can
    still change without notice until the first stable release.

----

**Table of contents**

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What the job board does, which features it offers and how it looks
        in the frontend.

    ..  card:: :ref:`Installation <installation>`

        Install the extension with Composer, add the site set and prepare
        the storage folder.

    ..  card:: :ref:`Configuration <configuration>`

        Site sets, site settings, TypoScript, content element options,
        routing and page title.

    ..  card:: :ref:`Editors <editor>`

        Maintain jobs, salary grades, benefits and addresses and insert the
        "Job board" content element.

    ..  card:: :ref:`Administration <administration>`

        Templates, PDF export, address search, job import and upgrade
        wizards.

    ..  card:: :ref:`Developers <developer>`

        Connect your own job source to the import and extend the job
        board.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Editor/Index
    Administration/Index
    Developer/Index
    Contributing/Index
    KnownProblems/Index
    Changelog/Index

..  Meta Menu

..  toctree::
    :hidden:

    Sitemap
