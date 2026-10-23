:navigation-title: Job

..  include:: /Includes.rst.txt
..  _editor-job:

==============
The job record
==============

Create a job with :guilabel:`New Job` in the list module of the storage
folder. Fields marked as required must be filled before you can save.

..  _editor-job-description:

Tab "Job Description" (Job-Beschreibung)
========================================

..  figure:: /Images/Editor/job-tab-description.png
    :alt: Tab Job Description of a job record
    :class: with-border with-shadow
    :zoom: lightbox

    Tab "Job Description"

:guilabel:`Job title` (Jobtitel)
    Required, maximum 250 characters. Used as page title of the detail
    view.

:guilabel:`Reference number` (Kennziffer)
    Required, maximum 60 characters.

:guilabel:`Subtitle` (Untertitel)
    Optional.

:guilabel:`URL segment` (URL-Segment)
    Part of the URL of the detail view. It is created from the title on the
    first save. Use the button next to the field to recreate it after you
    changed the title.

:guilabel:`Job description` and further text fields
    :guilabel:`Job description`, :guilabel:`Offer`, :guilabel:`Requirements`
    and :guilabel:`Further Information` are rich text fields. The keyword
    search searches all of them.

:guilabel:`Is imported record?`, :guilabel:`Vacancy ID`
    Read-only. Set by the :ref:`job import <import>`. The vacancy ID is only
    shown for imported jobs.

..  _editor-job-details:

Tab "Job Details" (Eckdaten)
============================

..  figure:: /Images/Editor/job-tab-details-grade.png
    :alt: Tab Job Details of a job with salary mode salary grade
    :class: with-border with-shadow
    :zoom: lightbox

    Tab "Job Details" with salary mode "Salary grade (tariff-based)"

:guilabel:`Workplace` (Arbeitsort)
    Required. Exactly one address, see :ref:`editor-addresses`.

:guilabel:`Position` and further single selections
    :guilabel:`Position`, :guilabel:`Employment scope`, :guilabel:`Contract
    type` and :guilabel:`Tender type`: one record each, see
    :ref:`editor-lookup-records`.

:guilabel:`Occupational fields` (Berufsfelder)
    At least one occupational field.

:guilabel:`Benefits`
    Any number of benefits, see :ref:`editor-benefit`.

..  _editor-job-salary:

Salary (palette "Remuneration")
-------------------------------

:guilabel:`Salary mode` (Gehaltsmodus) decides how the salary is entered.
Changing it reloads the form.

Salary grade (tariff-based)
    Select one or more :guilabel:`Salary grades`. The frontend shows the
    lowest and highest amount of all steps or fixed amounts of these
    grades. See :ref:`editor-salary`.

Free salary entry
    Enter :guilabel:`Minimum salary` and :guilabel:`Maximum salary`. For an
    exact salary, enter the same amount in both fields. If the maximum is
    empty, the minimum is shown as salary. Amounts can be entered with a
    thousands separator and a decimal comma, for example :code:`3.842,17`.

..  figure:: /Images/Editor/job-tab-details-free.png
    :alt: Tab Job Details of a job with salary mode free salary entry
    :class: with-border with-shadow
    :zoom: lightbox

    Salary mode "Free salary entry" with minimum and maximum salary

In a translated job, the salary fields show the values of the default
language and are read-only, see :ref:`editor-salary-translation`.

..  important::

    Jobs without salary information are never shown in the frontend. See
    :ref:`editor-visibility`.

..  _editor-job-employer:

Tab "Employer" (Arbeitgeber)
============================

..  figure:: /Images/Editor/job-tab-employer.png
    :alt: Tab Employer of a job record
    :class: with-border with-shadow
    :zoom: lightbox

    Tab "Employer"

:guilabel:`Employer` (Arbeitgeber)
    Required, maximum 60 characters.

:guilabel:`Employer logo`
    Optional image files.

:guilabel:`Address of the employer`
    Optional address record.

..  _editor-job-application:

Tab "Application Info" (Bewerbungsinfos)
========================================

..  figure:: /Images/Editor/job-tab-application.png
    :alt: Tab Application Info with contact person and dates
    :class: with-border with-shadow
    :zoom: lightbox

    Tab "Application Info"

Contact person
    :guilabel:`First name`, :guilabel:`Last name`, :guilabel:`Phone
    number`, :guilabel:`Function` are optional. :guilabel:`Email address`
    is required. The "Apply online" link of the detail view is only shown
    for jobs with an email address.

:guilabel:`Start of employment` (Datum für Arbeitsbeginn)
    Required. Information for applicants only: the job is shown as soon as
    it is saved, regardless of this date.

:guilabel:`End of employment` (Ende der Beschäftigung)
    Optional, only for fixed-term positions: the last day of employment.
    After this day, the job is no longer listed.

:guilabel:`Application deadline` (Bewerbungsfrist)
    Optional. Last day for applications. It is shown in the list, the
    detail view and the PDF version. The job is listed until the
    end of this day.

:guilabel:`Application guidelines` (Hinweise zur Bewerbung)
    Rich text with information about the application process.

..  _editor-job-media:

Tab "Media" (Medien)
====================

:guilabel:`Header image`
    Optional image files.

:guilabel:`Tender documents`, :guilabel:`Job advertisement`
    Optional PDF files. They are linked in the detail view.

:guilabel:`PDF generated on`
    Optional date, shown in the detail view.

..  _editor-job-relations:

Tab "Relations" (Relationen)
============================

:guilabel:`Related jobs`
    Up to 99 other jobs. The detail view links to them.

..  _editor-job-access:

Tab "Access"
============

Publish date and expiration date of the job. The field
:guilabel:`Enabled` on the first tab hides or shows the job.
