:navigation-title: Page title

..  include:: /Includes.rst.txt


..  _admin-page-title:

=================================
Page title of the job detail view
=================================

On the detail view of a job, Jobboard shows the title of the job as page
title (the HTML :html:`<title>` tag) instead of the title of the page the
:guilabel:`Job board` content element is placed on.

Target group: **Administrators / Integrators**


..  _admin-page-title-requirements:

Requirements
============

*   TYPO3 13.4
*   The Site Set :guilabel:`Jobboard - Main` (`jweiland/jobboard`) added to
    the site. If you do not use the Site Set, add the TypoScript shown in
    :ref:`admin-page-title-configuration` to your own TypoScript setup.
*   :t3ext:`seo` is optional. Without it, :typoscript:`before = seo` has no
    effect.


..  _admin-page-title-configuration:

Configuration
=============

The Site Set :guilabel:`Jobboard - Main` registers the page title provider
:php:class:`\JWeiland\Jobboard\PageTitle\JobPageTitleProvider` with the key
`jobboard`:

..  code-block:: typoscript
    :caption: EXT:jobboard/Configuration/Sets/Jobboard/setup.typoscript

    config.pageTitleProviders.jobboard {
        provider = JWeiland\Jobboard\PageTitle\JobPageTitleProvider
        before = record, seo
    }

TYPO3 asks the registered page title providers in their configured order
and uses the first non-empty title. See
:ref:`Page title API <t3coreapi:pagetitle>` for details.


..  _admin-page-title-behaviour:

Behaviour
=========

*   Only the detail view of the :guilabel:`Job board` plugin
    (`jobboard_jobboard`) sets a title: the :sql:`title` field of the
    displayed job, with surrounding whitespace removed.
*   On every other page, and in the list and search view, the provider
    returns an empty string. The next provider is used then: `seo` (field
    :guilabel:`Title for search engines` of the page), then `record` (the
    page title).
*   :typoscript:`config.pageTitleSeparator`,
    :typoscript:`config.showWebsiteTitle`, :typoscript:`config.pageTitleFirst`
    and the :typoscript:`config.pageTitle` stdWrap still apply, so the
    result may look like :samp:`Site title: Web developer (m/w/d)`.
*   The plugin can stay cached. The resolved title is stored in the page
    cache together with the page, so it is also shown for cached page
    deliveries.


..  _admin-page-title-order:

Order with other page title providers
=====================================

..  attention::
    If your site package registers its own page title provider that always
    returns a title (for example a site-wide fallback), order it after
    `jobboard`. If several providers only declare
    :typoscript:`before = record, seo`, their order among each other
    depends on internal sorting, and the job title may not show up.

    ..  code-block:: typoscript
        :caption: EXT:my_sitepackage/Configuration/Sets/MySitePackage/setup.typoscript

        config.pageTitleProviders.my_sitepackage {
            provider = MyVendor\MySitePackage\PageTitle\FallbackPageTitleProvider
            before = record, seo
            after = jobboard
        }


..  _admin-page-title-disable:

Disable the job title
=====================

To keep the regular page title on the detail view, remove the provider:

..  code-block:: typoscript
    :caption: EXT:my_sitepackage/Configuration/Sets/MySitePackage/setup.typoscript

    config.pageTitleProviders.jobboard >


..  _admin-page-title-override:

Replace the page title provider
===============================

:php:class:`\JWeiland\Jobboard\PageTitle\JobPageTitleProvider` is declared
:php:`final`. Do not extend it, replace it via TypoScript instead: set
:typoscript:`config.pageTitleProviders.jobboard.provider` to your own class
implementing
:php:interface:`\TYPO3\CMS\Core\PageTitle\PageTitleProviderInterface`.
Change :typoscript:`before` and :typoscript:`after` to adjust the order.

..  code-block:: typoscript
    :caption: EXT:my_sitepackage/Configuration/Sets/MySitePackage/setup.typoscript

    config.pageTitleProviders.jobboard {
        provider = MyVendor\MySitePackage\PageTitle\JobPageTitleProvider
    }

..  note::
    Your own class does not receive the job title from the
    :guilabel:`Job board` plugin automatically, as the plugin only passes
    it to :php:class:`\JWeiland\Jobboard\PageTitle\JobPageTitleProvider`.
