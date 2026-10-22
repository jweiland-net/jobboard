:navigation-title: Content element

..  include:: /Includes.rst.txt
..  _editor-content-element:

======================================
Insert the content element "Job board"
======================================

#.  Open the page in the module :guilabel:`Web > Page`.
#.  Click :guilabel:`+ Content` in the column where the job board should
    appear.
#.  Open the tab :guilabel:`Plugins` and select :guilabel:`Jobboard`
    (Stellenbörse). You can also type "Jobboard" into the search field.

    ..  figure:: /Images/Editor/new-content-element-wizard.png
        :alt: New content element wizard with the entry Jobboard
        :class: with-border with-shadow
        :zoom: lightbox

        The content element in the new content element wizard

#.  Open the tab :guilabel:`Configuration` and adjust the options of the
    sheets :guilabel:`General` and :guilabel:`Search`. All options are
    explained in :ref:`configuration-plugin`.
#.  Save the content element.

The page module shows the most important options of the content element:

..  figure:: /Images/Editor/page-module-preview.png
    :alt: Preview of the content element in the page module
    :class: with-border with-shadow
    :zoom: lightbox

    Preview in the page module. "Job Areas" lists the UIDs of the selected
    occupational fields.

The content element shows the list and, after a click on a job, the
detail view on the same page. The jobs are taken from the storage folder
configured for the whole site. There is no field to choose the storage
folder in the content element.

..  _editor-content-element-options:

Typical configurations
======================

Show only jobs of some occupational fields
    Select them in :guilabel:`Job areas` (sheet :guilabel:`General`).

Show only the latest jobs, for example on the start page
    Set :guilabel:`Max entries to show` to a number and disable
    :guilabel:`Show Search`. Jobs are sorted by application deadline.

Location as select box instead of a text field
    Set :guilabel:`Address search field` to :guilabel:`Job location select
    box`.
