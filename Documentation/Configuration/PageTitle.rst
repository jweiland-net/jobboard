:navigation-title: Page title

..  include:: /Includes.rst.txt
..  _configuration-page-title:

=======================
Job title as page title
=======================

On the detail view, the title of the job is used as page title (the
:html:`<title>` tag). The site set registers the page title provider
:php:`JWeiland\Jobboard\PageTitle\JobPageTitleProvider`:

..  code-block:: typoscript
    :caption: EXT:jobboard/Configuration/Sets/Jobboard/setup.typoscript

    config.pageTitleProviders {
      jobboard {
        provider = JWeiland\Jobboard\PageTitle\JobPageTitleProvider
        before = record, seo
      }
    }

The provider is ordered before :typoscript:`record` (page title) and
:typoscript:`seo` (field "Title for search engines" of the page), because
the first provider that returns a title wins.

How it works
============

*   The provider reads the argument :code:`tx_jobboard_jobboard[job]` of
    the current request. On all other pages it returns an empty string and
    the next provider is used.
*   The job is loaded with the usual frontend restrictions (hidden, start
    and end time) and translated into the current language.
*   Settings like :typoscript:`config.pageTitleSeparator` and
    :typoscript:`config.showWebsiteTitle` still apply.

See :ref:`t3coreapi:pagetitle` for the page title API of TYPO3.

Customizing
===========

..  code-block:: typoscript
    :caption: Disable the page title provider of the job board

    config.pageTitleProviders.jobboard >

..  code-block:: typoscript
    :caption: Replace the provider with your own class

    config.pageTitleProviders.jobboard.provider = MyVendor\MySitepackage\PageTitle\MyJobPageTitleProvider
