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

All changes are listed in the
`commit history on GitHub <https://github.com/jweiland-net/jobboard/commits/main>`__.
