:navigation-title: Routing

..  include:: /Includes.rst.txt
..  _configuration-routing:

==============================
Speaking URLs (route enhancer)
==============================

The extension ships a route enhancer for the detail view in
:file:`EXT:jobboard/Configuration/Routes/Default.yaml`. It creates URLs like
:file:`https://example.org/jobs/web-developer-m-f-d` instead of URLs with
query parameters.

TYPO3 13.4 cannot load route enhancers from site sets. Import the file in
your site configuration:

..  literalinclude:: _snippets/_routing.yaml
    :language: yaml
    :caption: config/sites/<site-identifier>/config.yaml

The enhancer has the key :yaml:`JobboardPlugin`. It does not define
:yaml:`limitToPages`, because lists of imported YAML files are merged and
cannot be replaced. Add :yaml:`limitToPages` with the UID of your job board
page below the same key, as shown above.

..  _configuration-routing-details:

How it works
============

*   Route :yaml:`/{job_title}` maps to the action :php:`detail` with the
    argument :code:`job`. The default action is :php:`list`.
*   Route :yaml:`/page/{page}` maps to the action :php:`list` with the
    argument :code:`currentPage` of the page browser, for example
    :file:`https://example.org/jobs/page/2`. The aspect
    :yaml:`StaticRangeMapper` allows the pages 1 to 999. The pages of a
    search result keep their search criteria as query parameters.
*   The aspect :yaml:`PersistedAliasMapper` resolves :yaml:`job_title`
    with the field "URL segment" (:sql:`path_segment`) of the job.
*   The URL segment is created from the job title when a job is saved, also
    for imported jobs. It is unique in the whole job table, because the
    aspect resolves it table-wide. Changing the title does not change the
    URL segment. Editors can regenerate it in the job record.
*   For jobs without URL segment, for example after a migration, run the
    upgrade wizard :code:`jweilandJobboardJobPathSegmentUpdate`, see
    :ref:`upgrade`.

..  _configuration-routing-pdf:

URL of the PDF version
======================

The PDF version of a job uses the page type :code:`1575903427`. Without
further configuration, its URL ends with :code:`?type=1575903427`. To get a
speaking suffix, map the page type with a :yaml:`PageType` decorator:

..  literalinclude:: _snippets/_routing-pdf.yaml
    :language: yaml
    :caption: config/sites/<site-identifier>/config.yaml

If your site already has a :yaml:`PageType` decorator, add the mapping to
it instead of creating a second one.
