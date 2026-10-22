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
*   Occupational fields and employment scopes are assigned by their title.
    They must already exist in TYPO3, the import does not create them.

After all sources have been processed, the import **deletes all imported
jobs of the storage folder that were not part of this import**. Each
import always delivers all valid jobs of a source, so jobs that are no
longer delivered have ended. Jobs that editors created manually are never
deleted by the import.

All records are written with the TYPO3 DataHandler. Hooks and events of
other extensions, for example for search indexing, are triggered for
every imported record. The import does not clear caches itself. Configure
:typoscript:`TCEMAIN.clearCacheCmd` for the storage folder, see
:ref:`configuration-page-tsconfig-recipes`.

..  note::

    Imported jobs are only shown if they have salary information, like all
    other jobs. See :ref:`editor-visibility`.

..  _import-logging:

Logging
=======

The import logs to the channel :php:`JWeiland.Jobboard`. If you did not
configure a writer for this channel yourself, messages of level INFO and
higher are written to the file :file:`var/log/typo3_jobboard_<hash>.log`.
A job that cannot be saved is logged and skipped, the import continues
with the next job.
