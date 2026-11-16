:navigation-title: Why is my job not shown?

..  include:: /Includes.rst.txt
..  _editor-visibility:

========================
Why is my job not shown?
========================

A job is listed in the frontend only if **all** of these conditions are
met:

#.  The job is stored in the storage folder of the site (or up to three
    levels below it), see :confval:`jobboard-storagepid`.
#.  The job is enabled, and its publish and expiration date (tab
    :guilabel:`Access`) allow it.
#.  The job has a title.
#.  The :guilabel:`Application deadline` is empty, today or in the future.
#.  The job has salary information. This condition only applies if the
    site setting :confval:`jobboard-salary-required` is enabled, which is
    the default:

    *   Salary mode "Salary grade": at least one selected salary grade is
        enabled, within its validity period (see
        :ref:`editor-salary-validity`) and has an amount: a fixed amount or
        at least one enabled step with an amount. A grade or step that
        expires within the next 24 hours already counts as expired.
    *   Salary mode "Free salary entry": minimum or maximum salary is
        greater than 0.

#.  If the content element restricts the list to some occupational fields:
    the job has at least one of them.
#.  The limit :guilabel:`Max entries to show` of the content element is not
    reached yet.

The dates are compared with the current day. A job with the application
deadline "2026-11-20" is listed until the end of 20 November 2026.

The :guilabel:`Start of employment` and the :guilabel:`End of employment`
do **not** influence whether a job is shown.

..  versionchanged:: 0.0.5
    The :guilabel:`End of employment` no longer hides a job. It lies long
    after the position has been filled and is shown in the detail view
    for information only.

..  _editor-visibility-deadline:

Detail view after the application deadline
===========================================

..  versionchanged:: 0.0.4
    The detail view no longer shows the details of a job whose
    application deadline has passed.

A job with an expired :guilabel:`Application deadline` is no longer
listed, but its detail view can still be opened by a direct link, for
example from a search engine, a job portal or a bookmark. Instead of the
job details, the detail view then shows only the job title and a notice
that the application deadline has expired. The "Apply online" link and
the structured data for search engines are not rendered. The rule is the
same as for the list: applying is possible until the end of the deadline
day.

Only the application deadline is checked here.

..  _editor-visibility-detail:

Detail view returns "Page not found"
====================================

If the site setting :confval:`jobboard-salary-required` is enabled (the
default), the detail view of a job without salary information returns the
HTTP status 404 (page not found). This also happens for jobs whose
salary grade has expired in the meantime. :guilabel:`Related jobs`
without salary information are not linked in the detail view.

..  _editor-visibility-cache:

Changes are not visible yet
===========================

The list and the detail view of a job are cached. When you save a record
in the storage folder, TYPO3 clears the cache of the job board pages. If
changes appear only after some time, ask your administrator to configure
:typoscript:`TCEMAIN.clearCacheCmd` for the storage folder, see
:ref:`configuration-page-tsconfig-cache`.

When the publish or expiration date of a salary grade or step is reached,
the affected jobs are updated by a command that runs once a day, see
:ref:`salary-update`.
