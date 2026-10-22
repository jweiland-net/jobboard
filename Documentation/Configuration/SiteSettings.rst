:navigation-title: Site settings

..  include:: /Includes.rst.txt
..  _configuration-site-settings:

=============
Site settings
=============

The site set :yaml:`jweiland/jobboard` defines the following settings. Edit
them in the backend module :guilabel:`Site Management > Settings`
(category :guilabel:`Jobboard`) or in the file
:file:`config/sites/<site-identifier>/settings.yaml`.

..  figure:: /Images/Configuration/site-settings.png
    :alt: Site settings of the category Jobboard
    :class: with-border with-shadow
    :zoom: lightbox

    The settings of the job board in the module Site Management > Settings

..  literalinclude:: /Installation/_snippets/_settings.yaml
    :language: yaml
    :caption: config/sites/<site-identifier>/settings.yaml

..  confval-menu::
    :name: site-settings-jobboard
    :display: table
    :type:
    :default:

    ..  confval:: jobboard.templateRootPath
        :name: jobboard-templaterootpath
        :type: string
        :default: :file:`EXT:jobboard/Resources/Private/Templates/`

        Path to an additional template root of the plugin. It is added as
        :typoscript:`plugin.tx_jobboard.view.templateRootPaths.1`, see
        :ref:`templates`.

    ..  confval:: jobboard.partialRootPath
        :name: jobboard-partialrootpath
        :type: string
        :default: :file:`EXT:jobboard/Resources/Private/Partials/`

        Path to an additional partial root of the plugin. It is added as
        :typoscript:`plugin.tx_jobboard.view.partialRootPaths.1`.

    ..  confval:: jobboard.layoutRootPath
        :name: jobboard-layoutrootpath
        :type: string
        :default: :file:`EXT:jobboard/Resources/Private/Layouts/`

        Path to an additional layout root of the plugin. It is added as
        :typoscript:`plugin.tx_jobboard.view.layoutRootPaths.1`.

    ..  confval:: jobboard.storagePid
        :name: jobboard-storagepid
        :type: string (comma-separated list of page UIDs)
        :default: :yaml:`'0'`

        Comma-separated list of page UIDs that store all jobs, for example
        :yaml:`'23'` or :yaml:`'23,24'`. Jobs are also found in up to three
        levels of subpages of these pages. With the default :yaml:`'0'`, no
        jobs are found.

        If the addresses (:sql:`tt_address`) of the job locations live in
        additional folders, add these folders here too. The suggestions of
        the location field in the search form (see
        :ref:`address-search`) only search in the pages listed here.

        Mapped to :typoscript:`plugin.tx_jobboard.persistence.storagePid`.

    ..  confval:: jobboard.pidOfDetailPage
        :name: jobboard-pidofdetailpage
        :type: page
        :default: :yaml:`0`

        Page with a "Job board" content element for the detail view. The
        shipped templates render the detail view on the same page as the
        list. The value is available as :html:`{settings.pidOfDetailPage}`
        in Fluid, for own templates that link to a separate detail page.

    ..  confval:: jobboard.pidOfApplicationForm
        :name: jobboard-pidofapplicationform
        :type: page
        :default: :yaml:`0`

        Page with the online application form. If set to a value greater
        than :yaml:`0`, the detail view shows an "Apply online" link to this
        page (only for jobs with a contact email address), and the card
        layout shows it for each job. The link adds the query parameter
        :code:`jobuid=<uid of the job>`, which your form can evaluate.

        With :yaml:`0`, no "Apply online" link is rendered.

    ..  confval:: jobboard.showJobAreaCount
        :name: jobboard-showjobareacount
        :type: bool
        :default: :yaml:`false`

        Appends the number of jobs to each occupational field in the search
        form, for example "IT (3)". The number only counts jobs of the
        current list. A job with several occupational fields is counted for
        each of them, so the sum can be higher than the number of jobs.

        Can be overridden per content element, see
        :confval:`plugin-showjobareacount`.
