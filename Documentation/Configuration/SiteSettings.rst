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

    ..  confval:: jobboard.pageBrowser.enable
        :name: jobboard-pagebrowser-enable
        :type: bool
        :default: :yaml:`true`

        Splits the list and the search result into pages of
        :confval:`jobboard-pagebrowser-itemsperpage` jobs and shows the page
        browser, see :ref:`templates-pagination`. With :yaml:`false`, the
        list and the search result show all jobs on one page.

        Can be overridden per content element, see
        :confval:`plugin-pagebrowser-enable`. A content element with
        :confval:`plugin-maxentries` greater than :code:`0` never shows the
        page browser, independent of this setting.

        Mapped to
        :typoscript:`plugin.tx_jobboard.settings.pageBrowser.enable`.

    ..  confval:: jobboard.pageBrowser.itemsPerPage
        :name: jobboard-pagebrowser-itemsperpage
        :type: int
        :default: :yaml:`15`

        Number of jobs on one page of the list and of the search result.
        Further jobs are reached with the page browser, see
        :ref:`templates-pagination`. Has no effect, if the page browser is
        disabled, see :confval:`jobboard-pagebrowser-enable`.

        Mapped to
        :typoscript:`plugin.tx_jobboard.settings.pageBrowser.itemsPerPage`.

    ..  confval:: jobboard.salary.required
        :name: jobboard-salary-required
        :type: bool
        :default: :yaml:`true`

        With :yaml:`true`, only jobs with salary information are shown:
        the job needs an effective salary (minimum or maximum greater than
        :code:`0`, see :ref:`salary-update`). Jobs without salary
        information are hidden in the list, the search result, the detail
        view (HTTP status 404), the remembered jobs and the map. The detail
        view does not link related jobs without salary information. This
        is the default.

        With :yaml:`false`, jobs without salary information are shown like
        any other open job. All other conditions still apply, for example
        title, application deadline, end of employment and the publish and
        expiration date of the job, see :ref:`editor-visibility`. The
        salary is shown for jobs that have one. The effective salary is
        calculated in both cases, so switching the setting later does not
        require a recalculation.

        The EU pay transparency directive (EU) 2023/970 requires that
        applicants receive information about the initial pay or its range.
        Whether and from when this applies to your job offers depends on
        the national transposition of the directive. This documentation is
        no legal advice. Clarify with your legal department which value is
        right for your site.

        ..  literalinclude:: _snippets/_settings-salary-not-required.yaml
            :language: yaml
            :caption: config/sites/<site-identifier>/settings.yaml

        The setting can also be changed in the backend module
        :guilabel:`Site Management > Settings`, category
        :guilabel:`Salary`. Flush the frontend caches after changing it,
        cached pages still show the jobs of the previous value.

    ..  confval:: jobboard.salary.numberFormat
        :name: jobboard-salary-numberformat
        :type: string
        :default: :yaml:`'automatic'`

        How salary amounts are displayed in the detail view (salary range
        and salary steps). Change it in the backend module
        :guilabel:`Site Management > Settings`, category
        :guilabel:`Salary`:

        :yaml:`automatic`
            The number format of the locale of the site language
            (:yaml:`locale` in the site configuration), formatted with
            :php:`\NumberFormatter` of the PHP extension intl. Separators,
            grouping and the position of the currency sign follow the
            locale, for example:

            ========  ================
            Locale    Display
            ========  ================
            de_DE     1.234,56 €
            en_US     €1,234.56
            nl_NL     € 1.234,56
            de_CH     CHF 1’234.56
            fr_FR     1 234,56 €
            ========  ================

            This is the default.

        :yaml:`custom`
            The separators of :confval:`jobboard-salary-thousandsseparator`
            and :confval:`jobboard-salary-decimalseparator`, and the
            currency sign before or after the amount, see
            :confval:`jobboard-salary-currencyposition`.

        The currency is :confval:`jobboard-salary-currency` and the number
        of decimal places :confval:`jobboard-salary-decimals`, for both
        formats. Only the display changes: the stored salaries, the
        effective salary and the structured data for search engines (see
        :ref:`templates-json-ld`) keep the plain numbers. Own templates
        have to use the ViewHelper of :ref:`templates-viewhelper-salary`.

        ..  literalinclude:: _snippets/_settings-salary-number-format.yaml
            :language: yaml
            :caption: config/sites/<site-identifier>/settings.yaml

        Flush the frontend caches after changing one of the number format
        settings.

    ..  confval:: jobboard.salary.currency
        :name: jobboard-salary-currency
        :type: string
        :default: :yaml:`'EUR'`

        ISO 4217 code of the currency of the salary amounts, for example
        :yaml:`'EUR'`, :yaml:`'CHF'` or :yaml:`'USD'`. The currency sign is
        taken from the locale of the page, for example :code:`€` or
        :code:`CHF`. The structured data for search engines uses the same
        code. A value that is not three uppercase letters is replaced by
        :yaml:`'EUR'`.

    ..  confval:: jobboard.salary.decimals
        :name: jobboard-salary-decimals
        :type: int
        :default: :yaml:`2`

        Number of decimal places of salary amounts, from :yaml:`0` to
        :yaml:`4`, for both number formats. The amounts are rounded half up
        for the display only. An invalid value is replaced by the default.

    ..  confval:: jobboard.salary.thousandsSeparator
        :name: jobboard-salary-thousandsseparator
        :type: string
        :default: :yaml:`','`

        Character between groups of thousands of the number format
        :yaml:`custom`, for example :yaml:`','`, :yaml:`'.'`, :yaml:`"'"`
        or a space :yaml:`' '`. Leave it empty (:yaml:`''`) for no
        grouping. Ignored by the automatic format.

        See :confval:`jobboard-salary-decimalseparator` for the rules.

    ..  confval:: jobboard.salary.decimalSeparator
        :name: jobboard-salary-decimalseparator
        :type: string
        :default: :yaml:`'.'`

        Character between the whole number and the decimal places of the
        number format :yaml:`custom`, for example :yaml:`'.'` or
        :yaml:`','`. Ignored by the automatic format.

        Both separators of the custom format are validated, so an amount
        can not be misread:

        *   The decimal separator is exactly one character, but no space.
        *   The thousands separator is empty or one character.
        *   No separator is a digit, a plus or a minus sign.
        *   Both separators are different, for example :yaml:`'.'` and
            :yaml:`'.'` is not allowed.

        If the custom format breaks one of these rules, the
        :yaml:`automatic` format is used instead.

    ..  confval:: jobboard.salary.currencyPosition
        :name: jobboard-salary-currencyposition
        :type: string
        :default: :yaml:`'after'`

        Position of the currency sign of the number format :yaml:`custom`:
        :yaml:`'after'` the amount (:code:`1.234,56 €`) or :yaml:`'before'`
        the amount (:code:`€ 1.234,56`). A non-breaking space separates the
        sign and the amount. Ignored by the automatic format, which takes
        the position from the locale. Any other value uses the
        :yaml:`automatic` format.

    ..  confval:: jobboard.pdf.logo
        :name: jobboard-pdf-logo
        :type: string
        :default: :yaml:`''`

        Image shown at the top right of every page of the PDF version of a
        job, for example
        :file:`EXT:my_sitepackage/Resources/Public/Images/logo.png` or
        :file:`fileadmin/logo.png`. Leave it empty for a PDF without header,
        see :ref:`pdf-export`. Defined by the site set
        :yaml:`jweiland/jobboard-pdf`.

        Mapped to :typoscript:`plugin.tx_jobboard.settings.pdfLogo`.

    ..  confval:: jobboard.pdf.linkMode
        :name: jobboard-pdf-linkmode
        :type: string
        :default: :yaml:`'download'`

        How the PDF link of the detail view opens the PDF version of a job,
        see :ref:`pdf-export`. Defined by the site set
        :yaml:`jweiland/jobboard-pdf`.

        :yaml:`download`
            The PDF is sent as file download. Link label "Download PDF file
            of the job offer".

        :yaml:`newTab`
            The PDF is shown in the browser in a new tab
            (:html:`target="_blank"`). Link label "Show rendered PDF in new
            tab".

        :yaml:`sameTab`
            The PDF is shown in the browser in the same tab. Link label
            "Show PDF".

        Mapped to :typoscript:`plugin.tx_jobboard.settings.pdfLinkMode` on
        all pages, as the detail view and the PDF page type both use it.

