:navigation-title: PDF export

..  include:: /Includes.rst.txt
..  _pdf-export:

==========
PDF export
==========

The detail view links to a PDF version of the job. The PDF is rendered
with EXT:pdfviewhelpers (:composer:`bithost-gmbh/pdfviewhelpers`) and
opens in the browser.

Setup
=====

*   The site set :yaml:`jweiland/jobboard-pdf` is loaded automatically by
    the main set. It defines the page type :code:`1575903427`
    (:typoscript:`pageJobboardPDF`), which renders the content element
    "Job board" with the PDF template.
*   Add the site set :yaml:`bithost/pdfviewhelpers` of EXT:pdfviewhelpers
    to your site, see :ref:`installation-site-set`.
*   Optionally map the page type to a URL suffix, see
    :ref:`configuration-routing-pdf`.
*   Optionally show a logo at the top right of every page, see
    :confval:`jobboard-pdf-logo`. Without a logo the PDF has no header.

Template
========

The PDF template is :file:`EXT:jobboard/Resources/Private/Pdf/Jobboard/Detail.html`.
It is registered as :typoscript:`plugin.tx_jobboard.view.templateRootPaths.100`
for the PDF page type only. The layout of the PDF (margins, fonts, text
types) is configured with the TypoScript settings of EXT:pdfviewhelpers
in :file:`EXT:jobboard/Configuration/Sets/Pdf/setup.typoscript`. See the
`documentation of EXT:pdfviewhelpers <https://docs.typo3.org/p/bithost-gmbh/pdfviewhelpers/main/en-us/>`__
for all options.
