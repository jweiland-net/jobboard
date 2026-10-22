:navigation-title: Upgrade

..  include:: /Includes.rst.txt
..  _upgrade:

===============
Upgrade wizards
===============

The extension ships upgrade wizards for the migration from its predecessor
EXT:jobfair2 and for older installations of the job board.

Run them in the backend module :guilabel:`Admin Tools > Upgrade >
Upgrade Wizard` or on the command line:

..  code-block:: bash
    :caption: List and run the upgrade wizards

    vendor/bin/typo3 upgrade:list
    vendor/bin/typo3 upgrade:run jweilandJobboardJobfairToJobboardMigration

All wizards require an up-to-date database. Run
:bash:`vendor/bin/typo3 extension:setup` (or the database analyzer) before,
so that the new tables exist, and remove old tables and columns only
afterwards.

..  _upgrade-wizards:

Available wizards
=================

..  list-table::
    :header-rows: 1
    :widths: 40 60

    *   -   Identifier
        -   What it does
    *   -   :code:`jweilandJobboardJobfairToJobboardMigration`
        -   Copies all records of the tables
            :sql:`tx_jobfair2_domain_model_*` into the tables
            :sql:`tx_jobboard_domain_model_*`. The UIDs are kept, so all
            relations between the records stay intact. Only columns that
            exist in both tables are copied. File references of employer
            logo, header image, tender documents and job advertisement are
            moved to the new table.
    *   -   :code:`jweilandJobboardJobfairToJobboardCTypeMigration`
        -   Changes content elements of the type :code:`jobfair2_jobfair`
            to :code:`jobboard_jobboard` and updates the permissions of
            backend user groups.
    *   -   :code:`jweilandJobboardJobAreaToJobAreasMigration`
        -   Moves the single occupational field of early job board
            installations (column :sql:`job_area`) to the relation
            :sql:`job_areas`. Only needed if this column still exists.
    *   -   :code:`jweilandJobboardSalaryGradeToSalaryGradesMigration`
        -   Moves the single salary grade of early job board installations
            (column :sql:`salary_grade`) to the relation
            :sql:`salary_grades`. Only needed if this column still exists.
    *   -   :code:`jweilandJobboardJobPathSegmentUpdate`
        -   Creates the URL segment of all jobs without one, see
            :ref:`configuration-routing`.

..  _upgrade-jobfair2:

Migrate from EXT:jobfair2
=========================

#.  Install the job board (see :ref:`installation`). Keep the tables of
    EXT:jobfair2 in the database for now.
#.  Run :bash:`vendor/bin/typo3 extension:setup`.
#.  Run :code:`jweilandJobboardJobfairToJobboardMigration`.
#.  Run :code:`jweilandJobboardJobfairToJobboardCTypeMigration`.
#.  Run :code:`jweilandJobboardJobPathSegmentUpdate`.
#.  Replace the TypoScript of EXT:jobfair2 by the site set of the job board
    and check the site settings, see :ref:`configuration`.
#.  Check the options of the migrated content elements.
#.  Assign occupational fields and positions to the migrated jobs.
    EXT:jobfair2 had no occupational fields and no positions, so there is
    nothing to migrate for them.
#.  Check the salary information of the migrated jobs (salary mode and
    salary grades or minimum and maximum salary). Jobs without salary
    information are not shown.
#.  Remove EXT:jobfair2 and its tables.

Because the UIDs of the jobs are kept, jobs that visitors have remembered
in the job list stay remembered.
