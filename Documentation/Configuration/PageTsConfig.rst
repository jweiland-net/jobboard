:navigation-title: Page TSconfig

..  include:: /Includes.rst.txt
..  _configuration-page-tsconfig:

=============
Page TSconfig
=============

The extension loads its page TSconfig automatically through
:file:`EXT:jobboard/Configuration/page.tsconfig`:

*   The content element "Jobboard" is added to the tab
    :guilabel:`Plugins` of the new content element wizard.
*   The page module shows a preview of the content element with its most
    important options (template
    :file:`EXT:jobboard/Resources/Private/Templates/PluginPreview/Jobboard.html`).

..  _configuration-page-tsconfig-recipes:

Recommended page TSconfig
=========================

The following snippets are optional. Add them to the page TSconfig of the
storage folder or of your site package.

..  literalinclude:: _snippets/_page.tsconfig
    :language: typoscript
    :caption: EXT:my_sitepackage/Configuration/page.tsconfig

Clear the cache of the list page
    The list page is cached. :typoscript:`TCEMAIN.clearCacheCmd` clears the
    cache of the given pages whenever a record in the storage folder is
    saved, also by the :ref:`job import <import>`.

Hide salary steps in the list module
    Salary steps are maintained inside their salary grade. Hiding the table
    in the list module keeps the storage folder clear.

Default validity of new salary grades
    Pre-fills start and end time of new salary grades, for example with the
    validity period of the current pay scale. Remember that these dates
    control the visibility of all jobs using the grade, see
    :ref:`editor-salary`.
