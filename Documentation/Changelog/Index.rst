:navigation-title: Changelog

..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

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
