:navigation-title: Changelog

..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

..  _changelog-0-0-3:

Version 0.0.3
=============

*   Jobs are sorted by creation date, newest first, in the list module
    of the backend.
*   The record title of addresses prefers the full name and falls back
    to the last and first name, the company and the email address.
    Addresses with a company only are no longer shown as "[No title]".
*   The description of the employer address in the backend explains the
    fallback to the company of the workplace.

..  _changelog-0-0-2:

Version 0.0.2
=============

*   The field :sql:`offer` is labelled "Tasks" ("Aufgaben") in the
    backend and in the detail view. The column name is unchanged.
*   The reference number of a job is optional.
*   The contact email address of a job is optional.
*   The start of employment is optional. If it is empty, the detail view
    shows "As soon as possible" ("baldmöglichst") instead of a date.
    Overridden detail templates should render the new label
    :code:`job.startEndDate.startDate.asSoonAsPossible`.
*   **Breaking:** Name and logo of the employer are taken from the employer
    address: the company and the first image of the address. The job
    columns :sql:`employer` and :sql:`employer_logos` are removed, the
    properties :html:`{job.employer}` and :html:`{job.employerLogos}` no
    longer exist. Overridden templates have to use
    :html:`{job.employerAddress.company}` and
    :html:`{job.employerAddress.firstImage}`. Without a company of the
    employer address, the list, the sidebar of the detail view and the
    structured data use the company of the workplace. Run the upgrade
    wizard
    :code:`jweilandJobboardEmployerToEmployerAddressMigration` before the
    database compare removes the old columns, see :ref:`upgrade-wizards`.

..  _changelog-0-0-1:

Version 0.0.1
=============

*   Initial alpha version, successor of EXT:jobfair2 for TYPO3 13.4 LTS.
*   New site setting :confval:`jobboard-salary-required` (default
    :yaml:`true`). Disable it to show jobs without salary information.
*   Salary amounts follow the locale of the site language, formatted with
    :php:`\NumberFormatter` (for example 1.234,56 € in German, € 1.234,56
    in Dutch, CHF 1'234.56 in Swiss German) instead of always the German
    format. New site setting :confval:`jobboard-salary-currency` (ISO 4217,
    default EUR), also used by the structured data instead of the fixed
    EUR. Overridden detail templates have to use the new ViewHelper, see
    :ref:`templates-viewhelper-salary`.
*   The job columns :sql:`header_logo`, :sql:`employer_logo` and
    :sql:`tender_file` are renamed to :sql:`header_logos`,
    :sql:`employer_logos` and :sql:`tender_files`, the properties
    :html:`{job.headerLogo}`, :html:`{job.employerLogo}` and
    :html:`{job.tenderFile}` to :html:`{job.headerLogos}`,
    :html:`{job.employerLogos}` and :html:`{job.tenderFiles}`. Overridden
    templates have to use the new names. Run the upgrade wizard
    :code:`jweilandJobboardJobFileColumnsToPluralMigration`, see
    :ref:`upgrade-wizards`.

All changes are listed in the
`commit history on GitHub <https://github.com/jweiland-net/jobboard/commits/main>`__.
