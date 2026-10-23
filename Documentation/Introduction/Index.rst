:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

..  _what-it-does:

What does the job board do?
===========================

The extension adds a content element "Job board" (CType
:php:`jobboard_jobboard`). It lists all current job offers of a storage
folder, lets visitors filter the list and opens a detail view for each job.

..  figure:: /Images/Introduction/list.png
    :alt: Job list with the search form on the left and job cards on the right
    :class: with-border with-shadow
    :zoom: lightbox

    Job list with search form. Example output of a site package that renders
    the shipped card layout.

..  _features:

Features
========

Job list
    The list shows all jobs that are currently open (see
    :ref:`editor-visibility`). By default it is rendered as a table with the
    columns job title, application deadline, ZIP code, city, employer,
    employment scope and "Remember". A card layout ships as an alternative
    partial, see :ref:`templates-card-layout`.

Search form
    Visitors filter by occupational field, position, employment scope and
    location and can enter keywords. The select boxes only offer values of
    jobs that are currently listed. The location is either a text field with
    suggestions of ZIP codes and cities or a select box with all job
    locations. Optionally, each occupational field shows the number of
    matching jobs, for example "IT (3)".

Remember jobs
    In the table layout, visitors can tick "Remember" for single jobs.
    Remembered jobs are highlighted with a tinted background and a colored
    bar in front of the row. A link "Remembered jobs" with the number of
    remembered jobs leads to a view of all remembered jobs, where visitors
    can also remove them. The selection is stored in the TYPO3 session of
    the visitor, see :ref:`remember-jobs`.

Map
    If the optional extension :composer:`jweiland/maps2` is installed, the
    locations of the jobs on the current page of the list are shown on a
    map, one marker per address. A click on a marker opens an info window
    with the number of jobs at this location and links to them. You choose
    the map provider, OpenStreetMap or Google Maps, with a site set of
    EXT:maps2, see :ref:`installation-maps2`.

Detail view
    All facts of a job grouped in sections: description, offer,
    requirements, job information, salary, benefits, employer, contact
    person, application information, files and related jobs. The detail
    view contains structured data (JSON-LD, schema.org
    :code:`JobPosting`), a link to a PDF version and an "Apply online" link
    to the page with your application form.

Salary information
    Each job needs salary information to be shown: either one or more tariff
    salary grades (with or without steps) or a free minimum and maximum
    amount. Jobs without salary information are never shown. The extension
    is built for employers who must publish the salary of each job offer.
    A daily console command applies the validity periods of salary grades,
    see :ref:`salary-update`.

Caching
    The list and the detail view are cached and identical for all
    visitors. The page browser loads only the jobs of the current page
    from the database, see :ref:`configuration-plugin-caching`.

Speaking URLs and page title
    A shipped route enhancer creates URLs like :file:`/jobs/web-developer-m-f-d`
    and the job title is used as page title of the detail view.

Job import
    A console command imports jobs from external XML sources on a regular
    basis. No source ships with the extension, a developer adds a small PHP
    class per source (see :ref:`developer-import-api`).

Migration from EXT:jobfair2
    Upgrade wizards migrate records and content elements of the predecessor
    extension EXT:jobfair2, see :ref:`upgrade`.

..  _screenshots:

Screenshots
===========

All frontend screenshots show the output of a custom site package theme
that renders the shipped card layout. The default table layout looks
different, but offers the same data.

..  figure:: /Images/Introduction/job-area-count.png
    :alt: Select box of occupational fields with the number of jobs
    :class: with-border with-shadow
    :zoom: lightbox

    Occupational fields with the number of jobs per field

..  figure:: /Images/Introduction/autocomplete.png
    :alt: Location field with suggestions of ZIP codes and cities
    :class: with-border with-shadow
    :zoom: lightbox

    Suggestions for the location field after typing three characters

..  figure:: /Images/Introduction/search-result.png
    :alt: Search result with selected occupational field and employment scope
    :class: with-border with-shadow
    :zoom: lightbox

    Search result for an occupational field and an employment scope

..  figure:: /Images/Introduction/detail.png
    :alt: Detail view of a job with employment scope, contract type and salary
    :class: with-border with-shadow
    :zoom: lightbox

    Detail view of a job (custom detail template of a site package)

..  _requirements-overview:

Requirements at a glance
========================

*   TYPO3 13.4 LTS, Composer-based installation
*   PHP 8.2 or higher with the :code:`intl` extension
*   :composer:`friendsoftypo3/tt-address` for job locations
*   :composer:`bithost-gmbh/pdfviewhelpers` for the PDF export
*   Optional: :composer:`jweiland/maps2` for the map

See :ref:`installation` for all steps.
