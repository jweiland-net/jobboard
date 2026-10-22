:navigation-title: Storage folder

..  include:: /Includes.rst.txt
..  _editor-storage-folder:

=============================
Records in the storage folder
=============================

All records of the job board are stored in a folder in the page tree. Your
administrator has configured this folder, see
:confval:`jobboard-storagepid`. Open it in the module
:guilabel:`Web > List`.

..  figure:: /Images/Editor/list-module.png
    :alt: List module with all record types of the job board
    :class: with-border with-shadow
    :zoom: lightbox

    The storage folder in the list module (all tables collapsed)

..  _editor-record-types:

Record types
============

..  list-table::
    :header-rows: 1
    :widths: 35 65

    *   -   Record (German label)
        -   Purpose
    *   -   Job (Stellenangebot)
        -   The job offer, see :ref:`editor-job`.
    *   -   Occupational field (Berufsfeld)
        -   Categories of jobs, for example "IT" or "Administration". A job
            can have several occupational fields.
    *   -   Position (Position)
        -   Level of the position, for example "Specialist" or "Manager".
    *   -   Type of employment (Beschäftigungsart)
        -   Employment scope, for example "Full-time" or "Part-time". Shown
            in the job as "Employment scope".
    *   -   Contract type (Vertragsart)
        -   For example "Permanent" or "Fixed-term".
    *   -   Tender type (Art der Ausschreibung)
        -   For example "Internal" or "Internal and external".
    *   -   Benefit (Benefit)
        -   Benefits of the employer with color, description and image.
    *   -   Salary table (Besoldungstabelle)
        -   Groups salary grades, for example of one pay scale.
    *   -   Salary grade (Besoldungsgruppe)
        -   A salary grade like "A 9", with steps or a fixed amount.
    *   -   Salary step (Besoldungsstufe)
        -   One step of a salary grade. Edited inside the salary grade.
    *   -   Address
        -   Job locations and employer addresses (EXT:tt_address), see
            :ref:`editor-addresses`.

Create the records in this order: occupational fields, positions, types
of employment, contract types, tender types, benefits, salary grades and
addresses first, then the jobs that use them.

..  _editor-translation:

Translations
============

All records of the job board are translatable. To get the buttons for
translating records in the list module, the storage folder itself needs a
translation into the target language: use
:guilabel:`Create a new translation of this page` at the top of the list
module first.
