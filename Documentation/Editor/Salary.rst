:navigation-title: Salary

..  include:: /Includes.rst.txt
..  _editor-salary:

=============================================
Salary tables, salary grades and salary steps
=============================================

Every job must have salary information, otherwise it is not shown. A job
either refers to one or more **salary grades** or contains a free
**minimum and maximum salary**, see :ref:`editor-job-salary`.

..  code-block:: text
    :caption: How the salary records belong together

    Salary table (optional, e.g. "Sample pay scale 2026")
     └── Salary grade (e.g. "A 9")
          ├── has steps:   Salary steps (step label + amount)
          └── no steps:    Fixed amount

    Job, salary mode "Salary grade (tariff-based)" → one or more salary grades
    Job, salary mode "Free salary entry"           → minimum / maximum salary

The frontend shows the lowest and the highest amount of all steps or fixed
amounts of all salary grades selected in a job, for example
"3,480.07 € - 4,342.82 €". If there is only one amount, only this amount is
shown.

..  _editor-salary-grade:

Salary grade
============

..  figure:: /Images/Editor/salarygrade-with-steps.png
    :alt: Salary grade A 9 with two salary steps
    :class: with-border with-shadow
    :zoom: lightbox

    Salary grade with steps, the first step is opened

:guilabel:`Grade` (Gruppe)
    Name of the salary grade, for example "A 9", "R 3" or "B 3". Required,
    maximum 60 characters. Salary grades are sorted by this name.

:guilabel:`Has steps?` (Hat Stufen?)
    Enabled by default. Disable it for grades without seniority steps.
    Changing it reloads the form, because the visible fields differ.

:guilabel:`Steps` (Stufen)
    Only for grades with steps. At least one step is required. Not every
    step has to exist, gaps are allowed.

:guilabel:`Fixed amount` (Fester Betrag)
    Only for grades without steps.

..  figure:: /Images/Editor/salarygrade-fixed-amount.png
    :alt: Salary grade R 3 without steps and with a fixed amount
    :class: with-border with-shadow
    :zoom: lightbox

    Salary grade without steps

In select boxes, for example in the job, a salary grade of a salary table
is shown with the title of the table: "A 9, Sample pay scale 2026".

..  _editor-salary-step:

Salary step
===========

Salary steps are created inside their salary grade.

:guilabel:`Step` (Stufe)
    Free text, for example "3", "3a" or "Entry step". Required, maximum 30
    characters.

:guilabel:`Amount` (Betrag)
    Monthly amount. Required. You can enter the amount with a thousands
    separator and a decimal comma, for example :code:`3.842,17`. It is
    normalized when you leave the field.

A hidden or expired step is ignored, like a step that expires within the
next 24 hours. If all steps of a grade are hidden or expired, the grade has
no amount any more.

..  _editor-salary-validity:

Validity of salary grades
=========================

..  figure:: /Images/Editor/salarygrade-access.png
    :alt: Tab Access of a salary grade with publish and expiration date
    :class: with-border with-shadow
    :zoom: lightbox

    The validity of a salary grade is maintained in the tab "Access"

..  attention::

    The publish date and expiration date of a **salary grade** control the
    visibility of **every job that uses this grade**. When the period of a
    grade has expired (or not started yet), the grade is ignored. Jobs
    without another valid grade then have no salary information: they
    disappear from the list and their detail page returns "Page not found".

    A grade or step already counts as expired **24 hours before** its
    expiration date. Maintain the salary grades of a new pay scale in time,
    at the latest one day before the old ones expire.

Changes that you save take effect at once. When a publish or expiration
date is reached without any change, the affected jobs are updated by a
command that runs once a day, usually at night, see :ref:`salary-update`.
A grade with a publish date in the future therefore counts for the jobs
from the first run of the command after this date.

..  _editor-salary-translation:

Salary in translated jobs
=========================

The salary of a job is the same in all languages. In a translated job, the
fields :guilabel:`Salary mode`, :guilabel:`Salary grades`,
:guilabel:`Minimum salary` and :guilabel:`Maximum salary` show the values
of the default language and cannot be edited. Change them in the job of
the default language, the translations take them over.

..  _editor-salary-table:

Salary table
============

..  figure:: /Images/Editor/salarytable.png
    :alt: Salary table with a list of salary grades
    :class: with-border with-shadow
    :zoom: lightbox

    A salary table with its salary grades

A salary table groups salary grades, for example all grades of one pay
scale. It is optional.

:guilabel:`Title` (Titel)
    Required.

:guilabel:`Description` (Beschreibung)
    Optional plain text.

:guilabel:`Salary grades` (Besoldungsgruppen)
    Salary grades of this table. Grades created here belong to this table.

The publish and expiration date of a salary table are for your own
documentation only. They do not hide the table or its grades. The
validity that counts is maintained in each salary grade.
