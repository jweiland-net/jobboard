:navigation-title: Job import

..  include:: /Includes.rst.txt
..  _import:

==========
Job import
==========

The job board can import jobs from external XML sources. Each source is a
small PHP class that a developer adds to a site package, see
:ref:`developer-import-api`. Without such a class, the import does
nothing.

..  _import-command:

Run the import
==============

..  code-block:: bash
    :caption: Import jobs into the storage folder with UID 23

    vendor/bin/typo3 jobboard:import:jobs:mhm 23

The argument is the UID of the page that stores the imported jobs **and**
their addresses. Use the storage folder of the job board, see
:confval:`jobboard-storagepid`. The command name contains "mhm" for
historical reasons, the command works with any source.

The command processes all registered sources one after the other and
always ends with the exit code :code:`0`. Problems are written to the log,
see :ref:`import-logging`.

..  _import-scheduler:

Run the import regularly
========================

Use the task :guilabel:`Execute console commands` of the TYPO3 scheduler
(EXT:scheduler) and select :code:`jobboard:import:jobs:mhm`, or call the
command with a cron job of your server. A typical interval is once per
hour or once per day.

..  _import-behaviour:

What the import does
====================

For each job of a source:

*   The address of the primary location is created or updated in the
    storage folder. It is identified by its import key.
*   The job is created or updated. It is identified by its vacancy ID,
    which is stored as :code:`<source name>_<ID of the source>`, so several
    sources can share one storage folder.
*   Imported jobs are marked as imported (:guilabel:`Is imported record?`).
*   Occupational fields and employment scopes are assigned by their title
    in any language. If no record with this title exists, the import
    creates it in the storage folder. Translate or rename it there, the
    import finds it by its translated title, too.

After all sources have been processed, the import **deletes the imported
jobs of a source that this source no longer delivers**. Each source always
delivers all of its valid jobs, so jobs that are no longer delivered have
ended:

*   Jobs are only deleted for sources that were fetched successfully. If a
    source cannot be fetched (invalid URL, failed request or invalid XML),
    the error is logged and **all jobs of this source are kept**.
*   A source that delivers valid XML without any job has no valid jobs any
    more. All its imported jobs are deleted.
*   A delivered job that cannot be saved is kept with its previous data.
*   Jobs that editors created manually are never deleted by the import.

The jobs of a source are recognized by the prefix of their vacancy ID, the
:php:`NAME` of the source, see :ref:`developer-import-mapping`.

All records are written with the TYPO3 DataHandler. Hooks and events of
other extensions, for example for search indexing, are triggered for
every imported record. This also calculates the effective salary of the
imported jobs, see :ref:`salary-update`. The import does not clear caches
itself. Configure :typoscript:`TCEMAIN.clearCacheCmd` for the storage
folder, see :ref:`configuration-page-tsconfig-cache`.

..  note::

    Like all other jobs, imported jobs are only shown if they have salary
    information, as long as the site setting
    :confval:`jobboard-salary-required` is enabled (the default). See
    :ref:`editor-visibility`.

..  _import-logging:

Logging
=======

The import logs to the channel :php:`JWeiland.Jobboard`. If you did not
configure a writer for this channel yourself, messages of level INFO and
higher are written to the file :file:`var/log/typo3_jobboard_<hash>.log`.
These events are logged, the import continues afterwards:

*   A source cannot be fetched (invalid URL, failed request, invalid XML).
    The source is skipped and its existing jobs are kept.
*   A job cannot be saved. It is skipped; if it already exists, it is kept
    with its previous data.
*   A job has no vacancy ID or no primary location. It is skipped with a
    warning; if it already exists, it is kept with its previous data.
*   A job or its address has an invalid email address or URL. The value is
    removed with a warning, the job is imported without it.
*   An occupational field or employment scope is created (INFO).

The command shows the number of skipped jobs as warning and still ends
successfully, as skipped jobs are a problem of the source data. It only
ends with an error (exit code 1, shown as failed in the scheduler), if a
source could not be fetched or a job could not be saved.
