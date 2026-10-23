:navigation-title: Remembered jobs

..  include:: /Includes.rst.txt
..  _remember-jobs:

===============
Remembered jobs
===============

In the table layout, visitors can tick "Remember" for single jobs. The list
and the search result show the remembered jobs of this visitor first, on
the first page, followed by all other jobs. Remembered jobs are highlighted
and their checkbox is checked.

How it works
============

The JavaScript file
:file:`EXT:jobboard/Resources/Public/JavaScript/remember-jobs.js` sends a
POST request with the HTTP header :code:`jobboard-remember-job` whenever a
checkbox is changed. The request goes to the URL of the current page, which
the table provides in the attribute :html:`data-jobboard-remember-url`. If
the attribute is missing, the path of the current page in the browser is
used. The PSR-15 middleware
:php:`JWeiland\Jobboard\Middleware\RememberJobMiddleware` (identifier
:code:`jweiland/jobboard-remember-job`) answers every frontend request with
this header. It runs before the page is resolved, so it does not render the
page.

Form fields of the request:

:code:`job`
    UID of the job in the default language. This UID is valid in all
    languages of the site.

:code:`remember`
    :code:`1` remembers the job, :code:`0` forgets it.

The answer is always a JSON object with the UIDs of all remembered jobs. In
case of an error, it also contains an error code:

..  code-block:: bash
    :caption: Example request

    curl -X POST -H 'jobboard-remember-job: 1' \
        -d 'job=12&remember=1' https://example.org/jobs

..  code-block:: json
    :caption: Example response

    {"rememberedJobs": [7, 12]}

..  list-table::
    :header-rows: 1

    *   -   HTTP status
        -   Error code
        -   Reason
    *   -   200
        -
        -   The job was remembered or forgotten.
    *   -   400
        -   :code:`invalidArguments`
        -   :code:`job` is not a positive integer or :code:`remember` is
            neither :code:`1` nor :code:`0`.
    *   -   404
        -   :code:`unknownJob`
        -   The job does not exist, is not visible or is not stored in the
            pages of the site setting :confval:`jobboard-storagepid` and
            their sub pages (3 levels, like the content element).
    *   -   405
        -   :code:`methodNotAllowed`
        -   The request is not a POST request.
    *   -   409
        -   :code:`limitReached`
        -   The visitor has remembered 50 jobs already.

If a request fails, :file:`remember-jobs.js` shows the remembered jobs of
the answer. Without an answer (network error), it resets the checkbox.

The order of the list changes only when the page is loaded again. A job
that is ticked stays in its place until then. Moving it to the top at once
would move the row away from the mouse pointer and the keyboard focus, and
on the second page the job would have to vanish from the current page.

Storage in the session
======================

The remembered jobs are stored in the session data of the TYPO3 frontend
user, also for visitors who are not logged in. The session data has the key
:code:`tx_jobboard_remembered_jobs` and contains a list of job UIDs only.
When the first job is remembered, TYPO3 creates a session and sets its
session cookie :code:`fe_typo_user`. When the last job is forgotten and the
session contains no other data, TYPO3 removes the session and the cookie.

*   For visitors who are not logged in, the cookie :code:`fe_typo_user` is
    a session cookie of the browser, so the remembered jobs are lost when
    the browser is closed. The garbage collection of TYPO3 removes the
    session from the database at the earliest
    :php:`$GLOBALS['TYPO3_CONF_VARS']['FE']['sessionDataLifetime']` seconds
    (default: 1 day) after the last change.
*   For logged-in frontend users, the remembered jobs belong to their
    login session and are lost after logout.

:code:`fe_typo_user` is a technically necessary cookie of TYPO3. Mention
it in the privacy information of your site.

..  _remember-jobs-caching:

Caching
=======

As the list and the search result depend on the session of the visitor,
the actions :php:`list` and :php:`search` of the content element are not
cached, see :ref:`configuration-plugin`. The page itself and the detail view
are still cached. Every request of the list or search result loads the jobs from
the database and renders them. With a very large number of jobs, limit
the list with :confval:`plugin-maxentries`.

Security
========

*   Only UIDs of existing, visible jobs in the default language from the
    storage pages of the current site can be remembered. The storage pages
    are taken from the site setting :confval:`jobboard-storagepid`, never
    from the request. Other data is never written to the session.
*   Not more than 50 jobs can be remembered. Jobs that are not visible
    anymore are removed from the session when the next job is remembered.
*   A request that changes nothing, for example an invalid or unknown job,
    does not create a session.
*   The middleware only accepts POST requests with the custom header. A
    form or link on another website cannot send this header. A script on
    another website has to ask the browser for permission first (CORS
    preflight request), which TYPO3 does not grant. Additionally, browsers
    do not send the cookie :code:`fe_typo_user` with POST requests of other
    websites (:php:`$GLOBALS['TYPO3_CONF_VARS']['FE']['cookieSameSite']`,
    default :code:`lax`). There is no additional CSRF token: the worst
    result of a forged request is a changed order of the job list.

..  _remember-jobs-override:

Own templates
=============

The checkbox, the highlight and the attribute
:html:`data-jobboard-remember-url` are rendered in
:file:`Partials/Types/Table.html`. :file:`Templates/Jobboard/List.html`
and :file:`Templates/Jobboard/Search.html` pass the variable
:html:`{rememberedJobs}` to this partial. It contains the UIDs of the
remembered jobs as keys. If you override one of these files, keep this:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Templates/Jobboard/List.html

    <f:render partial="Types/Table"
              arguments="{objects: jobs, rememberedJobs: rememberedJobs}"/>

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Partials/Types/Table.html

    <table class="jobboard-table table" data-jobboard-remember-url="{f:uri.page()}">
        ...
        <f:variable name="isRemembered"
                    value="{f:if(condition: '{rememberedJobs.{job.uid}}', then: 1, else: 0)}"/>
        <tr data-jobboard-uid="{job.uid}"{f:if(condition: isRemembered, then: ' class="highlight-row"')}>
            ...
            <input type="checkbox" name="remember_job" value="{job.uid}"{f:if(condition: isRemembered, then: ' checked')}>
