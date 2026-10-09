:navigation-title: Routing

..  include:: /Includes.rst.txt


..  _admin-routing:

=====================================
Speaking URLs for the job detail view
=====================================

Without further configuration, a link to the detail view of a job contains
the plugin arguments as query parameters, for example
:samp:`/jobs?tx_jobboard_jobboard[action]=detail&tx_jobboard_jobboard[job]=12&cHash=...`.
With the route enhancer shipped by Jobboard, the same link becomes
:samp:`/jobs/web-developer-m-w-d`.

Target group: **Administrators / Integrators**


..  _admin-routing-slug:

The URL segment of a job
========================

Every job record has a slug field :sql:`path_segment` (TCA type `slug`),
which is generated from the job title. Editors find it as
:guilabel:`URL segment` (German: :guilabel:`URL-Segment`) in the
:guilabel:`Job Details` palette of the job record.

*   New jobs get their URL segment automatically, including jobs created
    by the scheduled import (it writes through the TYPO3
    :php:`DataHandler`).
*   If the title changes later, the URL segment is kept on purpose, so
    existing URLs stay valid. Editors can regenerate it with the button
    next to the field.
*   The URL segment is unique across the whole table (:yaml:`eval: unique`),
    not only per storage folder. The
    :yaml:`PersistedAliasMapper` used by the route enhancer resolves slugs
    table-wide, so two jobs in different folders must not share a slug.


..  _admin-routing-upgrade-wizard:

Generate URL segments of existing jobs
======================================

Jobs created before the URL segment was introduced have an empty
:sql:`path_segment`. The upgrade wizard
:guilabel:`[jobboard] Generate URL segments (slugs) of jobs.`
(identifier `jweilandJobboardJobPathSegmentUpdate`) fills them. Run it after
the database schema has been updated:

..  code-block:: bash
    :caption: Update the database schema and run the upgrade wizard

    vendor/bin/typo3 extension:setup -e jobboard
    vendor/bin/typo3 upgrade:run jweilandJobboardJobPathSegmentUpdate

The wizard is also available in the backend under :guilabel:`Admin Tools >
Upgrade > Upgrade Wizard`.


..  _admin-routing-route-enhancer:

The shipped route enhancer
==========================

Jobboard ships a ready-made route enhancer with the key
:yaml:`JobboardPlugin` in
:file:`EXT:jobboard/Configuration/Routes/Default.yaml`:

..  code-block:: yaml
    :caption: EXT:jobboard/Configuration/Routes/Default.yaml (without header comment)

    routeEnhancers:
      JobboardPlugin:
        type: Extbase
        extension: Jobboard
        plugin: Jobboard
        routes:
          -
            routePath: '/{job_title}'
            _controller: 'Jobboard::detail'
            _arguments:
              job_title: job
        requirements:
          job_title: '^[[:alnum:]\-]+$'
        defaultController: 'Jobboard::list'
        aspects:
          job_title:
            type: PersistedAliasMapper
            tableName: tx_jobboard_domain_model_job
            routeFieldName: path_segment

..  note::
    The file intentionally does not define :yaml:`limitToPages`. When
    imports are merged, lists are appended instead of replaced, so a
    default value in the shipped file could not be overridden by your site
    configuration (see below).

TYPO3 13.4 has no mechanism for extensions to register route enhancers
automatically, so the file has to be included in the site configuration.


..  _admin-routing-import:

Recommended: Import the route enhancer
--------------------------------------

Import the file in the site configuration and add the project-specific
values below the same enhancer key:

..  code-block:: yaml
    :caption: config/sites/<identifier>/config.yaml

    imports:
      - { resource: 'EXT:jobboard/Configuration/Routes/Default.yaml' }

    routeEnhancers:
      JobboardPlugin:
        limitToPages: [42]

Replace :yaml:`42` with the UID of the page containing the
:guilabel:`Job board` content element. Things to know about imports:

*   Scalar values in :file:`config.yaml` override the imported ones. Lists
    (like :yaml:`limitToPages`) are appended to the imported lists, not
    replaced
    (:php:`ArrayUtility::replaceAndAppendScalarValuesRecursive()`).
*   Imports work nested. You can import the file from a shared YAML file
    (for example :file:`routeenhancers.yaml`), which is itself imported
    by :file:`config.yaml`.
*   The :yaml:`imports` key is preserved when the site configuration is
    saved in the backend :guilabel:`Sites` module.
*   A wrong :yaml:`resource` path is only logged, no exception is thrown.
    The enhancer is then silently missing and links fall back to query
    parameters. Check the path if URLs are not speaking.

Flush all caches after changing the site configuration:

..  code-block:: bash

    vendor/bin/typo3 cache:flush


..  _admin-routing-copy:

Alternative: Copy the route enhancer
------------------------------------

If you need full control, copy the :yaml:`JobboardPlugin` block of
:file:`EXT:jobboard/Configuration/Routes/Default.yaml` into the
:yaml:`routeEnhancers` section of your site configuration and adapt it.
Note that later changes to the shipped file are not applied to your copy.


..  _admin-routing-pdf:

Optional: Speaking URLs for the PDF view
========================================

The PDF view of a job (Site Set :guilabel:`Jobboard - PDF`, page type
`1575903427`) uses the same detail route, so the job part of the PDF URL
is speaking as well. The page type itself stays a query parameter
(:samp:`/jobs/web-developer-m-w-d?type=1575903427`), unless you map it with
a :yaml:`PageType` decorator:

..  code-block:: yaml
    :caption: config/sites/<identifier>/config.yaml

    routeEnhancers:
      PageTypeSuffix:
        type: PageType
        default: ''
        map:
          '/': 0
          job.pdf: 1575903427

If your site configuration already contains a :yaml:`PageType` enhancer,
only add the :yaml:`job.pdf: 1575903427` entry to its :yaml:`map`.


..  _admin-routing-outlook:

Outlook: Route enhancers in site sets
=====================================

Since TYPO3 14.1, a site set can provide route enhancers in a file
:file:`route-enhancers.yaml`. Jobboard currently supports TYPO3 13.4 only,
so the import described above is required.
