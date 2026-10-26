:navigation-title: Salary update

..  include:: /Includes.rst.txt
..  _salary-update:

=======================================
Update the effective salary of the jobs
=======================================

If the site setting :confval:`jobboard-salary-required` is enabled (the
default), the job list filters the jobs without salary information in the
database query. For this, every job stores its **effective salary**: the
lowest and
the highest amount that the frontend shows. The values are calculated from
the salary mode, the minimum and maximum salary and the valid salary grades
and steps of the job, with the same rules as the salary range of the
detail view, see :ref:`editor-salary`.

With this setting enabled, jobs without an effective salary are not
listed, and their detail view returns the HTTP status 404 (page not
found). With the setting disabled, these jobs are shown like all other
open jobs. The effective salary is calculated in both cases, so keep the
daily command running either way.

..  _salary-update-columns:

Database columns
================

The table :sql:`tx_jobboard_domain_model_job` has these columns. Editors
see them read-only at the end of the first tab of a job, in the palette
:guilabel:`Effective salary`, as a control. They cannot be edited. The
values are updated when the job, a salary grade or a step is saved, and by
the scheduler task.

:sql:`effective_salary_min`, :sql:`effective_salary_max`
    Lowest and highest amount. A job is listed if one of them is greater
    than :code:`0`.

:sql:`effective_salary_valid_until`
    Next point in time at which the publish date or expiration date of one
    of the salary grades or steps of the job changes the result. :code:`0`
    means that no change is scheduled.

Translations of a job always get the values of the default language.

..  _salary-update-look-ahead:

Look-ahead of 24 hours
======================

A salary grade or step whose expiration date is within the next 24 hours
already counts as expired. Jobs that depend on such a grade disappear one
day before the grade expires. This gives editors a day to assign the
grades of the new pay scale, and a daily recalculation is enough to keep
all jobs up to date. A publish date in the future counts from the exact
point in time, without look-ahead.

..  _salary-update-datahandler:

Update on saving records
========================

A DataHandler hook
(:php:`JWeiland\Jobboard\Hook\UpdateEffectiveSalaryHook`) recalculates the
affected jobs whenever jobs, salary grades, salary steps or salary tables
are created, changed, hidden, deleted, restored, moved, copied or
localized. This also applies to the :ref:`job import <import>`, which
saves all records with the DataHandler. Changes in a workspace are
calculated when they are published to the live workspace.

The values are written with the DataHandler as well, so
:typoscript:`TCEMAIN.clearCacheCmd` of the storage folder clears the cache
of the job board pages, see :ref:`configuration-page-tsconfig-cache`.

..  _salary-update-command:

Daily update with the console command
=====================================

Publish and expiration dates of salary grades and steps take effect
without saving any record. The console command
:bash:`jobboard:salary:update` recalculates all jobs whose
:sql:`effective_salary_valid_until` is due within the next 24 hours.

..  code-block:: bash
    :caption: Recalculate the jobs with upcoming salary changes

    vendor/bin/typo3 jobboard:salary:update

..  important::

    Run this command **once a day**, preferably at night. Create a task
    :guilabel:`Execute console commands` in the backend module
    :guilabel:`System > Scheduler` (EXT:scheduler), select
    :code:`jobboard:salary:update` and set a daily frequency, for example
    the cron expression :code:`30 2 * * *`. Alternatively, call the command
    with a cron job of your server.

The command writes only changed jobs, with the DataHandler. Therefore
:typoscript:`TCEMAIN.clearCacheCmd` of the storage folder also applies to
the nightly run.

The option :bash:`--all` recalculates every job. Run it once after
installing or updating the extension and after migrating records with an
upgrade wizard, see :ref:`upgrade-update`:

..  code-block:: bash
    :caption: Recalculate the effective salary of all jobs

    vendor/bin/typo3 jobboard:salary:update --all
