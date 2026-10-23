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

..  _configuration-page-tsconfig-cache:

Clear the cache of the job board pages
======================================

..  important::

    Add :typoscript:`TCEMAIN.clearCacheCmd` to the page TSconfig of the
    storage folder. List and detail view are cached. Without this setting,
    changes in the storage folder appear on the job board pages only when
    their cache expires.

..  code-block:: typoscript
    :caption: Page TSconfig of the storage folder

    # UIDs of all pages with the content element "Job board"
    TCEMAIN.clearCacheCmd = 42, 87

:typoscript:`TCEMAIN.clearCacheCmd` clears the cache of the given pages
whenever a record in the storage folder is saved, deleted, hidden or
moved. This applies to:

*   changes of editors in the backend,
*   the :ref:`job import <import>`,
*   the daily command :bash:`jobboard:salary:update`, which saves jobs with
    changed salary information with the DataHandler, see
    :ref:`salary-update`.

List all pages that contain the content element, for example the job
board page and a start page with a short list of jobs. Changes of the dates
of jobs (application deadline, end of employment, publish and expiration
date) do not need a cache clearing, see :ref:`configuration-plugin-caching`.

..  _configuration-page-tsconfig-recipes:

Recommended page TSconfig
=========================

Except for :typoscript:`TCEMAIN.clearCacheCmd`, the following snippets are
optional. Add them to the page TSconfig of the storage folder.

..  literalinclude:: _snippets/_page.tsconfig
    :language: typoscript
    :caption: Page TSconfig of the storage folder

Clear the cache of the list page
    Required in the page TSconfig of the storage folder, see
    :ref:`configuration-page-tsconfig-cache`.

Hide salary steps in the list module
    Salary steps are maintained inside their salary grade. Hiding the table
    in the list module keeps the storage folder clear.

Default validity of new salary grades
    Pre-fills start and end time of new salary grades, for example with the
    validity period of the current pay scale. Remember that these dates
    control the visibility of all jobs using the grade, see
    :ref:`editor-salary`.
