:navigation-title: Remembered jobs

..  include:: /Includes.rst.txt
..  _remember-jobs:

===============
Remembered jobs
===============

Visitors can tick "Remember" for single jobs. The list and the search
result keep their order. Remembered jobs are
highlighted and their checkbox is checked.

Above the list, a link "Remembered jobs" with a badge shows the number of
remembered jobs. It leads to the view of the remembered jobs, where
visitors see all their remembered jobs and can remove them again.

..  _remember-jobs-how:

How it works
============

The list is cached and identical for all visitors, see
:ref:`remember-jobs-caching`. The JavaScript file
:file:`EXT:jobboard/Resources/Public/JavaScript/remember-jobs.js` adds
everything that belongs to the visitor in the browser:

#.  After the page is loaded, it sends a read request (see below). With
    the answer, it checks the checkboxes of the remembered jobs, highlights
    their rows (CSS class :css:`highlight-row`) and fills the badge of the
    link to the remembered jobs.
#.  When a checkbox is changed, it sends the job and the new state to the
    server and updates checkboxes, highlight and badge with the answer.

All requests are POST requests with the HTTP header
:code:`jobboard-remember-job`. They go to the URL of the current page,
which the table and the link to the remembered jobs provide in the
attribute :html:`data-jobboard-remember-url`. If the attribute is missing,
the path of the current page in the browser is used. The PSR-15 middleware
:php:`JWeiland\Jobboard\Middleware\RememberJobMiddleware` (identifier
:code:`jweiland/jobboard-remember-job`) answers every frontend request with
this header. It runs before the page is resolved, so it does not render the
page.

Form fields of a request that changes a job:

:code:`job`
    UID of the job in the default language. This UID is valid in all
    languages of the site.

:code:`remember`
    :code:`1` remembers the job, :code:`0` forgets it.

A request with an empty body only reads the remembered jobs. It never
creates or changes a session.

The answer is always a JSON object with the UIDs of all remembered jobs. In
case of an error, it also contains an error code. All answers are sent with
:code:`Cache-Control: no-store`.

..  code-block:: bash
    :caption: Example request that remembers the job with UID 12

    curl -X POST -H 'jobboard-remember-job: 1' \
        -d 'job=12&remember=1' https://example.org/jobs

..  code-block:: bash
    :caption: Example request that only reads the remembered jobs

    curl -X POST -H 'jobboard-remember-job: 1' https://example.org/jobs

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
        -   The job was remembered or forgotten, or the remembered jobs
            were read.
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
        -   The request is not a POST request. The answer contains the error
            code only, no remembered jobs.
    *   -   409
        -   :code:`limitReached`
        -   The visitor has reached the maximum number of remembered jobs.

If a request fails, :file:`remember-jobs.js` shows the remembered jobs of
the answer and a message in a toast. Without an answer (network error), it
resets the checkbox. A visually hidden status element announces the new
number of remembered jobs to screen readers.

..  _remember-jobs-view:

View of the remembered jobs
===========================

The link "Remembered jobs" opens the action :php:`rememberedJobs` of the
content element, with the speaking URL :file:`/remembered` (see
:ref:`configuration-routing`). It lists all remembered jobs of the visitor
that are still listed in the frontend, in the same order as the list,
without page browser. Their checkboxes are checked.

When the visitor unticks a job here, the job is removed from the
remembered jobs at once. Its row stays on the page, greyed out and struck
through (CSS class :css:`jobboard-removed`), until the page is loaded
again, so the visitor can tick it again. The button "Back to all jobs"
leads back to the list.

..  _remember-jobs-session:

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

The list and the detail view are cached, see
:ref:`configuration-plugin-caching`. They never contain data of the
current visitor: the remembered jobs are added by :file:`remember-jobs.js`
in the browser, from the answer of the read request. Therefore every
visitor gets the same cached page.

The view of the remembered jobs shows the jobs of the current visitor, so
the action :php:`rememberedJobs` is not cached.

..  _remember-jobs-security:

Security
========

*   Only UIDs of existing, visible jobs in the default language from the
    storage pages of the current site can be remembered. The storage pages
    are taken from the site setting :confval:`jobboard-storagepid`, never
    from the request. Other data is never written to the session.
*   The number of remembered jobs is limited. Jobs that are not visible
    anymore are removed from the session when the next job is remembered.
*   A request that changes nothing, for example a read request or an
    invalid or unknown job, does not create a session.
*   The middleware only accepts POST requests with the custom header. A
    form or link on another website cannot send this header. A script on
    another website has to ask the browser for permission first (CORS
    preflight request), which TYPO3 does not grant. Additionally, browsers
    do not send the cookie :code:`fe_typo_user` with POST requests of other
    websites (:php:`$GLOBALS['TYPO3_CONF_VARS']['FE']['cookieSameSite']`,
    default :code:`lax`). There is no additional CSRF token: the worst
    result of a forged request is a changed list of remembered jobs.

..  _remember-jobs-override:

Own templates
=============

:file:`remember-jobs.js` relies on these parts of the shipped templates:

:html:`data-jobboard-uid="{job.uid}"`
    On every job of :file:`Partials/Types/List.html`, every row of
    :file:`Partials/Types/Table.html` or every card of
    :file:`Partials/Types/Card.html`. The script highlights the elements
    of remembered jobs and checks the checkboxes inside.

:html:`<input type="checkbox" name="remember_job" value="{job.uid}">`
    The "Remember" checkbox in :file:`Partials/RememberedJobs/Toggle.html`.

:html:`data-jobboard-remember-url="{f:uri.page()}"`
    URL for the requests, on the list and on the link to the remembered
    jobs.

:html:`data-jobboard-remembered-count`
    The badge in :file:`Partials/RememberedJobs/Link.html`.

:html:`data-jobboard-remembered-view`
    Marks the view of the remembered jobs in
    :file:`Templates/Jobboard/RememberedJobs.html`. Inside, unticked rows
    are greyed out instead of highlighted.

:file:`Partials/RememberedJobs/Feedback.html`
    The status element for screen readers and the toast with the error
    messages.

:file:`Partials/JobList.html` (rendered by List and Search) renders the
list partial without
the variable :html:`{rememberedJobs}`, because their output is the same for
all visitors. Never pass it there. Only
:file:`Templates/Jobboard/RememberedJobs.html` passes it, to render the
checkboxes checked:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Partials/JobList.html

    <f:render partial="RememberedJobs/Link"/>
    <f:render partial="RememberedJobs/Feedback"/>
    <f:render partial="Types/List" arguments="{objects: jobs}"/>

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Templates/Jobboard/RememberedJobs.html

    <section data-jobboard-remembered-view>
        <f:render partial="Types/List"
                  arguments="{objects: jobs, rememberedJobs: rememberedJobs}"/>
    </section>
